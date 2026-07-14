<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Order;
use App\Models\User;
use App\Models\UserOperationRating;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Subjective star reviews (slice 2). The core rule: a review is only allowed for
 * a real, COMPLETED operation the rater took part in — no rating strangers you
 * never dealt with. Also covers one-per-operation update, the running average,
 * and the read API. Rolls back.
 */
class OperationReviewTest extends TestCase
{
    use DatabaseTransactions;

    private User $client;
    private User $business;
    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = User::query()->where('type', 'business')->orderBy('id')->firstOrFail();
        $this->client = User::query()->where('type', 'client')->orderBy('id')->firstOrFail();
        $this->stranger = User::query()->where('type', 'client')->where('id', '!=', $this->client->id)->orderBy('id')->firstOrFail();
    }

    private function order(string $status = 'completed'): Order
    {
        return Order::create([
            'user_id' => $this->client->id,
            'business_id' => $this->business->id,
            'total' => 100,
            'address' => 'addr',
            'status' => $status,
        ]);
    }

    private function booking(string $status = 'completed'): Booking
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
            'meta' => ['source' => 'review_test'],
        ]);
    }

    public function test_client_can_review_the_business_after_a_completed_order(): void
    {
        $order = $this->order();

        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/v2/ratings/review', [
                'operation_type' => 'order',
                'operation_id' => $order->id,
                'stars' => 5,
                'comment' => 'ممتاز',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.review.ratee_id', (int) $this->business->id)
            ->assertJsonPath('data.review.ratee_role', UserOperationRating::ROLE_BUSINESS)
            ->assertJsonPath('data.review.stars', 5);

        $summary = app(\App\Services\Ratings\RatingService::class)
            ->summaryFor((int) $this->business->id, UserOperationRating::ROLE_BUSINESS);
        $this->assertSame(1, $summary['review_count']);
        $this->assertSame(5.0, $summary['stars_average']);
    }

    public function test_a_stranger_with_no_dealing_cannot_review(): void
    {
        $order = $this->order(); // between client & business, NOT the stranger

        $this->actingAs($this->stranger, 'sanctum')
            ->postJson('/api/v2/ratings/review', [
                'operation_type' => 'order',
                'operation_id' => $order->id,
                'stars' => 1,
                'comment' => 'ضرب سمعة',
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('operation_reviews', [
            'operation_id' => $order->id,
            'rater_id' => $this->stranger->id,
        ]);
    }

    public function test_cannot_review_an_operation_that_is_not_completed(): void
    {
        $order = $this->order('pending');

        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/v2/ratings/review', [
                'operation_type' => 'order',
                'operation_id' => $order->id,
                'stars' => 5,
            ])
            ->assertStatus(409);
    }

    public function test_cannot_review_a_nonexistent_operation(): void
    {
        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/v2/ratings/review', [
                'operation_type' => 'order',
                'operation_id' => 999999999,
                'stars' => 5,
            ])
            ->assertStatus(404);
    }

    public function test_stars_are_validated(): void
    {
        $order = $this->order();

        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/v2/ratings/review', [
                'operation_type' => 'order',
                'operation_id' => $order->id,
                'stars' => 9,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['stars']);
    }

    public function test_second_review_of_the_same_operation_updates_and_does_not_double_count(): void
    {
        $order = $this->order();
        $service = app(\App\Services\Ratings\RatingService::class);

        $this->actingAs($this->client, 'sanctum')->postJson('/api/v2/ratings/review', [
            'operation_type' => 'order', 'operation_id' => $order->id, 'stars' => 4,
        ])->assertStatus(201);

        $this->actingAs($this->client, 'sanctum')->postJson('/api/v2/ratings/review', [
            'operation_type' => 'order', 'operation_id' => $order->id, 'stars' => 2,
        ])->assertStatus(201);

        $summary = $service->summaryFor((int) $this->business->id, UserOperationRating::ROLE_BUSINESS);
        $this->assertSame(1, $summary['review_count'], 'One operation yields at most one review.');
        $this->assertSame(2.0, $summary['stars_average'], 'The average reflects the updated stars.');

        $this->assertDatabaseCount('operation_reviews', OperationReviewTest::reviewRows($order->id));
    }

    private static function reviewRows(int $orderId): int
    {
        return \App\Models\OperationReview::query()->where('operation_id', $orderId)->count();
    }

    public function test_business_can_review_the_client_after_a_completed_booking(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->business, 'sanctum')
            ->postJson('/api/v2/ratings/review', [
                'operation_type' => 'booking',
                'operation_id' => $booking->id,
                'stars' => 4,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.review.ratee_id', (int) $this->client->id)
            ->assertJsonPath('data.review.ratee_role', UserOperationRating::ROLE_CLIENT);
    }

    public function test_reviews_endpoint_lists_received_reviews(): void
    {
        $order = $this->order();
        $this->actingAs($this->client, 'sanctum')->postJson('/api/v2/ratings/review', [
            'operation_type' => 'order', 'operation_id' => $order->id, 'stars' => 5, 'comment' => 'رائع',
        ])->assertStatus(201);

        $this->actingAs($this->client, 'sanctum')
            ->getJson("/api/v2/ratings/user/{$this->business->id}/reviews")
            ->assertOk()
            ->assertJsonPath('data.user_id', (int) $this->business->id)
            ->assertJsonStructure(['data' => ['reviews' => ['data']]]);
    }

    public function test_summary_exposes_the_star_aggregate(): void
    {
        $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/v2/ratings/me')
            ->assertOk()
            ->assertJsonStructure(['data' => ['rating' => ['review_count', 'stars_average']]]);
    }

    public function test_review_requires_authentication(): void
    {
        $this->postJson('/api/v2/ratings/review', [
            'operation_type' => 'order', 'operation_id' => 1, 'stars' => 5,
        ])->assertUnauthorized();
    }
}
