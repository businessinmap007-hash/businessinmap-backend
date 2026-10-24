<?php

namespace Tests\Feature;

use App\Models\BookableAllocation;
use App\Models\BookableItem;
use App\Models\BookableItemRoom;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\BusinessPartnership;
use App\Models\BusinessServicePrice;
use App\Models\PlatformService;
use App\Models\User;
use App\Services\BookableAvailabilityService;
use App\Services\BookingRoomService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A hotel and its partner sell the same rooms at the same time out of ONE pool; the hotel keeps a few rooms back for peak; and the clash
 * between two sellers is always over a room NUMBER (1001 of 1001–1020), so «تحت التعامل» names the number and two stays starting at the
 * same moment are never given the same one.
 */
class SharedPoolAndRoomNumbersTest extends TestCase
{
    use DatabaseTransactions;

    private const ROOT = 24;
    private const CHILD = 536;
    private const SINGLE_ROOM = 965;

    private User $hotel;
    private User $agency;
    private array $guests = [];
    private BookableItem $unit;
    private int $serviceId;
    private \Illuminate\Support\Carbon $start;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = $this->user(User::TYPE_BUSINESS, true);
        $this->agency = $this->user(User::TYPE_BUSINESS);

        for ($i = 0; $i < 6; $i++) {
            $this->guests[] = $this->user(User::TYPE_CLIENT);
        }

        $this->serviceId = (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->where('is_active', 1)->value('id');
        $this->start = now()->addDays(40)->startOfDay();

        BusinessServicePrice::create([
            'business_id' => $this->hotel->id, 'child_id' => self::CHILD, 'service_id' => $this->serviceId,
            'bookable_item_type' => 'booking_stay', 'line_option_id' => self::SINGLE_ROOM, 'price' => 1000, 'currency' => 'EGP', 'is_active' => 1,
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

    /** A type of $stock rooms, numbered 1001…; the agency holds a shared (non-guaranteed) allocation of $granted of them. */
    private function hotelWith(int $stock, int $granted, int $reserve = 0, bool $numbered = false, string $type = 'non_guaranteed'): void
    {
        $this->unit = BookableItem::create([
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'item_type' => 'booking_stay',
            'line_option_id' => self::SINGLE_ROOM, 'code' => 'Z-' . uniqid(), 'quantity' => $stock, 'reserve_rooms' => $reserve, 'is_active' => 1,
        ]);

        if ($numbered) {
            for ($n = 0; $n < $stock; $n++) {
                BookableItemRoom::create(['bookable_item_id' => $this->unit->id, 'business_id' => $this->hotel->id, 'number' => (string) (1001 + $n), 'status' => 'available']);
            }
        }

        $p = BusinessPartnership::create(['owner_business_id' => $this->hotel->id, 'partner_business_id' => $this->agency->id, 'relationship_type' => 'agency', 'status' => 'active', 'approval_required' => true, 'created_by' => $this->hotel->id]);
        BookableAllocation::create([
            'partnership_id' => $p->id, 'owner_business_id' => $this->hotel->id, 'partner_business_id' => $this->agency->id,
            'bookable_item_id' => $this->unit->id, 'platform_service_id' => $this->serviceId, 'allocation_type' => $type,
            'quantity_total' => $granted, 'quantity_sold' => 0, 'quantity_reserved' => 0, 'quantity_released' => 0, 'release_days_before' => 0,
            'contract_price' => 800, 'currency' => 'EGP', 'markup_type' => 'none', 'status' => 'active',
        ]);
    }

    private function body(User $business): array
    {
        return [
            'business_id' => $business->id, 'service_id' => $this->serviceId, 'bookable_id' => $this->unit->id, 'quantity' => 1,
            'starts_at' => $this->start->toDateTimeString(), 'ends_at' => $this->start->copy()->addDays(2)->toDateTimeString(),
        ];
    }

    private function book(User $guest, User $business)
    {
        Sanctum::actingAs($guest);

        return $this->postJson('/api/v2/bookings', $this->body($business));
    }

    public function test_a_grant_is_a_shared_pool_unless_the_hotel_says_otherwise(): void
    {
        $this->hotelWith(5, 3);
        $partnership = BusinessPartnership::query()->where('owner_business_id', $this->hotel->id)->first();

        Sanctum::actingAs($this->hotel);
        $res = $this->postJson("/api/v2/business/partnerships/{$partnership->id}/allocations", [
            'bookable_item_id' => $this->unit->id, 'quantity_total' => 1, 'contract_price' => 700,
        ])->assertCreated();

        $this->assertSame('non_guaranteed', $res->json('data.allocation.allocation_type'));
    }

    public function test_the_hotel_and_the_partner_sell_the_same_pool_at_the_same_time(): void
    {
        $this->hotelWith(3, 3);

        // one sale each way, in either order: both are open at once
        $this->book($this->guests[0], $this->hotel)->assertCreated();
        $this->book($this->guests[1], $this->agency)->assertCreated();
        // the third room goes to whoever asks first, from either side
        $this->book($this->guests[2], $this->hotel)->assertCreated();

        // and nothing is left for either of them
        $this->book($this->guests[3], $this->agency)->assertStatus(422);
        $this->book($this->guests[4], $this->hotel)->assertStatus(422);
        $this->assertSame(3, Booking::query()->where('bookable_id', $this->unit->id)->count());
    }

    public function test_the_rooms_the_hotel_keeps_back_are_for_its_own_guests_only(): void
    {
        // 5 rooms, 2 kept back for peak: the partner can sell 3, the hotel's own guests can take all 5
        $this->hotelWith(5, 5, 2);

        foreach ([0, 1, 2] as $i) {
            $this->book($this->guests[$i], $this->agency)->assertCreated();
        }

        $blocked = $this->book($this->guests[3], $this->agency)->assertStatus(422);
        $this->assertStringContainsString('احتياطيًا', (string) $blocked->json('errors.bookable_id.0'));

        // the same two rooms are still on sale to the hotel's own guests
        $this->book($this->guests[3], $this->hotel)->assertCreated();
        $this->book($this->guests[4], $this->hotel)->assertCreated();
        $this->book($this->guests[5], $this->hotel)->assertStatus(422);
    }

    public function test_the_reserve_is_reported_to_the_partners_guest_before_they_try(): void
    {
        $this->hotelWith(2, 2, 2);

        Sanctum::actingAs($this->guests[0]);
        $room = $this->getJson("/api/v2/discovery/units/{$this->agency->id}?starts_at=" . urlencode($this->start->toDateTimeString()) . '&ends_at=' . urlencode($this->start->copy()->addDays(2)->toDateTimeString()))
            ->assertOk()->json('data.partner_rooms.0');

        $this->assertFalse($room['available']);
        $this->assertNotEmpty($room['reason']);
    }

    public function test_the_reserve_can_be_set_from_the_room_type_screen(): void
    {
        $this->hotelWith(4, 2);

        Sanctum::actingAs($this->hotel);
        $this->putJson("/api/v2/business/bookable-items/{$this->unit->id}", [
            'service_id' => $this->serviceId, 'item_type' => 'booking_stay', 'line_option_id' => self::SINGLE_ROOM, 'code' => $this->unit->code, 'quantity' => 4, 'reserve_rooms' => 2,
        ])->assertOk()->assertJsonPath('data.reserve_rooms', 2);

        $this->assertSame(2, (int) $this->unit->fresh()->reserve_rooms);
    }

    public function test_a_hold_names_the_room_number_and_two_holds_never_share_one(): void
    {
        $this->hotelWith(3, 1, 0, true);

        Sanctum::actingAs($this->guests[0]);
        $this->postJson('/api/v2/bookings/holds', Arr::only($this->body($this->hotel), ['business_id', 'bookable_id', 'starts_at', 'ends_at', 'quantity']))->assertCreated();
        Sanctum::actingAs($this->guests[1]);
        $this->postJson('/api/v2/bookings/holds', Arr::only($this->body($this->agency), ['business_id', 'bookable_id', 'starts_at', 'ends_at', 'quantity']))->assertCreated();

        $numbers = BookingHold::query()->orderBy('id')->get()->map(fn ($h) => (string) BookableItemRoom::find($h->room_id)?->number)->all();
        $this->assertSame(['1001', '1002'], $numbers, 'lowest free number first, and the second holder gets another');

        // the hotel sees the numbers on its board; a guest never does
        Sanctum::actingAs($this->hotel);
        $board = $this->getJson('/api/v2/business/occupancy?month=' . $this->start->format('Y-m'))->assertOk()->json('data');
        $unit = collect($board['kinds'])->flatMap(fn ($k) => $k['units'])->firstWhere('id', $this->unit->id);
        $this->assertEqualsCanonicalizing(['1001', '1002'], collect($unit['dealing_now'])->pluck('room')->all());

        Sanctum::actingAs($this->guests[2]);
        $answer = $this->getJson("/api/v2/discovery/units/{$this->hotel->id}?starts_at=" . urlencode($this->start->toDateTimeString()) . '&ends_at=' . urlencode($this->start->copy()->addDays(2)->toDateTimeString()))->assertOk();
        $this->assertStringNotContainsString('1001', $answer->getContent());
    }

    public function test_two_stays_starting_together_are_never_given_the_same_number(): void
    {
        $this->hotelWith(3, 1, 0, true);
        $rooms = app(BookingRoomService::class);

        $stays = [];

        foreach ([0, 1, 2] as $i) {
            $stays[] = Booking::create([
                'user_id' => $this->guests[$i]->id, 'business_id' => $this->hotel->id, 'service_id' => $this->serviceId,
                'bookable_type' => BookableItem::class, 'bookable_id' => $this->unit->id, 'status' => 'accepted',
                'date' => $this->start->toDateString(), 'time' => '00:00:00', 'starts_at' => $this->start, 'ends_at' => $this->start->copy()->addDays(2), 'quantity' => 1,
            ]);
        }

        // each is started from its own copy of the row (as two requests would load it)
        $given = [];

        foreach ($stays as $stay) {
            $given[] = $rooms->assignOnStart(Booking::find($stay->id))->number;
        }

        $this->assertSame(['1001', '1002', '1003'], $given);

        // a fourth overlapping stay finds nothing to hand over
        $extra = Booking::create([
            'user_id' => $this->guests[3]->id, 'business_id' => $this->hotel->id, 'service_id' => $this->serviceId,
            'bookable_type' => BookableItem::class, 'bookable_id' => $this->unit->id, 'status' => 'accepted',
            'date' => $this->start->toDateString(), 'time' => '00:00:00', 'starts_at' => $this->start, 'ends_at' => $this->start->copy()->addDay(), 'quantity' => 1,
        ]);

        $this->expectException(ValidationException::class);
        $rooms->assignOnStart($extra);
    }

    public function test_a_number_another_guest_is_dealing_with_is_given_last(): void
    {
        $this->hotelWith(3, 1, 0, true);

        // a guest is dealing with 1001 right now
        Sanctum::actingAs($this->guests[0]);
        $this->postJson('/api/v2/bookings/holds', Arr::only($this->body($this->hotel), ['business_id', 'bookable_id', 'starts_at', 'ends_at', 'quantity']))->assertCreated();

        $stay = Booking::create([
            'user_id' => $this->guests[1]->id, 'business_id' => $this->hotel->id, 'service_id' => $this->serviceId,
            'bookable_type' => BookableItem::class, 'bookable_id' => $this->unit->id, 'status' => 'accepted',
            'date' => $this->start->toDateString(), 'time' => '00:00:00', 'starts_at' => $this->start, 'ends_at' => $this->start->copy()->addDays(2), 'quantity' => 1,
        ]);

        $this->assertSame('1002', app(BookingRoomService::class)->assignOnStart($stay)->number, 'the number under dealing is spared while another is free');
    }

    public function test_a_batch_answer_equals_the_one_by_one_answer(): void
    {
        $this->hotelWith(2, 1, 1);
        $other = BookableItem::create(['business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'item_type' => 'booking_stay', 'line_option_id' => self::SINGLE_ROOM, 'code' => 'Y-' . uniqid(), 'quantity' => 1, 'is_active' => 1]);
        $this->book($this->guests[0], $this->hotel)->assertCreated();
        BookingHold::create(['bookable_item_id' => $other->id, 'user_id' => $this->guests[1]->id, 'starts_at' => $this->start, 'ends_at' => $this->start->copy()->addDays(2), 'quantity' => 1, 'expires_at' => now()->addMinutes(5)]);

        Sanctum::actingAs($this->guests[2]);
        $service = app(BookableAvailabilityService::class);
        $units = BookableItem::query()->whereIn('id', [$this->unit->id, $other->id])->withCount('rooms')->get();

        $single = $units->mapWithKeys(fn ($u) => [$u->id => Arr::only($service->check($u, $this->start, $this->start->copy()->addDays(2), 1, null, true), ['available', 'code'])])->all();
        $service->preload($units, $this->start, $this->start->copy()->addDays(2));
        $batch = $units->mapWithKeys(fn ($u) => [$u->id => Arr::only($service->check($u, $this->start, $this->start->copy()->addDays(2), 1, null, true), ['available', 'code'])])->all();
        $service->forget();

        $this->assertSame($single, $batch);
    }
}
