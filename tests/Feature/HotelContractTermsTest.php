<?php

namespace Tests\Feature;

use App\Models\BookableItem;
use App\Models\Booking;
use App\Models\BusinessServicePrice;
use App\Models\OfferingOption;
use App\Models\PlatformService;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The rest of a hotel's rate sheet (owner, 2026-10-28 «نفذ النقاط الناقصة كلها»): the single rate, the third adult's reduction, the
 * non-refundable rate, the seasons (rooms, meal plans, shortest stay) and the baby cots.
 */
class HotelContractTermsTest extends TestCase
{
    use DatabaseTransactions;

    private const ROOT = 24;
    private const CHILD = 536;
    private const SINGLE_ROOM = 965;
    private const BREAKFAST = 855;

    private User $hotel;
    private User $guest;
    private BookableItem $unit;
    private int $serviceId;
    private \Illuminate\Support\Carbon $start;

    protected function setUp(): void
    {
        parent::setUp();

        $make = fn (string $type, array $extra = []) => User::create([
            'name' => 'Zz Terms ' . uniqid(), 'email' => 'zz-terms-' . uniqid() . '@test.local', 'phone' => '01' . random_int(100000000, 999999999),
            'password' => Hash::make('Passw0rdTest'), 'type' => $type, 'api_token' => 'zz' . uniqid() . bin2hex(random_bytes(8)),
        ] + $extra);

        $this->hotel = $make(User::TYPE_BUSINESS, ['category_id' => self::ROOT, 'category_child_id' => self::CHILD]);
        $this->guest = $make(User::TYPE_CLIENT);
        $this->serviceId = (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->value('id');
        $this->start = now()->addDays(40)->startOfDay();

        BusinessServicePrice::create([
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'child_id' => self::CHILD,
            'bookable_item_type' => 'booking_stay', 'line_option_id' => self::SINGLE_ROOM, 'price' => 1000, 'currency' => 'EGP', 'is_active' => 1,
        ]);
        // a room for two at 1000 a night (500 a person)
        $this->unit = BookableItem::create([
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'item_type' => 'booking_stay',
            'line_option_id' => self::SINGLE_ROOM, 'code' => 'T-' . uniqid(), 'quantity' => 3, 'capacity' => 2, 'is_active' => 1,
        ]);
        $this->unit->syncOfferingOptions(self::SINGLE_ROOM, []);

        OfferingOption::create([
            'offering_type' => (new User)->getMorphClass(), 'offering_id' => $this->hotel->id, 'option_id' => self::BREAKFAST,
            'role' => OfferingOption::ROLE_MODIFIER, 'adjust_type' => OfferingOption::ADJUST_AMOUNT, 'adjust_value' => 100, 'per_person' => true, 'sort_order' => 1,
        ]);
    }

    private function body(int $fromDay, int $nights, array $extra = []): array
    {
        return $extra + [
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'bookable_id' => $this->unit->id, 'quantity' => 1,
            'starts_at' => $this->start->copy()->addDays($fromDay)->toDateTimeString(),
            'ends_at' => $this->start->copy()->addDays($fromDay + $nights)->toDateTimeString(),
        ];
    }

    private function preview(int $fromDay, int $nights, array $extra = [])
    {
        Sanctum::actingAs($this->guest);

        return $this->postJson('/api/v2/bookings/preview', $this->body($fromDay, $nights, $extra));
    }

    private function price(int $fromDay, int $nights, array $extra = []): float
    {
        return (float) $this->preview($fromDay, $nights, $extra)->assertOk()->json('data.price');
    }

    private function policy(array $body)
    {
        Sanctum::actingAs($this->hotel);

        return $this->putJson('/api/v2/business/child-policy', $body + [
            'adult_from_age' => 12, 'counts_from_age' => 6, 'meals_follow' => true,
            'bands' => [['from' => 0, 'to' => 5, 'percent' => 0], ['from' => 6, 'to' => 11, 'percent' => 50]],
        ]);
    }

    public function test_one_adult_alone_pays_the_single_rate_of_the_room(): void
    {
        $this->unit->update(['single_occupancy_percent' => 80]);

        // one adult with breakfast: the room 1000 at 80% = 800, + his breakfast 100 — two nights
        $res = $this->preview(0, 2, ['party_size' => 1, 'option_ids' => [self::BREAKFAST]])->assertOk();
        $this->assertEquals((800 + 100) * 2, $res->json('data.price'));
        $this->assertEquals(200, $res->json('data.price_breakdown.single_occupancy.per_night'));

        // two adults pay the room; one adult with a child of 8 shares it; with a baby of 1 he is still alone
        $this->assertEquals(1000 * 2, $this->price(0, 2, ['party_size' => 2]));
        $this->assertEquals(1000 * 2, $this->price(0, 2, ['party_size' => 1, 'children_ages' => [8]]));
        $this->assertEquals(800 * 2, $this->price(0, 2, ['party_size' => 1, 'children_ages' => [1]]));

        // two rooms for three adults: one room has one adult
        $this->assertEquals((1000 + 800) * 2, $this->price(0, 2, ['party_size' => 3, 'quantity' => 2]));

        // the room type carries it to the app
        Sanctum::actingAs($this->hotel);
        $this->getJson("/api/v2/business/bookable-items/{$this->unit->id}")->assertOk()->assertJsonPath('data.single_occupancy_percent', 80);
    }

    public function test_a_third_adult_pays_one_persons_night_less_the_hotels_reduction(): void
    {
        // «تخفيض الشخص الثالث 30%»: no fixed bed price — the adult in the extra bed pays 500 less 30% = 350 a night
        $this->policy(['third_adult_reduction' => 30, 'extra_beds_max' => 1])->assertOk()->assertJsonPath('data.policy.third_adult_reduction', 30);

        $res = $this->preview(0, 2, ['party_size' => 3])->assertOk();
        $this->assertEquals((1000 + 350) * 2, $res->json('data.price'));
        $this->assertEquals(30, $res->json('data.price_breakdown.extra_beds.third_adult_reduction'));

        // a fourth adult still needs a second bed the room does not take
        $this->preview(0, 2, ['party_size' => 4])->assertStatus(422)->assertJsonValidationErrors(['party_size']);
    }

    public function test_the_non_refundable_rate_is_cheaper_and_its_dates_cannot_move(): void
    {
        // not sold yet
        $this->preview(0, 2, ['party_size' => 2, 'rate_plan' => 'non_refundable'])->assertStatus(422)->assertJsonValidationErrors(['rate_plan']);

        Sanctum::actingAs($this->hotel);
        $this->putJson('/api/v2/business/booking-settings/rate-plans', ['non_refundable_percent' => 10])->assertOk()->assertJsonPath('data.non_refundable_percent', 10);
        $this->putJson('/api/v2/business/booking-settings/rate-plans', ['non_refundable_percent' => 80])->assertStatus(422);

        $this->assertEquals(2000, $this->price(0, 2, ['party_size' => 2]));
        $this->assertEquals(1800, $this->price(0, 2, ['party_size' => 2, 'rate_plan' => 'non_refundable']));

        $id = $this->postJson('/api/v2/bookings', $this->body(0, 2, ['party_size' => 2, 'rate_plan' => 'non_refundable']))->assertCreated()->json('data.booking.id');
        $this->assertEquals(1800, (float) Booking::find($id)->price);
        $this->getJson("/api/v2/bookings/{$id}")->assertOk()->assertJsonPath('data.stay_terms.rate_plan', 'non_refundable');

        $this->postJson("/api/v2/bookings/{$id}/reschedule", ['starts_at' => $this->start->copy()->addDays(5)->toDateTimeString(), 'ends_at' => $this->start->copy()->addDays(7)->toDateTimeString()])
            ->assertStatus(422)->assertJsonValidationErrors(['starts_at']);

        // the guest reads the hotel's discount with the rooms
        $this->getJson("/api/v2/discovery/units/{$this->hotel->id}")->assertOk()->assertJsonPath('data.non_refundable_percent', 10);
    }

    public function test_a_season_prices_the_rooms_and_the_meals_and_names_the_shortest_stay(): void
    {
        Sanctum::actingAs($this->hotel);
        // two nights of high season: rooms +20%, breakfast 150, at least three nights for an arrival in it
        $high = $this->postJson('/api/v2/business/seasons', [
            'name' => 'عيد الأضحى', 'start_date' => $this->start->toDateString(), 'end_date' => $this->start->copy()->addDay()->toDateString(),
            'room_change_type' => 'percent', 'room_change_value' => 20, 'meal_prices' => [self::BREAKFAST => 150], 'min_nights' => 3,
        ])->assertCreated()->json('data.season');
        $this->assertSame(3, $high['min_nights']);

        // the form reads the room types and the meal plans the hotel sells
        $this->getJson('/api/v2/business/seasons')->assertOk()
            ->assertJsonPath('data.room_types.0.id', $this->unit->id)
            ->assertJsonPath('data.meal_plans.0.option_id', self::BREAKFAST);

        // seasons never overlap; a price for another hotel's room is refused
        $this->postJson('/api/v2/business/seasons', ['name' => 'x', 'start_date' => $this->start->copy()->addDay()->toDateString(), 'end_date' => $this->start->copy()->addDays(3)->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors(['start_date']);
        $this->postJson('/api/v2/business/seasons', ['name' => 'x', 'start_date' => $this->start->copy()->addDays(30)->toDateString(), 'end_date' => $this->start->copy()->addDays(31)->toDateString(), 'room_prices' => [999999 => 5]])
            ->assertStatus(422)->assertJsonValidationErrors(['room_prices']);

        // an arrival in it for two nights is too short
        $this->preview(0, 2, ['party_size' => 1, 'option_ids' => [self::BREAKFAST]])->assertStatus(422)->assertJsonValidationErrors(['ends_at']);

        // three nights: two in the season (1200 + 150), one after it (1000 + 100)
        $this->assertEquals((1200 + 150) * 2 + 1100, $this->price(0, 3, ['party_size' => 1, 'option_ids' => [self::BREAKFAST]]));

        // a season with a night price for this room type
        Sanctum::actingAs($this->hotel);
        $this->postJson('/api/v2/business/seasons', [
            'name' => 'الصيف', 'start_date' => $this->start->copy()->addDays(10)->toDateString(), 'end_date' => $this->start->copy()->addDays(20)->toDateString(),
            'room_change_type' => 'amount', 'room_change_value' => 100, 'room_prices' => [$this->unit->id => 1500],
        ])->assertCreated();
        $this->assertEquals(1500 * 2, $this->price(10, 2, ['party_size' => 2]));

        // a child's share follows the season's night (750 a person, half = 375) and its breakfast (150, half = 75)
        $this->policy([])->assertOk();
        $res = $this->preview(0, 3, ['party_size' => 1, 'children_ages' => [8], 'option_ids' => [self::BREAKFAST]])->assertOk();
        $this->assertEquals((300 + 75) * 2 + (250 + 50), $res->json('data.price_breakdown.children_total'));

        // removing it gives the nights their price back
        Sanctum::actingAs($this->hotel);
        $this->deleteJson("/api/v2/business/seasons/{$high['id']}")->assertOk();
        $this->assertEquals(2000, $this->price(0, 2, ['party_size' => 2]));
    }

    public function test_a_non_refundable_stay_owes_its_whole_price_in_advance_before_check_in(): void
    {
        Sanctum::actingAs($this->hotel);
        $this->putJson('/api/v2/business/booking-settings/rate-plans', ['non_refundable_percent' => 10])->assertOk();

        Sanctum::actingAs($this->guest);
        $id = $this->postJson('/api/v2/bookings', $this->body(0, 2, ['party_size' => 2, 'rate_plan' => 'non_refundable']))->assertCreated()->json('data.booking.id');

        // the hotel accepts it: the whole 1800 is owed outside the app, as a prepayment
        $booking = Booking::find($id);
        $meta = (array) $booking->meta;
        $meta['_start_confirm'] = ['client' => true, 'business' => true];
        $booking->forceFill(['status' => Booking::STATUS_ACCEPTED, 'meta' => $meta])->save();

        Sanctum::actingAs($this->guest);
        $this->getJson("/api/v2/bookings/{$id}")->assertOk()
            ->assertJsonPath('data.booking.external_deposit.kind', 'prepayment')
            ->assertJsonPath('data.booking.external_deposit.status', 'pending')
            ->assertJsonPath('data.booking.external_deposit.amount', 1800);

        // no check-in before it arrived
        try {
            app(\App\Services\ServiceExecutionEngine::class)->moveBookingToInProgress($booking->fresh());
            $this->fail('checked in with the prepayment outstanding');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('external_deposit', $e->errors());
        }

        Sanctum::actingAs($this->hotel);
        $this->postJson("/api/v2/business/bookings/{$id}/deposit/confirm-received", ['method' => 'cash'])->assertOk()
            ->assertJsonPath('data.booking.external_deposit.status', 'received');

        try {
            app(\App\Services\ServiceExecutionEngine::class)->moveBookingToInProgress($booking->fresh());
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayNotHasKey('external_deposit', $e->errors(), 'the prepayment no longer stands in the way');
        }
    }

    public function test_the_booking_tells_both_sides_what_the_stay_is_made_of(): void
    {
        $this->policy(['cots_max' => 1, 'cot_price' => 0])->assertOk();
        $this->unit->update(['single_occupancy_percent' => 80]);

        Sanctum::actingAs($this->guest);
        $id = $this->postJson('/api/v2/bookings', $this->body(0, 2, ['party_size' => 1, 'children_ages' => [1], 'cots' => 1, 'option_ids' => [self::BREAKFAST]]))
            ->assertCreated()->json('data.booking.id');

        Sanctum::actingAs($this->hotel);
        $details = $this->getJson("/api/v2/bookings/{$id}")->assertOk()->json('data.stay_details');
        $this->assertSame(1, $details['adults']);
        $this->assertSame(1, $details['children'][0]['age']);
        $this->assertSame(1, $details['cots']);
        $this->assertEquals(80, $details['single_occupancy_percent']);
        $this->assertCount(1, $details['meal_plans']);
        $this->assertSame('flexible', $details['rate_plan']);
    }

    public function test_a_baby_cot_on_request_at_the_hotels_price(): void
    {
        $this->preview(0, 2, ['party_size' => 2, 'cots' => 1])->assertStatus(422)->assertJsonValidationErrors(['cots']);

        // one cot a room at 50 a night
        $this->policy(['cots_max' => 1, 'cot_price' => 50])->assertOk()->assertJsonPath('data.policy.cots_max', 1);

        $res = $this->preview(0, 2, ['party_size' => 2, 'children_ages' => [1], 'cots' => 1])->assertOk();
        $this->assertEquals(2000 + 100, $res->json('data.price'));
        $this->assertEquals(1, $res->json('data.price_breakdown.cots.count'));
        $this->preview(0, 2, ['party_size' => 2, 'cots' => 2])->assertStatus(422)->assertJsonValidationErrors(['cots']);

        // kept on the booking
        $id = $this->postJson('/api/v2/bookings', $this->body(0, 2, ['party_size' => 2, 'children_ages' => [1], 'cots' => 1]))->assertCreated()->json('data.booking.id');
        $this->getJson("/api/v2/bookings/{$id}")->assertOk()->assertJsonPath('data.stay_terms.cots', 1);
    }
}
