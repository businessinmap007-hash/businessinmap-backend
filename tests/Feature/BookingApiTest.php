<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Gap #1 coverage: the Api\V2\BookingController HTTP layer — party/role
 * authorization, store validation, and the status-machine guards. The deep money
 * mechanics (pricing/deposit/fees) are already covered by the ServiceExecution /
 * BookingDeposit service tests; here we lock the transport surface. Rolls back.
 */
class BookingApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $client;
    private User $business;
    private User $otherClient;
    private User $otherBusiness;
    private int $serviceId;

    protected function setUp(): void
    {
        parent::setUp();

        $businesses = User::query()->where('type', 'business')->orderBy('id')->take(2)->get();
        $this->business = $businesses->first() ?: $this->markTestSkipped('Needs a business user.');
        $this->otherBusiness = $businesses->get(1) ?: $this->markTestSkipped('Needs two business users.');

        $clients = User::query()->where('type', 'client')->orderBy('id')->take(2)->get();
        $this->client = $clients->first() ?: $this->markTestSkipped('Needs a client user.');
        $this->otherClient = $clients->get(1) ?: $this->markTestSkipped('Needs two client users.');

        $this->serviceId = (int) (Booking::query()->value('service_id') ?: 1);
    }

    private function makeBooking(string $status = Booking::STATUS_PENDING, array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $this->client->id,
            'business_id' => $this->business->id,
            'service_id' => $this->serviceId,
            'status' => $status,
            'price' => 100,
            'quantity' => 1,
            'date' => now()->toDateString(),
            'time' => '12:00',
            'starts_at' => now()->addDay(),
            'meta' => ['source' => 'booking_api_test'],
        ], $attributes));
    }

    // ---- listing ---------------------------------------------------------

    public function test_my_scope_lists_only_own_bookings(): void
    {
        $mine = $this->makeBooking();
        $foreign = $this->makeBooking(attributes: ['user_id' => $this->otherClient->id]);

        $ids = collect(
            $this->actingAs($this->client, 'sanctum')
                ->getJson('/api/v2/bookings?scope=my&per_page=100')
                ->assertOk()
                ->json('data.bookings.data')
        )->pluck('id')->all();

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_business_scope_is_blocked_for_clients(): void
    {
        $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/v2/bookings?scope=business')
            ->assertForbidden();
    }

    public function test_business_scope_lists_the_business_queue(): void
    {
        $booking = $this->makeBooking();

        $ids = collect(
            $this->actingAs($this->business, 'sanctum')
                ->getJson('/api/v2/bookings?scope=business&per_page=100')
                ->assertOk()
                ->json('data.bookings.data')
        )->pluck('id')->all();

        $this->assertContains($booking->id, $ids);
    }

    // ---- store -----------------------------------------------------------

    public function test_store_requires_a_client_account(): void
    {
        $this->actingAs($this->business, 'sanctum')
            ->postJson('/api/v2/bookings', [
                'business_id' => $this->business->id,
                'service_id' => $this->serviceId,
            ])
            ->assertForbidden();
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/v2/bookings', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['business_id', 'service_id']);
    }

    public function test_store_rejects_an_unknown_business(): void
    {
        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/v2/bookings', [
                'business_id' => 999999999,
                'service_id' => $this->serviceId,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['business_id']);
    }

    // ---- show / access ---------------------------------------------------

    public function test_show_is_visible_to_a_party(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->client, 'sanctum')
            ->getJson("/api/v2/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.booking.id', $booking->id);
    }

    public function test_show_is_forbidden_for_a_non_party(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->otherClient, 'sanctum')
            ->getJson("/api/v2/bookings/{$booking->id}")
            ->assertForbidden();
    }

    // ---- status machine --------------------------------------------------

    public function test_accept_is_business_only(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->client, 'sanctum')
            ->postJson("/api/v2/bookings/{$booking->id}/accept")
            ->assertForbidden();
    }

    public function test_a_foreign_business_cannot_accept(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->otherBusiness, 'sanctum')
            ->postJson("/api/v2/bookings/{$booking->id}/accept")
            ->assertForbidden();
    }

    public function test_business_accepts_a_pending_booking(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->business, 'sanctum')
            ->postJson("/api/v2/bookings/{$booking->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.booking.status', Booking::STATUS_ACCEPTED);

        $this->assertSame(Booking::STATUS_ACCEPTED, $booking->fresh()->status);
    }

    public function test_a_final_status_booking_cannot_be_changed(): void
    {
        $booking = $this->makeBooking(Booking::STATUS_COMPLETED);

        $this->actingAs($this->client, 'sanctum')
            ->postJson("/api/v2/bookings/{$booking->id}/cancel")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->assertSame(Booking::STATUS_COMPLETED, $booking->fresh()->status);
    }

    // ---- confirmations ---------------------------------------------------

    public function test_client_confirm_is_client_only_and_stamps_meta(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->business, 'sanctum')
            ->postJson("/api/v2/bookings/{$booking->id}/client-confirm")
            ->assertForbidden();

        $this->actingAs($this->client, 'sanctum')
            ->postJson("/api/v2/bookings/{$booking->id}/client-confirm")
            ->assertOk();

        $this->assertTrue((bool) data_get($booking->fresh()->meta, 'confirmations.client.confirmed'));
    }

    public function test_business_confirm_is_business_only(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->client, 'sanctum')
            ->postJson("/api/v2/bookings/{$booking->id}/business-confirm")
            ->assertForbidden();
    }

    public function test_financial_preview_is_party_only(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->otherClient, 'sanctum')
            ->getJson("/api/v2/bookings/{$booking->id}/financial-preview")
            ->assertForbidden();
    }

    public function test_bookings_require_authentication(): void
    {
        $this->getJson('/api/v2/bookings')->assertUnauthorized();
    }
}
