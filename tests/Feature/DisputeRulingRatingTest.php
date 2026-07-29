<?php

namespace Tests\Feature;

use App\Models\Dispute;
use App\Models\Order;
use App\Models\RatingOutcomeEvent;
use App\Models\User;
use App\Models\UserOperationRating;
use App\Services\DisputeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Linking the final ruling to the rating: opening a dispute marks BOTH parties
 * `disputed` (neutral: it went to dispute); the ruling then marks only the side
 * it was decided against as at-fault — and does so WITHOUT counting the
 * operation twice.
 */
class DisputeRulingRatingTest extends TestCase
{
    use DatabaseTransactions;

    private User $client;
    private User $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = User::query()->where('type', 'business')->orderBy('id')->first()
            ?: $this->markTestSkipped('Needs a business user.');
        $this->client = User::query()
            ->where('type', '!=', 'business')
            ->where('id', '!=', $this->business->id)
            ->orderBy('id')->firstOrFail();
    }

    private function makeOrder(): Order
    {
        return Order::create([
            'user_id' => $this->client->id, 'business_id' => $this->business->id,
            'fulfillment_type' => Order::FULFILLMENT_DELIVERY, 'status' => 'pending',
            'total' => 40, 'discount' => 0, 'delivery_fee' => 0, 'service_fee' => 0,
            'tax' => 0, 'final_total' => 40, 'payment_method' => 'cash', 'address' => 'x',
        ]);
    }

    /** Open a real dispute on the order through the API (records the `disputed` mark). */
    private function openDisputeOn(Order $order): Dispute
    {
        Sanctum::actingAs($this->client);
        $id = $this->postJson("/api/v2/orders/{$order->id}/disputes", ['reason_code' => 'quality'])
            ->assertOk()->json('data.id');

        return Dispute::findOrFail($id);
    }

    private function rating(int $userId, string $role): ?UserOperationRating
    {
        return UserOperationRating::query()->where('user_id', $userId)->where('role', $role)->first();
    }

    public function test_a_ruling_for_the_client_marks_the_business_at_fault(): void
    {
        $order = $this->makeOrder();
        $dispute = $this->openDisputeOn($order);

        $bizTotalBefore = (int) ($this->rating((int) $this->business->id, UserOperationRating::ROLE_BUSINESS)?->total_operations ?? 0);

        // refund_client == the ruling went in the client's favour → business lost.
        app(DisputeService::class)->resolve($dispute, 'refund_client', [], (int) $this->business->id);

        $biz = $this->rating((int) $this->business->id, UserOperationRating::ROLE_BUSINESS);
        $cli = $this->rating((int) $this->client->id, UserOperationRating::ROLE_CLIENT);

        $this->assertSame(1, (int) $biz->fault_count, 'the business the ruling went against is at fault');
        $this->assertSame(0, (int) ($cli->fault_count ?? 0), 'the vindicated client carries no fault');

        // The operation is NOT counted twice: fault overlays the disputed mark.
        $this->assertSame($bizTotalBefore, (int) $biz->total_operations, 'total operations unchanged by the ruling');

        $this->assertDatabaseHas('rating_outcome_events', [
            'operation_type' => RatingOutcomeEvent::OP_ORDER,
            'operation_id' => $order->id,
            'ratee_user_id' => $this->business->id,
            'outcome' => RatingOutcomeEvent::OUTCOME_FAULT,
        ]);
    }

    public function test_a_ruling_for_the_business_marks_the_client_at_fault(): void
    {
        $order = $this->makeOrder();
        $dispute = $this->openDisputeOn($order);

        app(DisputeService::class)->resolve($dispute, 'release_business', [], (int) $this->business->id);

        $this->assertSame(1, (int) $this->rating((int) $this->client->id, UserOperationRating::ROLE_CLIENT)->fault_count);
        $this->assertSame(0, (int) ($this->rating((int) $this->business->id, UserOperationRating::ROLE_BUSINESS)?->fault_count ?? 0));
    }

    /** An even split names no loser, so nobody is marked at fault. */
    public function test_an_even_split_marks_nobody_at_fault(): void
    {
        $order = $this->makeOrder();
        $dispute = $this->openDisputeOn($order);

        app(DisputeService::class)->resolve(
            $dispute,
            'split',
            ['client_percent' => 50, 'business_percent' => 50],
            (int) $this->business->id
        );

        $this->assertSame(0, (int) ($this->rating((int) $this->business->id, UserOperationRating::ROLE_BUSINESS)?->fault_count ?? 0));
        $this->assertSame(0, (int) ($this->rating((int) $this->client->id, UserOperationRating::ROLE_CLIENT)?->fault_count ?? 0));
        $this->assertDatabaseMissing('rating_outcome_events', [
            'operation_type' => RatingOutcomeEvent::OP_ORDER,
            'operation_id' => $order->id,
            'outcome' => RatingOutcomeEvent::OUTCOME_FAULT,
        ]);
    }

    /** A lopsided split still names the smaller-share side as the loser. */
    public function test_a_lopsided_split_marks_the_smaller_share_at_fault(): void
    {
        $order = $this->makeOrder();
        $dispute = $this->openDisputeOn($order);

        // Client gets 80%, business 20% → business lost.
        app(DisputeService::class)->resolve(
            $dispute,
            'split',
            ['client_percent' => 80, 'business_percent' => 20],
            (int) $this->business->id
        );

        $this->assertSame(1, (int) $this->rating((int) $this->business->id, UserOperationRating::ROLE_BUSINESS)->fault_count);
        $this->assertSame(0, (int) ($this->rating((int) $this->client->id, UserOperationRating::ROLE_CLIENT)?->fault_count ?? 0));
    }

    public function test_fault_appears_in_the_rating_summary(): void
    {
        $order = $this->makeOrder();
        $dispute = $this->openDisputeOn($order);

        app(DisputeService::class)->resolve($dispute, 'refund_client', [], (int) $this->business->id);

        $summary = app(\App\Services\Ratings\RatingService::class)
            ->summaryFor((int) $this->business->id, UserOperationRating::ROLE_BUSINESS);

        $this->assertArrayHasKey('fault_count', $summary);
        $this->assertArrayHasKey('fault_rate', $summary);
        $this->assertSame(1, $summary['fault_count']);
        $this->assertGreaterThan(0.0, $summary['fault_rate']);
    }
}
