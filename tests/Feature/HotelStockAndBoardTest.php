<?php

namespace Tests\Feature;

use App\Models\BookableAllocation;
use App\Models\BookableItem;
use App\Models\BusinessPartnership;
use App\Models\BusinessServicePrice;
use App\Models\PlatformService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A room sold by a travel agency is a room the hotel no longer has: both draw on the same stock, so one room is never sold twice, a
 * stay of three rooms takes three, and rooms the hotel promised a partner are held for it. «شاشة الغرف» shows all of it night by night.
 */
class HotelStockAndBoardTest extends TestCase
{
    use DatabaseTransactions;

    private const ROOT = 24;
    private const CHILD = 536;
    private const SINGLE_ROOM = 965;

    private User $hotel;
    private User $agency;
    private User $guest;
    private User $agencyGuest;
    private BookableItem $unit;
    private BookableAllocation $allocation;
    private int $serviceId;
    private Carbon $monthStart;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = $this->user(User::TYPE_BUSINESS, true);
        $this->agency = $this->user(User::TYPE_BUSINESS);
        $this->guest = $this->user(User::TYPE_CLIENT);
        $this->agencyGuest = $this->user(User::TYPE_CLIENT);
        $this->serviceId = (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->where('is_active', 1)->value('id');
        $this->monthStart = now()->addMonths(2)->startOfMonth();

        BusinessServicePrice::create([
            'business_id' => $this->hotel->id, 'child_id' => self::CHILD, 'service_id' => $this->serviceId,
            'bookable_item_type' => 'booking_stay', 'line_option_id' => self::SINGLE_ROOM, 'price' => 1000, 'currency' => 'EGP', 'is_active' => 1,
        ]);

        // five rooms of one type; the agency is promised two of them
        $this->unit = BookableItem::create([
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'item_type' => 'booking_stay',
            'line_option_id' => self::SINGLE_ROOM, 'code' => 'Z-' . uniqid(), 'quantity' => 5, 'is_active' => 1,
        ]);

        $p = BusinessPartnership::create(['owner_business_id' => $this->hotel->id, 'partner_business_id' => $this->agency->id, 'relationship_type' => 'agency', 'status' => 'active', 'approval_required' => true, 'created_by' => $this->hotel->id]);

        $this->allocation = BookableAllocation::create([
            'partnership_id' => $p->id, 'owner_business_id' => $this->hotel->id, 'partner_business_id' => $this->agency->id,
            'bookable_item_id' => $this->unit->id, 'platform_service_id' => $this->serviceId, 'allocation_type' => 'guaranteed',
            'quantity_total' => 2, 'quantity_sold' => 0, 'quantity_reserved' => 0, 'quantity_released' => 0, 'release_days_before' => 0,
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

    /** Books `$rooms` rooms from day `$from` of the month for `$nights` nights, as `$who`, at `$business`. */
    private function book(User $who, User $business, int $from, int $nights, int $rooms = 1)
    {
        Sanctum::actingAs($who);
        $start = $this->monthStart->copy()->addDays($from - 1);

        return $this->postJson('/api/v2/bookings', [
            'business_id' => $business->id, 'service_id' => $this->serviceId, 'bookable_id' => $this->unit->id, 'quantity' => $rooms,
            'starts_at' => $start->toDateTimeString(), 'ends_at' => $start->copy()->addDays($nights)->toDateTimeString(),
        ]);
    }

    public function test_a_stay_of_several_rooms_takes_that_many(): void
    {
        // five rooms, two promised to the agency: the hotel itself can sell three
        $this->book($this->guest, $this->hotel, 10, 2, 3)->assertCreated();
        $this->book($this->user(User::TYPE_CLIENT), $this->hotel, 10, 2, 1)->assertStatus(422)->assertJsonValidationErrors(['bookable_id']);
    }

    public function test_the_rooms_promised_to_the_agency_are_held_for_it(): void
    {
        $this->book($this->guest, $this->hotel, 10, 2, 3)->assertCreated();

        // the agency still gets what it was promised
        $this->book($this->agencyGuest, $this->agency, 10, 2, 2)->assertCreated();

        // and now every room of that night is taken, whoever sold it
        $this->book($this->user(User::TYPE_CLIENT), $this->hotel, 10, 2, 1)->assertStatus(422);
        $this->book($this->user(User::TYPE_CLIENT), $this->agency, 10, 2, 1)->assertStatus(422);

        // another night is untouched
        $this->book($this->user(User::TYPE_CLIENT), $this->hotel, 20, 1, 1)->assertCreated();
    }

    public function test_what_the_agency_sold_is_gone_from_the_hotel_and_the_board_says_who_sold_it(): void
    {
        $this->book($this->agencyGuest, $this->agency, 10, 2, 1)->assertCreated();
        $this->book($this->guest, $this->hotel, 11, 2, 1)->assertCreated();

        Sanctum::actingAs($this->hotel);
        $board = $this->getJson('/api/v2/business/occupancy?month=' . $this->monthStart->format('Y-m'))->assertOk()->json('data');

        $this->assertSame($this->monthStart->daysInMonth, $board['days']);
        $unit = $board['kinds'][0]['units'][0];
        $this->assertSame(5, $unit['stock']);

        // nights of the 10th, 11th (agency stay + hotel stay overlap on the 11th) and 12th; the check-out day 12 holds the hotel's own
        $this->assertSame(1, $unit['booked'][9]);
        $this->assertSame(2, $unit['booked'][10]);
        $this->assertSame(1, $unit['booked'][11]);
        $this->assertSame(0, $unit['booked'][12]);
        $this->assertSame([1, 1, 0], [$unit['via_partner'][9], $unit['via_partner'][10], $unit['via_partner'][11]]);
        $this->assertSame(1, $unit['held_for_partners'], 'one of the two promised rooms is still unsold');

        $sources = collect($unit['bookings'])->pluck('source')->sort()->values()->all();
        $this->assertSame(['direct', 'partner'], $sources);
        $this->assertSame($this->agency->name, collect($unit['bookings'])->firstWhere('source', 'partner')['partner']);
    }

    public function test_another_business_sees_only_its_own_board(): void
    {
        $this->book($this->agencyGuest, $this->agency, 10, 2, 1)->assertCreated();

        Sanctum::actingAs($this->agency);
        $board = $this->getJson('/api/v2/business/occupancy?month=' . $this->monthStart->format('Y-m'))->assertOk()->json('data');
        $this->assertSame([], $board['kinds'], 'the agency owns no rooms; the hotel\'s board is the hotel\'s');
    }

    public function test_a_cancelled_stay_frees_the_rooms(): void
    {
        $id = (int) $this->book($this->guest, $this->hotel, 10, 2, 3)->assertCreated()->json('data.booking.id') ?: (int) \App\Models\Booking::query()->latest('id')->value('id');
        \App\Models\Booking::whereKey($id)->update(['status' => 'cancelled']);

        $this->book($this->user(User::TYPE_CLIENT), $this->hotel, 10, 2, 3)->assertCreated();
    }
}
