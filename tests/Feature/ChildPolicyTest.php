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
 * «سياسة الأطفال» (owner, 2026-10-27): a child counts in the room from an age the hotel names, pays a percentage of every night's
 * price by its age band (free up to an age), and its meals follow the same percentage; the meal plan itself is chosen at booking.
 */
class ChildPolicyTest extends TestCase
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
            'name' => 'Zz Child ' . uniqid(), 'email' => 'zz-child-' . uniqid() . '@test.local', 'phone' => '01' . random_int(100000000, 999999999),
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
        $this->unit = BookableItem::create([
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'item_type' => 'booking_stay',
            'line_option_id' => self::SINGLE_ROOM, 'code' => 'Z-' . uniqid(), 'quantity' => 3, 'capacity' => 2, 'is_active' => 1,
        ]);
        $this->unit->syncOfferingOptions(self::SINGLE_ROOM, []);

        // breakfast, 100 a person a night, chosen at booking
        OfferingOption::create([
            'offering_type' => (new User)->getMorphClass(), 'offering_id' => $this->hotel->id, 'option_id' => self::BREAKFAST,
            'role' => OfferingOption::ROLE_MODIFIER, 'adjust_type' => OfferingOption::ADJUST_AMOUNT, 'adjust_value' => 100, 'per_person' => true, 'sort_order' => 1,
        ]);
    }

    private function policy(array $body = [])
    {
        Sanctum::actingAs($this->hotel);

        return $this->putJson('/api/v2/business/child-policy', $body + [
            'adult_from_age' => 12, 'counts_from_age' => 6, 'meals_follow' => true,
            'bands' => [['from' => 0, 'to' => 5, 'percent' => 0], ['from' => 6, 'to' => 11, 'percent' => 50]],
        ]);
    }

    private function stay(array $extra)
    {
        Sanctum::actingAs($this->guest);

        return $this->postJson('/api/v2/bookings', $extra + [
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'bookable_id' => $this->unit->id, 'quantity' => 1,
            'starts_at' => $this->start->toDateTimeString(), 'ends_at' => $this->start->copy()->addDays(2)->toDateTimeString(),
        ]);
    }

    public function test_the_hotel_writes_its_policy_and_the_guest_reads_it_with_the_rooms(): void
    {
        $this->policy()->assertOk()->assertJsonPath('data.policy.counts_from_age', 6)->assertJsonPath('data.policy.bands.1.percent', 50);

        // bands that overlap or reach the adult age are refused
        $this->policy(['bands' => [['from' => 0, 'to' => 6, 'percent' => 0], ['from' => 6, 'to' => 11, 'percent' => 50]]])->assertStatus(422);
        $this->policy(['bands' => [['from' => 6, 'to' => 12, 'percent' => 50]]])->assertStatus(422);

        Sanctum::actingAs($this->guest);
        $this->getJson("/api/v2/discovery/units/{$this->hotel->id}")->assertOk()
            ->assertJsonPath('data.child_policy.adult_from_age', 12)
            ->assertJsonPath('data.child_policy.bands.0.percent', 0);
    }

    public function test_a_child_pays_its_share_of_every_night_and_of_the_meals(): void
    {
        $this->policy()->assertOk();

        // a double (1000 a night = 500 a person): one adult with breakfast, a child of 4 (free) and a child of 8 (half), two nights
        $res = $this->stay(['party_size' => 1, 'children_ages' => [4, 8], 'option_ids' => [self::BREAKFAST]])->assertCreated();
        $booking = Booking::find($res->json('data.booking.id'));

        // a night: room 1000 + one breakfast 100 = 1100; the child of 8: half of one person's 500 + half one breakfast = 300
        $this->assertEquals((1100 + 300) * 2, (float) $booking->price);
        $this->assertSame([4, 8], $booking->meta['children_ages']);

        Sanctum::actingAs($this->guest);
        $preview = $this->postJson('/api/v2/bookings/preview', [
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'bookable_id' => $this->unit->id, 'quantity' => 1,
            'starts_at' => $this->start->copy()->addDays(10)->toDateTimeString(), 'ends_at' => $this->start->copy()->addDays(12)->toDateTimeString(),
            'party_size' => 1, 'children_ages' => [8], 'option_ids' => [self::BREAKFAST],
        ])->assertOk();
        $this->assertEquals(600, $preview->json('data.price_breakdown.children_total'));
        $this->assertEquals(300, $preview->json('data.price_breakdown.children.0.per_night'));
    }

    public function test_children_count_in_the_room_from_the_age_the_hotel_names(): void
    {
        $this->policy()->assertOk();

        // a double: two adults and a child of 8 is three — too many; with a child of 4 instead it fits
        $this->stay(['party_size' => 2, 'children_ages' => [8]])->assertStatus(422)->assertJsonValidationErrors(['party_size']);
        $this->stay(['party_size' => 2, 'children_ages' => [4]])->assertCreated();
    }

    public function test_over_the_capacity_a_guest_gets_an_extra_bed_at_its_price_instead_of_a_refusal(): void
    {
        // the hotel sells one extra bed a room at 200 a night
        $this->policy(['extra_bed_price' => 200, 'extra_beds_max' => 1])->assertOk()->assertJsonPath('data.policy.extra_bed_price', 200);

        // a double: two adults and a child of 8 — the child is in the extra bed and pays only its share: 1000 + 250 a night
        $res = $this->stay(['party_size' => 2, 'children_ages' => [8]])->assertCreated();
        $this->assertEquals((1000 + 250) * 2, (float) Booking::find($res->json('data.booking.id'))->price, 'a child is never charged its share AND the bed');

        // a third ADULT pays the extra bed: 1000 + 200 a night
        Sanctum::actingAs($this->guest);
        $body = [
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'bookable_id' => $this->unit->id, 'quantity' => 1,
            'starts_at' => $this->start->copy()->addDays(10)->toDateTimeString(), 'ends_at' => $this->start->copy()->addDays(12)->toDateTimeString(),
        ];
        $res = $this->postJson('/api/v2/bookings', $body + ['party_size' => 3])->assertCreated();
        $this->assertEquals((1000 + 200) * 2, (float) Booking::find($res->json('data.booking.id'))->price);

        // a fourth guest needs a second extra bed, which the room does not take
        $this->postJson('/api/v2/bookings', ['starts_at' => $this->start->copy()->addDays(20)->toDateTimeString(), 'ends_at' => $this->start->copy()->addDays(22)->toDateTimeString()] + $body + ['party_size' => 3, 'children_ages' => [8]])
            ->assertStatus(422)->assertJsonValidationErrors(['party_size']);
    }

    public function test_one_child_free_per_room_and_the_next_one_pays_its_share(): void
    {
        // «طفل واحد مجانًا لكل غرفة»: under 6 the first child is free, a second pays half; 6–11 half for each
        $this->policy(['bands' => [['from' => 0, 'to' => 5, 'percent' => 0, 'second_percent' => 50], ['from' => 6, 'to' => 11, 'percent' => 50]]])->assertOk()
            ->assertJsonPath('data.policy.bands.0.second_percent', 50);

        // one adult with two small children in a double (500 a person): one is free, the other pays half = 250 a night
        $res = $this->stay(['party_size' => 1, 'children_ages' => [2, 4]])->assertCreated();
        $this->assertEquals((1000 + 250) * 2, (float) Booking::find($res->json('data.booking.id'))->price);
    }

    public function test_the_guest_reads_the_meal_times_of_the_plan_he_chose(): void
    {
        Sanctum::actingAs($this->hotel);
        $this->putJson('/api/v2/business/meal-times', [
            'breakfast' => ['from' => '07:00', 'to' => '10:30'], 'lunch' => ['from' => '13:00', 'to' => '15:30'], 'dinner' => ['from' => '19:00', 'to' => '22:00'],
        ])->assertOk()->assertJsonPath('data.times.dinner.from', '19:00');
        $this->putJson('/api/v2/business/meal-times', ['breakfast' => ['from' => '7am']])->assertStatus(422);

        // half board: half board = breakfast + dinner
        $halfBoard = (int) \App\Models\Option::query()->where('name_en', 'Half Board')->value('id');
        OfferingOption::create([
            'offering_type' => (new User)->getMorphClass(), 'offering_id' => $this->hotel->id, 'option_id' => $halfBoard,
            'role' => OfferingOption::ROLE_MODIFIER, 'adjust_type' => OfferingOption::ADJUST_AMOUNT, 'adjust_value' => 150, 'per_person' => true, 'sort_order' => 2,
        ]);
        $id = $this->stay(['party_size' => 1, 'option_ids' => [$halfBoard]])->assertCreated()->json('data.booking.id');

        Sanctum::actingAs($this->guest);
        $meals = collect($this->getJson("/api/v2/bookings/{$id}")->assertOk()->json('data.meal_times'));
        $this->assertSame(['breakfast', 'dinner'], $meals->pluck('meal')->all());
        $this->assertSame('07:00', $meals->firstWhere('meal', 'breakfast')['from']);
        $this->assertSame('22:00', $meals->firstWhere('meal', 'dinner')['to']);

        // all inclusive is a plan of its own and gives all three meals
        $this->assertNotNull(\App\Models\Option::query()->where('name_en', 'All Inclusive')->value('id'));
    }

    public function test_one_adult_with_a_child_gives_the_child_the_second_adults_place(): void
    {
        // «بالغ واحد مع طفل»: the hotel lets the child in the empty adult place stay free
        $this->policy(['single_parent_percent' => 0])->assertOk()->assertJsonPath('data.policy.single_parent_percent', 0);

        // a double (500 a person): one adult and a child of 8 — the child is in the paid second place: just the room
        $res = $this->stay(['party_size' => 1, 'children_ages' => [8]])->assertCreated();
        $this->assertEquals(1000 * 2, (float) Booking::find($res->json('data.booking.id'))->price);

        // two adults: no empty place, the child of 8 pays its half share (250 a night)
        Sanctum::actingAs($this->guest);
        $body = [
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'bookable_id' => $this->unit->id, 'quantity' => 1,
            'starts_at' => $this->start->copy()->addDays(10)->toDateTimeString(), 'ends_at' => $this->start->copy()->addDays(12)->toDateTimeString(),
        ];
        $preview = $this->postJson('/api/v2/bookings/preview', $body + ['party_size' => 2, 'children_ages' => [4]])->assertOk();
        $this->assertFalse($preview->json('data.price_breakdown.children.0.single_parent'));

        // a hotel whose under-6s pay half: one adult with children of 4 and 9 — the 9-year-old takes the adult place free, the 4-year-old
        // is the room's first child at half (250 a night)
        $this->policy(['single_parent_percent' => 0, 'bands' => [['from' => 0, 'to' => 5, 'percent' => 50], ['from' => 6, 'to' => 11, 'percent' => 50]]])->assertOk();
        Sanctum::actingAs($this->guest);
        $preview = $this->postJson('/api/v2/bookings/preview', $body + ['party_size' => 1, 'children_ages' => [4, 9]])->assertOk();
        $this->assertEquals(250 * 2, $preview->json('data.price_breakdown.children_total'));
        $this->assertTrue($preview->json('data.price_breakdown.children.1.single_parent'));

        // a hotel that charges the child in the adult place 30% of a person: 150 a night
        $this->policy(['single_parent_percent' => 30])->assertOk();
        Sanctum::actingAs($this->guest);
        $preview = $this->postJson('/api/v2/bookings/preview', $body + ['party_size' => 1, 'children_ages' => [8]])->assertOk();
        $this->assertEquals(150 * 2, $preview->json('data.price_breakdown.children_total'));

        // left empty: the child pays its normal band share again
        $this->policy()->assertOk()->assertJsonPath('data.policy.single_parent_percent', null);
    }

    public function test_a_stay_over_a_gala_night_pays_its_compulsory_dinner(): void
    {
        Sanctum::actingAs($this->hotel);
        $gala = $this->start->copy()->addDay()->toDateString(); // the stay's SECOND night
        $id = $this->postJson('/api/v2/business/gala-dinners', [
            'date' => $gala, 'name' => 'عشاء رأس السنة', 'adult_price' => 500, 'child_percent' => 50, 'child_free_under' => 6,
        ])->assertCreated()->assertJsonPath('data.gala_dinner.adult_price', 500)->json('data.gala_dinner.id');

        // one dinner a night; a past date is refused
        $this->postJson('/api/v2/business/gala-dinners', ['date' => $gala, 'name' => 'x', 'adult_price' => 1])->assertStatus(422)->assertJsonValidationErrors(['date']);
        $this->postJson('/api/v2/business/gala-dinners', ['date' => now()->subDay()->toDateString(), 'name' => 'x', 'adult_price' => 1])->assertStatus(422);

        // two adults, a child of 8 (half the dinner) and one of 3 (free): 2 × 1000 + 2 × 500 + 250 — in a «2 بالغ + 2 طفل» room
        $this->unit->update(['max_children' => 2]);
        $res = $this->stay(['party_size' => 2, 'children_ages' => [8, 3]])->assertCreated();
        $this->assertEquals(2000 + 1000 + 250, (float) Booking::find($res->json('data.booking.id'))->price);

        // the guest sees the gala nights with the rooms, and a stay over other nights does not pay it
        $this->getJson("/api/v2/discovery/units/{$this->hotel->id}")->assertOk()->assertJsonPath('data.gala_dinners.0.date', $gala);
        $preview = $this->postJson('/api/v2/bookings/preview', [
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'bookable_id' => $this->unit->id, 'quantity' => 1,
            'starts_at' => $this->start->copy()->subDay()->toDateTimeString(), 'ends_at' => $this->start->copy()->addDay()->toDateTimeString(), 'party_size' => 2,
        ])->assertOk();
        $this->assertNull($preview->json('data.price_breakdown.gala_dinners'), 'the check-out day is not a night of the stay');

        // the hotel edits and removes it
        Sanctum::actingAs($this->hotel);
        $this->putJson("/api/v2/business/gala-dinners/{$id}", ['date' => $gala, 'name' => 'Gala', 'adult_price' => 600])->assertOk()->assertJsonPath('data.gala_dinner.adult_price', 600);
        $this->deleteJson("/api/v2/business/gala-dinners/{$id}")->assertOk();
        $this->getJson('/api/v2/business/gala-dinners')->assertOk()->assertJsonCount(0, 'data.gala_dinners');

        // another hotel cannot touch it
        Sanctum::actingAs($this->guest);
        $this->deleteJson("/api/v2/business/gala-dinners/{$id}")->assertStatus(403);
    }

    public function test_a_hotel_with_no_policy_charges_no_child(): void
    {
        // the child sleeps besides the adults of a «2 بالغ + 1 طفل» room and pays nothing
        $this->unit->update(['max_children' => 1]);
        $res = $this->stay(['party_size' => 2, 'children_ages' => [8]])->assertCreated();

        $this->assertEquals(1000 * 2, (float) Booking::find($res->json('data.booking.id'))->price);
    }

    public function test_a_hotel_with_no_policy_counts_every_child_from_two_only_a_baby_is_free(): void
    {
        // no «سياسة الأطفال»: a double takes two adults and a baby of one, not two adults and a child of three
        $this->stay(['party_size' => 2, 'children_ages' => [3]])->assertStatus(422)->assertJsonValidationErrors(['party_size']);
        $this->assertSame(\App\Models\ChildPolicy::DEFAULT_COUNTS_FROM_AGE, 2);
        $this->stay(['party_size' => 2, 'children_ages' => [1]])->assertCreated();
    }

    public function test_a_room_of_two_adults_and_two_children_takes_its_children_besides_the_adults_and_no_more(): void
    {
        $this->policy()->assertOk();

        Sanctum::actingAs($this->hotel);
        $this->putJson("/api/v2/business/bookable-items/{$this->unit->id}", [
            'service_id' => $this->serviceId, 'item_type' => 'booking_stay', 'code' => $this->unit->code, 'capacity' => 2, 'quantity' => 3, 'max_children' => 2,
        ])->assertOk()->assertJsonPath('data.max_children', 2);

        // each stay on its own nights: one guest cannot hold two stays at once
        $on = fn (int $week) => ['starts_at' => $this->start->copy()->addWeeks($week)->toDateTimeString(), 'ends_at' => $this->start->copy()->addWeeks($week)->addDays(2)->toDateTimeString()];

        // «2 بالغ + 2 طفل»: two children of 8 and 10 sleep besides the two adults; a third child is one too many (a baby still free)
        $this->stay($on(0) + ['party_size' => 2, 'children_ages' => [8, 10, 1]])->assertCreated();
        $this->stay($on(1) + ['party_size' => 2, 'children_ages' => [8, 10, 4]])->assertStatus(422)->assertJsonValidationErrors(['children_ages']);
        // two rooms take four
        $this->stay($on(3) + ['quantity' => 2, 'party_size' => 4, 'children_ages' => [8, 10, 4, 5]])->assertCreated();
        // a guest of 13 is an adult by the policy: he takes an adult's place
        $this->stay($on(2) + ['party_size' => 2, 'children_ages' => [13]])->assertStatus(422)->assertJsonValidationErrors(['party_size']);
    }
}
