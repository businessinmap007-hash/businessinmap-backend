<?php

namespace Tests\Feature;

use App\Models\BookableItem;
use App\Models\BookableItemRoom;
use App\Models\Booking;
use App\Models\BusinessServicePrice;
use App\Models\Option;
use App\Models\PlatformService;
use App\Models\User;
use App\Services\BookingRoomService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «مواصفات لكل رقم غرفة»: the guest books a room TYPE and may tick a feature the hotel priced («إطلالة على المسبح +150»); the numbered rooms
 * behind the type carry their own features, and when the stay starts the number given is one that carries what was asked.
 */
class RoomNumberFeaturesTest extends TestCase
{
    use DatabaseTransactions;

    private const ROOT = 24;
    private const CHILD = 536;
    private const SINGLE_ROOM = 965;
    private const BREAKFAST = 855;
    private const POOL_VIEW = 854;

    private User $hotel;
    private User $guest;
    private User $guest2;
    private BookableItem $unit;
    private int $serviceId;
    private int $seaView;
    /** @var array<string,BookableItemRoom> */
    private array $rooms = [];
    private \Illuminate\Support\Carbon $start;

    protected function setUp(): void
    {
        parent::setUp();

        $make = fn (string $type, array $extra = []) => User::create([
            'name' => 'Zz RoomFeat ' . uniqid(), 'email' => 'zz-roomfeat-' . uniqid() . '@test.local', 'phone' => '01' . random_int(100000000, 999999999),
            'password' => Hash::make('Passw0rdTest'), 'type' => $type, 'api_token' => 'zz' . uniqid() . bin2hex(random_bytes(8)),
        ] + $extra);

        $this->hotel = $make(User::TYPE_BUSINESS, ['category_id' => self::ROOT, 'category_child_id' => self::CHILD]);
        $this->guest = $make(User::TYPE_CLIENT);
        $this->guest2 = $make(User::TYPE_CLIENT);
        $this->serviceId = (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->value('id');
        $this->start = now()->addDays(40)->startOfDay();

        // another view of the same group as the pool view
        $this->seaView = (int) Option::query()->where('id', '!=', self::POOL_VIEW)->where('group_id', Option::query()->whereKey(self::POOL_VIEW)->value('group_id'))->orderBy('id')->value('id');

        BusinessServicePrice::create([
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'child_id' => self::CHILD,
            'bookable_item_type' => 'booking_stay', 'line_option_id' => self::SINGLE_ROOM, 'price' => 800, 'currency' => 'EGP', 'is_active' => 1,
        ]);

        $this->unit = BookableItem::create([
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'item_type' => 'booking_stay',
            'line_option_id' => self::SINGLE_ROOM, 'code' => 'Z-' . uniqid(), 'quantity' => 3, 'is_active' => 1,
        ]);
        $this->unit->syncOfferingOptions(self::SINGLE_ROOM, []);

        foreach (['101', '102', '103'] as $n) {
            $this->rooms[$n] = BookableItemRoom::create(['bookable_item_id' => $this->unit->id, 'business_id' => $this->hotel->id, 'number' => $n, 'status' => 'available']);
        }

        // the hotel prices a view once for the whole hotel, and a meal plan
        Sanctum::actingAs($this->hotel);
        $this->putJson('/api/v2/business/booking-add-ons', [
            'add_ons' => [['option_id' => self::BREAKFAST, 'enabled' => true, 'value' => 50]],
            'features' => [['option_id' => self::POOL_VIEW, 'enabled' => true, 'value' => 150], ['option_id' => $this->seaView, 'enabled' => true, 'value' => 200]],
        ])->assertOk();
    }

    private function tick(string $number, array $optionIds)
    {
        Sanctum::actingAs($this->hotel);

        return $this->putJson("/api/v2/business/bookable-items/{$this->unit->id}/rooms/{$this->rooms[$number]->id}/features", ['option_ids' => $optionIds]);
    }

    private function book(User $guest, array $options = [], int $days = 2, ?\Illuminate\Support\Carbon $from = null)
    {
        Sanctum::actingAs($guest);
        $from = $from ?: $this->start;

        return $this->postJson('/api/v2/bookings', [
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'bookable_id' => $this->unit->id, 'quantity' => 1, 'option_ids' => $options,
            'starts_at' => $from->toDateTimeString(), 'ends_at' => $from->copy()->addDays($days)->toDateTimeString(),
        ]);
    }

    private function start(Booking $b): string
    {
        return app(BookingRoomService::class)->assignOnStart(Booking::query()->find($b->id))->number;
    }

    public function test_the_hotel_ticks_what_each_numbered_room_carries_and_reads_it_back(): void
    {
        $this->tick('102', [self::POOL_VIEW, 999999999])->assertOk()
            ->assertJsonPath('data.room.number', '102');

        Sanctum::actingAs($this->hotel);
        $rooms = collect($this->getJson("/api/v2/business/bookable-items/{$this->unit->id}/rooms")->assertOk()->json('data.rooms'))->keyBy('number');

        $this->assertSame([self::POOL_VIEW], $rooms['102']['feature_ids'], 'a number outside the vocabulary is dropped');
        $this->assertSame([], $rooms['101']['feature_ids']);
        $this->assertCount(1, $rooms['102']['features']);

        $this->tick('102', [])->assertOk();
        Sanctum::actingAs($this->hotel);
        $this->assertSame([], collect($this->getJson("/api/v2/business/bookable-items/{$this->unit->id}/rooms")->json('data.rooms'))->firstWhere('number', '102')['feature_ids']);
    }

    /** @return array<int,array<string,mixed>> the cards the guest sees for the room type, at the hotel's page */
    private function cards(?string $window = null): array
    {
        Sanctum::actingAs($this->guest);

        return $this->getJson("/api/v2/discovery/units/{$this->hotel->id}" . ($window ? "?{$window}" : ''))->assertOk()->json('data.kinds.0.units');
    }

    public function test_a_view_is_a_card_of_its_own_at_its_all_in_price_and_never_a_tick(): void
    {
        // no room has a view yet: one plain card
        $cards = $this->cards();
        $this->assertCount(1, $cards);
        $this->assertEquals(800, $cards[0]['price']);
        $this->assertArrayNotHasKey('option_ids', $cards[0]);

        $this->tick('102', [self::POOL_VIEW])->assertOk();
        $this->tick('103', [$this->seaView])->assertOk();

        $cards = collect($this->cards())->sortBy('price')->values();
        $this->assertCount(3, $cards, 'plain, pool view and sea view');
        $this->assertEquals([800, 950, 1000], $cards->pluck('price')->map(fn ($p) => (float) $p)->all(), 'the type price + what that kind of room carries: 800 + 150, 800 + 200');
        $this->assertSame([[], [self::POOL_VIEW], [$this->seaView]], $cards->map(fn ($c) => $c['option_ids'] ?? [])->all());
        $this->assertStringContainsString('·', $cards[1]['label'], 'the label says which view');

        // the view is not also offered as a tick; the meal plan still is
        Sanctum::actingAs($this->guest);
        $choices = collect($this->getJson("/api/v2/discovery/units/{$this->hotel->id}")->json('data.kinds.0.choices'))->pluck('option_id')->map(fn ($i) => (int) $i)->all();
        $this->assertNotContains(self::POOL_VIEW, $choices);
        $this->assertNotContains($this->seaView, $choices);
        $this->assertContains(self::BREAKFAST, $choices);

        // nor on the booking form: the extras left there are the meal plan and the add-ons
        $form = collect($this->getJson("/api/v2/bookings/form/{$this->hotel->id}")->assertOk()->json('data.modifiers'))->pluck('option_id')->map(fn ($i) => (int) $i)->all();
        $this->assertNotContains(self::POOL_VIEW, $form);
        $this->assertNotContains($this->seaView, $form);
        $this->assertContains(self::BREAKFAST, $form);
    }

    public function test_booking_a_card_costs_exactly_what_the_card_said_and_a_meal_plan_is_added_per_night(): void
    {
        $this->tick('102', [self::POOL_VIEW])->assertOk();
        $pool = collect($this->cards())->firstWhere('option_ids', [self::POOL_VIEW]);

        $start = $this->start->toDateString();
        $window = 'starts_at=' . $start . '&ends_at=' . $this->start->copy()->addDays(3)->toDateString();
        $pool = collect($this->cards($window))->firstWhere('option_ids', [self::POOL_VIEW]);
        $this->assertEquals(950 * 3, $pool['total']);

        $res = $this->book($this->guest, $pool['option_ids'], 3)->assertCreated();
        $this->assertEquals(950 * 3, (float) Booking::find($res->json('data.booking.id'))->price);

        // «نصف إقامة» added to the same card: the night's price + the plan, per night
        $withPlan = $this->book($this->guest2, array_merge($pool['option_ids'], [self::BREAKFAST]), 3, $this->start->copy()->addDays(10))->assertCreated();
        $this->assertEquals((950 + 50) * 3, (float) Booking::find($withPlan->json('data.booking.id'))->price);
    }

    public function test_the_hotel_sees_the_price_a_room_comes_to_beside_its_features(): void
    {
        Sanctum::actingAs($this->hotel);
        $data = $this->getJson("/api/v2/business/bookable-items/{$this->unit->id}/rooms/{$this->rooms['102']->id}/features")->assertOk()->json('data');

        $this->assertEquals(800, $data['base_price'], 'the night price of the room type, as the hotel wrote it');
        $pool = collect($data['features'])->flatMap(fn ($g) => $g['options'])->firstWhere('id', self::POOL_VIEW);
        $this->assertEquals(150, $pool['price']);
    }

    public function test_a_card_whose_view_is_taken_on_the_dates_says_so(): void
    {
        $this->tick('102', [self::POOL_VIEW])->assertOk();
        $this->book($this->guest, [self::POOL_VIEW])->assertCreated();

        $window = 'starts_at=' . $this->start->toDateString() . '&ends_at=' . $this->start->copy()->addDays(2)->toDateString();
        $pool = collect($this->cards($window))->firstWhere('option_ids', [self::POOL_VIEW]);
        $plain = collect($this->cards($window))->first(fn ($c) => ! isset($c['option_ids']) || $c['option_ids'] === []);

        $this->assertFalse($pool['available'], 'the only room with the pool view is taken those nights');
        $this->assertTrue($plain['available'], 'plain rooms are free');
    }

    public function test_a_view_no_room_can_give_is_refused_at_booking(): void
    {
        $this->tick('102', [self::POOL_VIEW])->assertOk();

        $this->book($this->guest, [$this->seaView])->assertStatus(422)->assertJsonValidationErrors(['option_ids']);
        $this->assertSame(0, Booking::query()->where('bookable_id', $this->unit->id)->count());
    }

    public function test_the_view_is_counted_night_by_night_so_it_is_never_sold_beyond_the_rooms_that_have_it(): void
    {
        $this->tick('102', [self::POOL_VIEW])->assertOk();

        $this->book($this->guest, [self::POOL_VIEW])->assertCreated();
        // the only pool-view room is spoken for on these nights — even though plain rooms are free
        $this->book($this->guest2, [self::POOL_VIEW])->assertStatus(422)->assertJsonValidationErrors(['option_ids']);
        // the next nights are free of it again
        $this->book($this->guest2, [self::POOL_VIEW], 2, $this->start->copy()->addDays(2))->assertCreated();
        // and anyone may take a plain room
        $this->book($this->guest2, [])->assertCreated();
    }

    public function test_the_number_given_at_the_start_carries_what_the_guest_asked_for(): void
    {
        $this->tick('103', [self::POOL_VIEW])->assertOk();

        $plain = Booking::find($this->book($this->guest)->assertCreated()->json('data.booking.id'));
        $plain->update(['status' => 'accepted']);

        $withView = Booking::find($this->book($this->guest2, [self::POOL_VIEW])->assertCreated()->json('data.booking.id'));
        $withView->update(['status' => 'accepted']);

        // the stay that asked for the pool view is given the room that has it, whichever starts first
        $this->assertSame('103', $this->start($withView));
        // and the one that asked for nothing is not given the only room with a view while plain ones are free
        $this->assertSame('101', $this->start($plain));
    }

    public function test_when_no_free_room_carries_the_view_the_start_is_refused_for_the_hotel_to_decide(): void
    {
        $this->tick('103', [self::POOL_VIEW])->assertOk();

        $withView = Booking::find($this->book($this->guest, [self::POOL_VIEW])->assertCreated()->json('data.booking.id'));
        $withView->update(['status' => 'accepted']);

        // a stay the hotel already put in the pool-view room, overlapping
        $other = Booking::create([
            'user_id' => $this->guest2->id, 'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'bookable_type' => BookableItem::class, 'bookable_id' => $this->unit->id,
            'status' => 'accepted', 'date' => $this->start->toDateString(), 'time' => '00:00:00', 'starts_at' => $this->start, 'ends_at' => $this->start->copy()->addDays(2), 'quantity' => 1,
        ]);
        $other->forceFill(['room_id' => $this->rooms['103']->id])->save();

        try {
            $this->start($withView);
            $this->fail('a paid view must not be quietly given away');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('room', $e->errors());
        }

        // the hotel then decides by hand: it can put the stay in any free room
        $this->rooms['103']->update(['status' => 'available']);
        $other->update(['status' => 'cancelled']);
        $this->assertSame('103', app(BookingRoomService::class)->assign(Booking::find($withView->id), $this->rooms['103']->id)->number);
    }

    public function test_a_room_takes_the_guests_its_capacity_says(): void
    {
        $this->unit->update(['capacity' => 1]);

        // a single room, one guest: more is refused with the number it takes
        Sanctum::actingAs($this->guest);
        $body = [
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'bookable_id' => $this->unit->id, 'quantity' => 1,
            'starts_at' => $this->start->toDateTimeString(), 'ends_at' => $this->start->copy()->addDays(2)->toDateTimeString(),
        ];
        $this->postJson('/api/v2/bookings', $body + ['party_size' => 2])->assertStatus(422)->assertJsonValidationErrors(['party_size']);
        $this->postJson('/api/v2/bookings/preview', $body + ['party_size' => 2])->assertStatus(422)->assertJsonValidationErrors(['party_size']);
        $this->assertSame(0, Booking::query()->where('bookable_id', $this->unit->id)->count());

        $this->postJson('/api/v2/bookings', $body + ['party_size' => 1])->assertCreated();

        // two rooms of a double take four; a type that states no capacity limits nothing
        $this->unit->update(['capacity' => 2]);
        Sanctum::actingAs($this->guest2);
        $later = ['starts_at' => $this->start->copy()->addDays(10)->toDateTimeString(), 'ends_at' => $this->start->copy()->addDays(12)->toDateTimeString()] + $body;
        $this->postJson('/api/v2/bookings', ['quantity' => 2, 'party_size' => 5] + $later)->assertStatus(422)->assertJsonValidationErrors(['party_size']);
        $this->postJson('/api/v2/bookings', ['quantity' => 2, 'party_size' => 4] + $later)->assertCreated();
        $this->unit->update(['capacity' => null]);
        $this->assertNull($this->unit->fresh()->guestLimit(3));
    }

    public function test_the_hotel_sees_what_the_guest_asked_for_and_which_free_rooms_match(): void
    {
        $this->tick('102', [self::POOL_VIEW])->assertOk();
        $b = Booking::find($this->book($this->guest, [self::POOL_VIEW])->assertCreated()->json('data.booking.id'));
        $b->update(['status' => 'accepted']);

        Sanctum::actingAs($this->hotel);
        $data = $this->getJson("/api/v2/business/bookings/{$b->id}/rooms")->assertOk()->json('data');

        $this->assertCount(1, $data['requested']);
        $free = collect($data['free'])->keyBy('number');
        $this->assertTrue($free['102']['matches']);
        $this->assertFalse($free['101']['matches']);
        $this->assertSame('102', $data['free'][0]['number'], 'the matching room is listed first');
    }
}
