<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Order;
use App\Models\User;
use App\Models\UserOperationRating;
use App\Services\Integrations\BookingGuaranteeIntegration;
use App\Services\Ratings\RatingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * End-to-end wiring for the operation-based rating: booking completion / cancel /
 * dispute and order cancellation feed both parties' counts, and the read API
 * surfaces the derived percentages. Rolls back.
 */
class RatingWiringTest extends TestCase
{
    use DatabaseTransactions;

    private User $client;
    private User $business;
    private RatingService $ratings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = User::query()->where('type', 'business')->orderBy('id')->firstOrFail();
        $this->client = User::query()->where('type', 'client')->orderBy('id')->firstOrFail();
        $this->ratings = app(RatingService::class);
    }

    private function booking(string $status = Booking::STATUS_PENDING): Booking
    {
        return Booking::create([
            'user_id' => $this->client->id,
            'business_id' => $this->business->id,
            'service_id' => (int) (Booking::query()->value('service_id') ?: 1),
            'status' => $status,
            'price' => 100,
            'quantity' => 1,
            'date' => now()->toDateString(),
            'time' => '12:00',
            'starts_at' => now()->addDay(),
            'meta' => ['source' => 'rating_wiring_test'],
        ]);
    }

    private function clientSuccess(): int
    {
        return $this->ratings->summaryFor((int) $this->client->id, UserOperationRating::ROLE_CLIENT)['success_count'];
    }

    private function businessSuccess(): int
    {
        return $this->ratings->summaryFor((int) $this->business->id, UserOperationRating::ROLE_BUSINESS)['success_count'];
    }

    public function test_booking_completion_records_success_for_both_parties(): void
    {
        $booking = $this->booking(Booking::STATUS_IN_PROGRESS);
        $integration = app(BookingGuaranteeIntegration::class);

        $clientBefore = $this->clientSuccess();
        $businessBefore = $this->businessSuccess();

        $integration->recordCompleted($booking);

        $this->assertSame($clientBefore + 1, $this->clientSuccess());
        $this->assertSame($businessBefore + 1, $this->businessSuccess());
    }

    public function test_booking_completion_rating_is_idempotent(): void
    {
        $booking = $this->booking(Booking::STATUS_IN_PROGRESS);
        $integration = app(BookingGuaranteeIntegration::class);

        $before = $this->clientSuccess();

        $integration->recordCompleted($booking);
        $integration->recordCompleted($booking->fresh()); // repeat

        $this->assertSame($before + 1, $this->clientSuccess(), 'A booking must only count once.');
    }

    public function test_booking_cancellation_records_a_cancel(): void
    {
        $booking = $this->booking();
        $before = $this->ratings->summaryFor((int) $this->client->id, UserOperationRating::ROLE_CLIENT)['cancelled_count'];

        app(BookingGuaranteeIntegration::class)->recordCancelled($booking);

        $after = $this->ratings->summaryFor((int) $this->client->id, UserOperationRating::ROLE_CLIENT)['cancelled_count'];
        $this->assertSame($before + 1, $after);
    }

    public function test_booking_dispute_records_a_dispute(): void
    {
        $booking = $this->booking(Booking::STATUS_IN_PROGRESS);
        $before = $this->ratings->summaryFor((int) $this->business->id, UserOperationRating::ROLE_BUSINESS)['disputed_count'];

        app(BookingGuaranteeIntegration::class)->recordDisputeOpened($booking);

        $after = $this->ratings->summaryFor((int) $this->business->id, UserOperationRating::ROLE_BUSINESS)['disputed_count'];
        $this->assertSame($before + 1, $after);
    }

    public function test_order_cancel_via_api_records_a_cancel_for_both_parties(): void
    {
        $order = Order::create([
            'user_id' => $this->client->id,
            'business_id' => $this->business->id,
            'total' => 120,
            'address' => 'Test address',
            'status' => 'pending',
        ]);

        $clientBefore = $this->ratings->summaryFor((int) $this->client->id, UserOperationRating::ROLE_CLIENT)['cancelled_count'];
        $businessBefore = $this->ratings->summaryFor((int) $this->business->id, UserOperationRating::ROLE_BUSINESS)['cancelled_count'];

        $this->actingAs($this->client, 'sanctum')
            ->postJson("/api/v2/orders/{$order->id}/cancel")
            ->assertOk();

        $clientAfter = $this->ratings->summaryFor((int) $this->client->id, UserOperationRating::ROLE_CLIENT)['cancelled_count'];
        $businessAfter = $this->ratings->summaryFor((int) $this->business->id, UserOperationRating::ROLE_BUSINESS)['cancelled_count'];

        $this->assertSame($clientBefore + 1, $clientAfter);
        $this->assertSame($businessBefore + 1, $businessAfter);
    }

    public function test_ratings_me_endpoint_returns_a_summary(): void
    {
        $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/v2/ratings/me')
            ->assertOk()
            ->assertJsonPath('data.user_id', (int) $this->client->id)
            ->assertJsonStructure([
                'data' => ['rating' => ['role', 'total_operations', 'success_rate', 'cancel_rate', 'dispute_rate']],
            ]);
    }

    public function test_ratings_user_endpoint_returns_a_summary(): void
    {
        $this->actingAs($this->client, 'sanctum')
            ->getJson("/api/v2/ratings/user/{$this->business->id}")
            ->assertOk()
            ->assertJsonPath('data.user_id', (int) $this->business->id)
            ->assertJsonPath('data.rating.role', UserOperationRating::ROLE_BUSINESS);
    }

    public function test_ratings_user_endpoint_404_for_unknown_user(): void
    {
        $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/v2/ratings/user/999999999')
            ->assertNotFound();
    }

    public function test_ratings_require_authentication(): void
    {
        $this->getJson('/api/v2/ratings/me')->assertUnauthorized();
    }
}
