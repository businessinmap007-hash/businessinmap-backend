<?php

namespace Tests\Feature;

use App\Models\BookableAllocation;
use App\Models\BookableItem;
use App\Models\Booking;
use App\Models\BusinessPartnership;
use App\Models\PlatformService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * `bookable_allocations` was built end-to-end for AdminV2 (partnership →
 * allocation → auto-generated commercial offer) but the booking ENGINE never
 * knew it existed: a customer booking through the partner couldn't even reach
 * the owner's bookable item (strict business_id match), the contract price
 * never priced the invoice, and quantity_sold/reserved never moved with real
 * bookings. This locks the consumption link: reserve on create, sold on
 * accept, released back to the pool on cancel/reject — and that a sold-out
 * allocation actually blocks a new booking.
 */
class AllocationBookingConsumptionTest extends TestCase
{
    use DatabaseTransactions;

    private const ROOT = 24;
    private const CHILD = 536;
    private const ITEM_TYPE = 'booking_stay';
    private const LINE_OPTION = 973;

    private User $owner;
    private User $partner;
    private User $client;
    private BookableItem $room;
    private BusinessPartnership $partnership;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = uniqid();

        $this->owner = $this->makeBusiness('zz-alloc-owner-' . $suffix);
        $this->partner = $this->makeBusiness('zz-alloc-partner-' . $suffix);

        $this->client = User::create([
            'name' => 'Zz Alloc Client ' . $suffix,
            'email' => "zz-alloc-client-{$suffix}@test.local",
            'phone' => '01' . random_int(100000000, 999999999),
            'password' => Hash::make('Passw0rdTest'),
            'type' => User::TYPE_CLIENT,
            'api_token' => 'zzc' . $suffix . bin2hex(random_bytes(8)),
        ]);

        $this->room = BookableItem::create([
            'business_id' => $this->owner->id,
            'service_id' => $this->serviceId(),
            'item_type' => self::ITEM_TYPE,
            'line_option_id' => self::LINE_OPTION,
            'code' => 'ZZ-ALLOC-' . $suffix,
            'quantity' => 1,
            'is_active' => 1,
        ]);

        // Deliberately NO BusinessServicePrice for this room — the whole point
        // is that the contract price is the pricing authority, not the
        // partner's own (nonexistent) price row.
        $this->partnership = BusinessPartnership::create([
            'owner_business_id' => $this->owner->id,
            'partner_business_id' => $this->partner->id,
            'relationship_type' => BusinessPartnership::TYPE_HOTEL_ALLOTMENT,
            'status' => BusinessPartnership::STATUS_ACTIVE,
        ]);
    }

    private function makeBusiness(string $tag): User
    {
        return User::create([
            'name' => 'Zz ' . $tag,
            'email' => "{$tag}@test.local",
            'phone' => '01' . random_int(100000000, 999999999),
            'password' => Hash::make('Passw0rdTest'),
            'type' => User::TYPE_BUSINESS,
            'category_id' => self::ROOT,
            'category_child_id' => self::CHILD,
            'api_token' => $tag . bin2hex(random_bytes(8)),
        ]);
    }

    private function serviceId(): int
    {
        return (int) PlatformService::query()
            ->where('key', PlatformService::KEY_BOOKING)->where('is_active', 1)->value('id');
    }

    private function makeAllocation(array $overrides = []): BookableAllocation
    {
        return BookableAllocation::create(array_merge([
            'partnership_id' => $this->partnership->id,
            'owner_business_id' => $this->owner->id,
            'partner_business_id' => $this->partner->id,
            'bookable_item_id' => $this->room->id,
            'allocation_type' => BookableAllocation::TYPE_GUARANTEED,
            'quantity_total' => 2,
            'quantity_sold' => 0,
            'quantity_reserved' => 0,
            'quantity_released' => 0,
            'contract_price' => 1000,
            'currency' => 'EGP',
            'markup_type' => 'fixed',
            'markup_value' => 100,
            'status' => BookableAllocation::STATUS_ACTIVE,
        ], $overrides));
    }

    private function bookWindow(): array
    {
        $start = Carbon::today()->addDay()->setTime(14, 0);

        return [
            'starts_at' => $start->toDateTimeString(),
            'ends_at' => $start->copy()->addDays(3)->toDateTimeString(),
        ];
    }

    private function bookAsClient(array $overrides = [])
    {
        return $this->actingAs($this->client, 'sanctum')->postJson('/api/v2/bookings', array_merge([
            'business_id' => $this->partner->id,
            'service_id' => $this->serviceId(),
            'bookable_id' => $this->room->id,
            'quantity' => 1,
        ], $this->bookWindow(), $overrides));
    }

    public function test_a_customer_can_book_the_owners_room_through_the_partner_at_contract_price(): void
    {
        $allocation = $this->makeAllocation();

        // 1000 contract + 100 fixed markup = 1100/night × 3 nights = 3300.
        $response = $this->bookAsClient()->assertCreated();

        $this->assertSame(3300.0, (float) $response->json('data.booking.price'));

        $booking = Booking::query()->latest('id')->firstOrFail();
        $this->assertSame($this->partner->id, (int) $booking->business_id);
        $this->assertSame($this->room->id, (int) $booking->bookable_id);
        $this->assertSame('reserved', data_get($booking->meta, '_allocation.phase'));
        $this->assertSame((int) $allocation->id, (int) data_get($booking->meta, '_allocation.id'));

        $this->assertSame(1, (int) $allocation->fresh()->quantity_reserved);
        $this->assertSame(0, (int) $allocation->fresh()->quantity_sold);
    }

    public function test_accepting_moves_the_unit_from_reserved_to_sold(): void
    {
        $allocation = $this->makeAllocation();
        $bookingId = (int) $this->bookAsClient()->assertCreated()->json('data.booking.id');

        $this->actingAs($this->partner, 'sanctum')
            ->postJson("/api/v2/bookings/{$bookingId}/accept")
            ->assertOk();

        $this->assertSame(0, (int) $allocation->fresh()->quantity_reserved);
        $this->assertSame(1, (int) $allocation->fresh()->quantity_sold);
        $this->assertSame('sold', data_get(Booking::find($bookingId)->meta, '_allocation.phase'));
    }

    public function test_cancelling_an_accepted_booking_releases_the_sold_unit_back(): void
    {
        $allocation = $this->makeAllocation();
        $bookingId = (int) $this->bookAsClient()->assertCreated()->json('data.booking.id');

        $this->actingAs($this->partner, 'sanctum')->postJson("/api/v2/bookings/{$bookingId}/accept")->assertOk();
        $this->actingAs($this->client, 'sanctum')->postJson("/api/v2/bookings/{$bookingId}/cancel")->assertOk();

        $this->assertSame(0, (int) $allocation->fresh()->quantity_sold);
        $this->assertSame(0, (int) $allocation->fresh()->quantity_reserved);
        $this->assertSame(2, $allocation->fresh()->availableQuantity());
        $this->assertSame('released', data_get(Booking::find($bookingId)->meta, '_allocation.phase'));
    }

    public function test_rejecting_a_pending_booking_releases_the_reserved_unit(): void
    {
        $allocation = $this->makeAllocation();
        $bookingId = (int) $this->bookAsClient()->assertCreated()->json('data.booking.id');

        $this->actingAs($this->partner, 'sanctum')->postJson("/api/v2/bookings/{$bookingId}/reject")->assertOk();

        $this->assertSame(0, (int) $allocation->fresh()->quantity_reserved);
        $this->assertSame(2, $allocation->fresh()->availableQuantity());
    }

    public function test_a_sold_out_allocation_blocks_a_new_booking(): void
    {
        $this->makeAllocation(['quantity_total' => 1]);

        $this->bookAsClient()->assertCreated();

        // A second, distinct client — same room, same partner: nothing left.
        $secondClient = User::create([
            'name' => 'Zz Alloc Client 2 ' . uniqid(),
            'email' => 'zz-alloc-client2-' . uniqid() . '@test.local',
            'phone' => '01' . random_int(100000000, 999999999),
            'password' => Hash::make('Passw0rdTest'),
            'type' => User::TYPE_CLIENT,
            'api_token' => 'zzc2' . uniqid() . bin2hex(random_bytes(8)),
        ]);

        $this->actingAs($secondClient, 'sanctum')
            ->postJson('/api/v2/bookings', array_merge([
                'business_id' => $this->partner->id,
                'service_id' => $this->serviceId(),
                'bookable_id' => $this->room->id,
                'quantity' => 1,
            ], $this->bookWindow()))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['bookable_id']);
    }

    public function test_without_an_allocation_the_partner_still_cannot_book_the_owners_room(): void
    {
        // No allocation created in this test — the pre-existing guard must
        // still hold for a partner with no active allocation at all.
        $this->bookAsClient()
            ->assertStatus(422)
            ->assertJsonValidationErrors(['bookable_id']);
    }
}
