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
        $this->unit->update(['capacity' => 3]); // two adults and the child of 8, who counts

        // two adults with breakfast, a child of 4 (free) and a child of 8 (half), two nights
        $res = $this->stay(['party_size' => 2, 'children_ages' => [4, 8], 'option_ids' => [self::BREAKFAST]])->assertCreated();
        $booking = Booking::find($res->json('data.booking.id'));

        // a night: room 1000 + breakfast 2 × 100 = 1200; the child of 8: half the room's 1000 + half one breakfast = 550
        $this->assertEquals((1200 + 550) * 2, (float) $booking->price);
        $this->assertSame([4, 8], $booking->meta['children_ages']);

        Sanctum::actingAs($this->guest);
        $preview = $this->postJson('/api/v2/bookings/preview', [
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'bookable_id' => $this->unit->id, 'quantity' => 1,
            'starts_at' => $this->start->copy()->addDays(10)->toDateTimeString(), 'ends_at' => $this->start->copy()->addDays(12)->toDateTimeString(),
            'party_size' => 2, 'children_ages' => [8], 'option_ids' => [self::BREAKFAST],
        ])->assertOk();
        $this->assertEquals(1100, $preview->json('data.price_breakdown.children_total'));
        $this->assertEquals(550, $preview->json('data.price_breakdown.children.0.per_night'));
    }

    public function test_children_count_in_the_room_from_the_age_the_hotel_names(): void
    {
        $this->policy()->assertOk();

        // a double: two adults and a child of 8 is three — too many; with a child of 4 instead it fits
        $this->stay(['party_size' => 2, 'children_ages' => [8]])->assertStatus(422)->assertJsonValidationErrors(['party_size']);
        $this->stay(['party_size' => 2, 'children_ages' => [4]])->assertCreated();
    }

    public function test_a_hotel_with_no_policy_keeps_children_free_and_uncounted(): void
    {
        $res = $this->stay(['party_size' => 2, 'children_ages' => [8]])->assertCreated();

        $this->assertEquals(1000 * 2, (float) Booking::find($res->json('data.booking.id'))->price);
    }
}
