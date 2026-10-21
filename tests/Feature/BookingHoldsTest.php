<?php

namespace Tests\Feature;

use App\Models\BookableAllocation;
use App\Models\BookableItem;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\BusinessPartnership;
use App\Models\BusinessServicePrice;
use App\Models\PlatformService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «تحت التعامل»: the room is held for the guest who has opened its booking, so the hotel's guest and a travel company's guest are
 * never both offered the last room. The hold turns into the booking, and falls away by itself.
 */
class BookingHoldsTest extends TestCase
{
    use DatabaseTransactions;

    private const ROOT = 24;
    private const CHILD = 536;
    private const SINGLE_ROOM = 965;

    private User $hotel;
    private User $agency;
    private User $first;
    private User $second;
    private BookableItem $unit;
    private int $serviceId;
    private Carbon $start;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = $this->user(User::TYPE_BUSINESS, true);
        $this->agency = $this->user(User::TYPE_BUSINESS);
        $this->first = $this->user(User::TYPE_CLIENT);
        $this->second = $this->user(User::TYPE_CLIENT);
        $this->serviceId = (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->where('is_active', 1)->value('id');
        $this->start = now()->addDays(40)->startOfDay();

        BusinessServicePrice::create([
            'business_id' => $this->hotel->id, 'child_id' => self::CHILD, 'service_id' => $this->serviceId,
            'bookable_item_type' => 'booking_stay', 'line_option_id' => self::SINGLE_ROOM, 'price' => 1000, 'currency' => 'EGP', 'is_active' => 1,
        ]);

        // ONE room of this type: whoever holds it first has it
        $this->unit = BookableItem::create([
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'item_type' => 'booking_stay',
            'line_option_id' => self::SINGLE_ROOM, 'code' => 'Z-' . uniqid(), 'quantity' => 1, 'is_active' => 1,
        ]);

        $p = BusinessPartnership::create(['owner_business_id' => $this->hotel->id, 'partner_business_id' => $this->agency->id, 'relationship_type' => 'agency', 'status' => 'active', 'approval_required' => true, 'created_by' => $this->hotel->id]);
        BookableAllocation::create([
            'partnership_id' => $p->id, 'owner_business_id' => $this->hotel->id, 'partner_business_id' => $this->agency->id,
            'bookable_item_id' => $this->unit->id, 'platform_service_id' => $this->serviceId, 'allocation_type' => 'non_guaranteed',
            'quantity_total' => 1, 'quantity_sold' => 0, 'quantity_reserved' => 0, 'quantity_released' => 0, 'release_days_before' => 0,
            'contract_price' => 800, 'currency' => 'EGP', 'markup_type' => 'none', 'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function user(string $type, bool $hotel = false): User
    {
        return User::create([
            'name' => 'Zz ' . uniqid(), 'email' => 'zz-' . uniqid() . '@test.local', 'phone' => '01' . random_int(100000000, 999999999),
            'password' => Hash::make('Passw0rdTest'), 'type' => $type, 'api_token' => 'zz' . uniqid() . bin2hex(random_bytes(8)),
            'category_id' => $hotel ? self::ROOT : null, 'category_child_id' => $hotel ? self::CHILD : null,
        ]);
    }

    private function body(User $business): array
    {
        return [
            'business_id' => $business->id, 'service_id' => $this->serviceId, 'bookable_id' => $this->unit->id, 'quantity' => 1,
            'starts_at' => $this->start->toDateTimeString(), 'ends_at' => $this->start->copy()->addDays(2)->toDateTimeString(),
        ];
    }

    private function hold(User $who, User $business)
    {
        Sanctum::actingAs($who);

        return $this->postJson('/api/v2/bookings/holds', Arr::only($this->body($business), ['business_id', 'bookable_id', 'starts_at', 'ends_at', 'quantity']));
    }

    private function availability(User $who, User $business): array
    {
        Sanctum::actingAs($who);

        return (array) $this->postJson('/api/v2/bookings/preview', $this->body($business))->assertOk()->json('data.availability');
    }

    public function test_the_first_guest_to_open_the_booking_holds_the_room_and_the_second_is_told_so(): void
    {
        $res = $this->hold($this->first, $this->hotel)->assertCreated();
        $this->assertGreaterThan(500, $res->json('data.hold.seconds_left'));

        $mine = $this->availability($this->first, $this->hotel);
        $this->assertTrue($mine['available'], 'the hold is the holder\'s own: it does not block them');

        $other = $this->availability($this->second, $this->hotel);
        $this->assertFalse($other['available']);
        $this->assertSame('held_by_other', $other['code']);

        // and the second one cannot book it, nor hold it
        Sanctum::actingAs($this->second);
        $this->postJson('/api/v2/bookings', $this->body($this->hotel))->assertStatus(422)->assertJsonValidationErrors(['bookable_id']);
        $this->hold($this->second, $this->hotel)->assertStatus(422);
    }

    public function test_a_travel_company_guest_and_the_hotel_guest_cannot_both_hold_the_last_room(): void
    {
        // the company's guest holds it through the allocation…
        $this->hold($this->first, $this->agency)->assertCreated();

        // …so the hotel's own guest sees it as being dealt with, and so does another guest of the company
        $this->assertSame('held_by_other', $this->availability($this->second, $this->hotel)['code']);
        $this->hold($this->second, $this->hotel)->assertStatus(422);
    }

    public function test_the_hold_becomes_the_booking_and_the_room_stays_taken(): void
    {
        $this->hold($this->first, $this->hotel)->assertCreated();

        Sanctum::actingAs($this->first);
        $this->postJson('/api/v2/bookings', $this->body($this->hotel))->assertCreated();

        $this->assertSame(0, BookingHold::query()->count(), 'the hold has done its job');
        $this->assertSame('booking_conflict', $this->availability($this->second, $this->hotel)['code'], 'now it is booked, not merely being dealt with');
    }

    public function test_a_hold_nobody_finishes_expires_by_itself_and_can_be_released(): void
    {
        $this->hold($this->first, $this->hotel)->assertCreated();
        $this->assertFalse($this->availability($this->second, $this->hotel)['available']);

        Carbon::setTestNow(now()->addMinutes(11));
        $this->assertTrue($this->availability($this->second, $this->hotel)['available'], 'ten minutes on, the room is free again');
        Carbon::setTestNow();

        $id = (int) $this->hold($this->first, $this->hotel)->json('data.hold.id');
        Sanctum::actingAs($this->first);
        $this->deleteJson("/api/v2/bookings/holds/{$id}")->assertOk();
        $this->assertTrue($this->availability($this->second, $this->hotel)['available'], 'the guest left the screen');
    }

    public function test_asking_again_moves_the_hold_instead_of_adding_one_and_a_stranger_cannot_release_it(): void
    {
        $id = (int) $this->hold($this->first, $this->hotel)->json('data.hold.id');
        $this->hold($this->first, $this->hotel)->assertCreated();
        $this->assertSame(1, BookingHold::query()->where('user_id', $this->first->id)->count());

        Sanctum::actingAs($this->second);
        $this->deleteJson("/api/v2/bookings/holds/{$id}");
        $this->assertSame(1, BookingHold::query()->count(), 'a hold is released only by whoever made it');
    }

    public function test_the_room_unit_shows_held_and_the_hotel_board_shows_who_is_dealing(): void
    {
        $this->hold($this->first, $this->agency)->assertCreated();

        Sanctum::actingAs($this->second);
        $u = $this->getJson("/api/v2/discovery/units/{$this->hotel->id}?starts_at=" . urlencode($this->start->toDateTimeString()) . '&ends_at=' . urlencode($this->start->copy()->addDays(2)->toDateTimeString()))->assertOk()->json('data.kinds.0.units.0');
        $this->assertFalse($u['available']);
        $this->assertTrue($u['held'], 'amber «تحت التعامل», not red «محجوز»');

        Sanctum::actingAs($this->hotel);
        $board = $this->getJson('/api/v2/business/occupancy?month=' . $this->start->format('Y-m'))->assertOk()->json('data');
        $unit = collect($board['kinds'])->flatMap(fn ($k) => $k['units'])->firstWhere('id', $this->unit->id);
        $this->assertSame(1, $unit['dealing'][$this->start->day - 1]);
        $this->assertSame($this->agency->name, $unit['dealing_now'][0]['seller']);
        $this->assertSame(0, $unit['booked'][$this->start->day - 1], 'being dealt with is not booked');
    }

    public function test_the_booking_judges_the_room_again_under_a_lock(): void
    {
        // the pre-check passes for both guests (no hold, nothing booked yet) — the second booking must still be refused
        // once the first one is written, which is what the lock-then-recheck inside the transaction guarantees
        Sanctum::actingAs($this->first);
        $this->postJson('/api/v2/bookings', $this->body($this->hotel))->assertCreated();

        Sanctum::actingAs($this->second);
        $this->postJson('/api/v2/bookings', $this->body($this->hotel))->assertStatus(422);
        $this->assertSame(1, Booking::query()->where('bookable_id', $this->unit->id)->count());
    }
}
