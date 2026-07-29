<?php

namespace Tests\Feature;

use App\Models\Dispute;
use App\Models\Order;
use App\Models\RatingOutcomeEvent;
use App\Models\User;
use App\Models\UserOperationRating;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The order counterpart of DisputeApiTest.
 *
 * A booking dispute already fed the rating (OUTCOME_DISPUTED); a menu order had
 * NO door to open a dispute at all, so an order dispute never reached the rating
 * ledger. This walks that new door — open, authorize, and prove the outcome is
 * recorded against both parties.
 */
class OrderDisputeApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $customer;
    private User $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = User::query()->where('type', 'business')->orderBy('id')->first()
            ?: $this->markTestSkipped('Needs a business user.');
        $this->customer = User::query()
            ->where('type', '!=', 'business')
            ->where('id', '!=', $this->business->id)
            ->orderBy('id')->firstOrFail();
    }

    private function makeOrder(string $status = 'pending'): Order
    {
        return Order::create([
            'user_id' => $this->customer->id, 'business_id' => $this->business->id,
            'fulfillment_type' => Order::FULFILLMENT_DELIVERY, 'status' => $status,
            'total' => 40, 'discount' => 0, 'delivery_fee' => 0, 'service_fee' => 0,
            'tax' => 0, 'final_total' => 40, 'payment_method' => 'cash', 'address' => 'x',
        ]);
    }

    private function someoneElse(): User
    {
        return User::query()
            ->whereNotIn('id', [(int) $this->customer->id, (int) $this->business->id])
            ->orderBy('id')->firstOrFail();
    }

    private function disputedCount(int $userId, string $role): int
    {
        return (int) (UserOperationRating::query()
            ->where('user_id', $userId)
            ->where('role', $role)
            ->value('disputed_count') ?? 0);
    }

    public function test_the_customer_can_open_a_dispute_on_their_own_order(): void
    {
        $order = $this->makeOrder();
        Sanctum::actingAs($this->customer);

        $this->postJson("/api/v2/orders/{$order->id}/disputes", [
            'reason_code' => 'not_as_described',
            'reason_text' => 'The dish was not what I ordered.',
        ])
            ->assertOk()
            ->assertJsonPath('data.reason_code', 'not_as_described')
            ->assertJsonPath('data.my_role', 'opener');

        $this->assertDatabaseHas('disputes', [
            'disputeable_type' => Order::class,
            'disputeable_id' => $order->id,
            'opened_by_user_id' => $this->customer->id,
            'against_user_id' => $this->business->id,
        ]);
    }

    /** The whole point: opening records a DISPUTED outcome against both parties. */
    public function test_opening_records_the_disputed_outcome_for_both_parties(): void
    {
        $order = $this->makeOrder();

        $businessBefore = $this->disputedCount((int) $this->business->id, UserOperationRating::ROLE_BUSINESS);
        $clientBefore = $this->disputedCount((int) $this->customer->id, UserOperationRating::ROLE_CLIENT);

        Sanctum::actingAs($this->customer);
        $this->postJson("/api/v2/orders/{$order->id}/disputes", ['reason_code' => 'quality'])->assertOk();

        $this->assertSame(
            $businessBefore + 1,
            $this->disputedCount((int) $this->business->id, UserOperationRating::ROLE_BUSINESS),
            'the business rating logs the dispute'
        );
        $this->assertSame(
            $clientBefore + 1,
            $this->disputedCount((int) $this->customer->id, UserOperationRating::ROLE_CLIENT),
            'the client rating logs the dispute'
        );

        $this->assertDatabaseHas('rating_outcome_events', [
            'operation_type' => RatingOutcomeEvent::OP_ORDER,
            'operation_id' => $order->id,
            'outcome' => RatingOutcomeEvent::OUTCOME_DISPUTED,
        ]);
    }

    /** The business is a party too — the grievance can run either way. */
    public function test_the_business_can_open_a_dispute_on_the_order(): void
    {
        $order = $this->makeOrder();
        Sanctum::actingAs($this->business);

        $this->postJson("/api/v2/orders/{$order->id}/disputes", ['reason_code' => 'no_show'])
            ->assertOk()
            ->assertJsonPath('data.my_role', 'opener');

        $this->assertDatabaseHas('disputes', [
            'disputeable_id' => $order->id,
            'opened_by_user_id' => $this->business->id,
            'against_user_id' => $this->customer->id,
        ]);
    }

    /** A stranger must not be able to freeze anyone into a dispute. */
    public function test_a_stranger_cannot_open_a_dispute_on_someone_elses_order(): void
    {
        $order = $this->makeOrder();
        Sanctum::actingAs($this->someoneElse());

        $this->postJson("/api/v2/orders/{$order->id}/disputes", ['reason_code' => 'quality'])
            ->assertNotFound();

        $this->assertDatabaseMissing('disputes', ['disputeable_id' => $order->id, 'disputeable_type' => Order::class]);
    }

    public function test_opening_requires_authentication(): void
    {
        $order = $this->makeOrder();

        $this->postJson("/api/v2/orders/{$order->id}/disputes", ['reason_code' => 'quality'])
            ->assertUnauthorized();
    }

    /** A draft cart is not an operation anyone can dispute. */
    public function test_a_cart_cannot_be_disputed(): void
    {
        $cart = $this->makeOrder('cart');
        Sanctum::actingAs($this->customer);

        $this->postJson("/api/v2/orders/{$cart->id}/disputes", ['reason_code' => 'quality'])
            ->assertNotFound();
    }

    public function test_an_unknown_reason_code_is_rejected(): void
    {
        $order = $this->makeOrder();
        Sanctum::actingAs($this->customer);

        $this->postJson("/api/v2/orders/{$order->id}/disputes", ['reason_code' => 'because_i_said_so'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason_code');
    }

    /** Opening twice returns the same dispute, and never double-counts the rating. */
    public function test_opening_twice_returns_one_dispute_and_counts_once(): void
    {
        $order = $this->makeOrder();
        $businessBefore = $this->disputedCount((int) $this->business->id, UserOperationRating::ROLE_BUSINESS);

        Sanctum::actingAs($this->customer);
        $first = $this->postJson("/api/v2/orders/{$order->id}/disputes", ['reason_code' => 'late']);
        $second = $this->postJson("/api/v2/orders/{$order->id}/disputes", ['reason_code' => 'quality']);

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(
            1,
            Dispute::query()->where('disputeable_type', Order::class)->where('disputeable_id', $order->id)->count()
        );
        $this->assertSame(
            $businessBefore + 1,
            $this->disputedCount((int) $this->business->id, UserOperationRating::ROLE_BUSINESS),
            'the rating counts the dispute exactly once'
        );
    }
}
