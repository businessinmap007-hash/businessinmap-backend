<?php

namespace Tests\Feature;

use App\Models\BookableAllocation;
use App\Models\BookableItem;
use App\Models\Booking;
use App\Models\BusinessPartnership;
use App\Models\BusinessServicePrice;
use App\Models\PlatformService;
use App\Models\User;
use App\Services\Partnerships\AllocationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * An allotment is ROOMS PER NIGHT, the way the trade writes it («٥ غرف كل ليلة من ١ ديسمبر إلى ٣١»): a room sold for two nights is free
 * again on the third, a cancelled stay frees its nights, the allocation's dates are the dates of the STAY, and what was already sold
 * stands when the allocation ends or is stopped.
 */
class AllocationPerNightTest extends TestCase
{
    use DatabaseTransactions;

    private const ROOT = 24;
    private const CHILD = 536;
    private const SINGLE_ROOM = 965;

    private User $hotel;
    private User $agency;
    private BookableItem $unit;
    private BookableAllocation $allocation;
    private int $serviceId;
    private Carbon $d;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = $this->user(User::TYPE_BUSINESS, true);
        $this->agency = $this->user(User::TYPE_BUSINESS);
        $this->serviceId = (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->where('is_active', 1)->value('id');
        $this->d = now()->addDays(60)->startOfDay();

        BusinessServicePrice::create([
            'business_id' => $this->hotel->id, 'child_id' => self::CHILD, 'service_id' => $this->serviceId,
            'bookable_item_type' => 'booking_stay', 'line_option_id' => self::SINGLE_ROOM, 'price' => 1000, 'currency' => 'EGP', 'is_active' => 1,
        ]);

        $this->unit = BookableItem::create([
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'item_type' => 'booking_stay',
            'line_option_id' => self::SINGLE_ROOM, 'code' => 'Z-' . uniqid(), 'quantity' => 10, 'is_active' => 1,
        ]);

        $p = BusinessPartnership::create(['owner_business_id' => $this->hotel->id, 'partner_business_id' => $this->agency->id, 'relationship_type' => 'agency', 'status' => 'active', 'approval_required' => true, 'created_by' => $this->hotel->id]);

        // one room per night, for stays from the 60th day to the 80th
        $this->allocation = BookableAllocation::create([
            'partnership_id' => $p->id, 'owner_business_id' => $this->hotel->id, 'partner_business_id' => $this->agency->id,
            'bookable_item_id' => $this->unit->id, 'platform_service_id' => $this->serviceId, 'allocation_type' => 'non_guaranteed',
            'starts_at' => $this->d->copy(), 'ends_at' => $this->d->copy()->addDays(20),
            'quantity_total' => 1, 'quantity_sold' => 0, 'quantity_reserved' => 0, 'quantity_released' => 0, 'release_days_before' => 0,
            'contract_price' => 800, 'currency' => 'EGP', 'markup_type' => 'none', 'status' => 'active',
        ]);
    }

    private function user(string $type, bool $hotel = false): User
    {
        return User::create([
            'name' => 'Zz ' . uniqid(), 'email' => 'zz-' . uniqid() . '@test.local', 'phone' => '01' . random_int(100000000, 999999999),
            'password' => Hash::make('Passw0rdTest'), 'type' => $type, 'api_token' => 'zz' . uniqid() . bin2hex(random_bytes(8)),
            'category_id' => $hotel ? self::ROOT : null, 'category_child_id' => $hotel ? self::CHILD : null,
        ]);
    }

    /** A guest of the agency books `$rooms` rooms from night `$from` (days after the allocation starts) for `$nights`. */
    private function book(int $from, int $nights, int $rooms = 1, ?User $who = null)
    {
        Sanctum::actingAs($who ?? $this->user(User::TYPE_CLIENT));
        $start = $this->d->copy()->addDays($from);

        return $this->postJson('/api/v2/bookings', [
            'business_id' => $this->agency->id, 'service_id' => $this->serviceId, 'bookable_id' => $this->unit->id, 'quantity' => $rooms,
            'starts_at' => $start->toDateTimeString(), 'ends_at' => $start->copy()->addDays($nights)->toDateTimeString(),
        ]);
    }

    public function test_a_room_sold_for_two_nights_is_free_again_on_the_third(): void
    {
        $this->book(0, 2)->assertCreated();

        $this->book(0, 2)->assertStatus(422);
        $this->book(1, 2)->assertStatus(422); // night 1 is still taken
        $this->book(2, 2)->assertCreated();   // from night 2 it is free again
        $this->book(4, 3)->assertCreated();
        $this->book(10, 1)->assertCreated();  // and a stay long after counts nothing against the first
    }

    public function test_the_busiest_night_of_a_stay_decides_what_is_left(): void
    {
        $this->allocation->update(['quantity_total' => 2]);

        $this->book(0, 4)->assertCreated();   // nights 0-3: one room
        $this->book(1, 2)->assertCreated();   // nights 1-2: two rooms

        $this->book(1, 1)->assertStatus(422); // night 1 is full
        $this->book(0, 1)->assertCreated();   // night 0 has one left
        $this->assertSame(1, $this->allocation->fresh()->availableFor($this->d->copy()->addDays(3), $this->d->copy()->addDays(4)));
        $this->assertSame(0, $this->allocation->fresh()->availableFor($this->d->copy()->addDays(0), $this->d->copy()->addDays(4)), 'a stay over the busy nights has none');
    }

    public function test_a_cancelled_stay_gives_its_nights_back(): void
    {
        $id = (int) $this->book(0, 2)->assertCreated()->json('data.booking.id') ?: (int) Booking::query()->latest('id')->value('id');
        $this->book(0, 2)->assertStatus(422);

        Booking::whereKey($id)->update(['status' => 'cancelled']);

        $this->book(0, 2)->assertCreated();
    }

    public function test_the_allocations_dates_are_the_dates_of_the_stay(): void
    {
        // before the window, and running past its last night
        $this->book(-3, 2)->assertStatus(422);
        $this->book(19, 3)->assertStatus(422);

        // the last night of the window is the last night that may be sold; and it is on sale long before it starts
        $this->book(19, 2)->assertCreated();
    }

    public function test_what_was_already_sold_stands_when_the_allocation_is_stopped_and_both_sides_are_told(): void
    {
        $this->book(0, 2)->assertCreated();
        $this->assertSame(1, app(AllocationService::class)->standing($this->allocation)->count());

        Sanctum::actingAs($this->hotel);
        $this->postJson("/api/v2/business/partnerships/{$this->allocation->partnership_id}/allocations/{$this->allocation->id}/stop")->assertOk();

        // nothing is cancelled …
        $this->assertSame(1, Booking::query()->where('allocation_id', $this->allocation->id)->where('status', '!=', 'cancelled')->count());
        // … both sides are told it stands …
        foreach ([$this->hotel, $this->agency] as $party) {
            $this->assertDatabaseHas('app_notifications', ['user_id' => $party->id, 'notifiable_type' => BookableAllocation::class, 'notifiable_id' => $this->allocation->id]);
        }
        // … they read it on the allocation …
        $card = $this->getJson("/api/v2/business/partnerships/{$this->allocation->partnership_id}")->assertOk()->json('data.allocations.0');
        $this->assertSame(1, $card['upcoming_bookings']);
        $this->assertSame(1, $card['pending_bookings'], 'the hotel still has to answer it');
        // … and nothing new is sold from it
        $this->book(4, 1)->assertStatus(422);
    }

    public function test_an_allocation_whose_last_night_has_passed_ends_and_tells_what_stands_on_it(): void
    {
        $this->book(0, 2)->assertCreated();

        // the dates slip into the past while the stay is still ahead (a stay may run to the day after the last night)
        $this->allocation->update(['ends_at' => now()->subDays(2)]);

        $this->assertSame(1, app(AllocationService::class)->expireEnded());
        $this->assertSame(BookableAllocation::STATUS_EXPIRED, $this->allocation->fresh()->status);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $this->agency->id, 'notifiable_type' => BookableAllocation::class, 'notifiable_id' => $this->allocation->id]);
        $this->assertSame(1, Booking::query()->where('allocation_id', $this->allocation->id)->count(), 'the stay is not taken back');
    }

    public function test_the_partnership_ending_stops_the_selling_but_not_what_was_sold(): void
    {
        $this->book(0, 2)->assertCreated();

        Sanctum::actingAs($this->agency);
        $this->postJson("/api/v2/business/partnerships/{$this->allocation->partnership_id}/end")->assertOk();

        $this->assertSame(BookableAllocation::STATUS_STOPPED, $this->allocation->fresh()->status);
        $this->assertSame(1, Booking::query()->where('allocation_id', $this->allocation->id)->count());
        $this->assertDatabaseHas('app_notifications', ['user_id' => $this->hotel->id, 'notifiable_type' => BookableAllocation::class, 'notifiable_id' => $this->allocation->id]);
    }
}
