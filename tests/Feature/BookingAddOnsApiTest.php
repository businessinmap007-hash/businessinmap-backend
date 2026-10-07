<?php

namespace Tests\Feature;

use App\Models\BookableItem;
use App\Models\BusinessServicePrice;
use App\Models\PlatformService;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * «تسعير نظام الوجبات بشكل منفصل كالإضافات في المطاعم … بيختار العميل نوع الغرفة - فردية - نصف إقامة - إطلالة على
 * المسبح، هنا يتم جمع 800 قيمة الغرفة + 250 نصف الإقامة + 150 تمييز للغرفة = 1200 … الفندق يحدد الغرفة 206 من
 * المميزات المضافة للغرفة مباشرة إطلالة على المسبح، سعر الغرفة الظاهر للعميل 950» — المالك، 2026-10-07.
 *
 * Rolls back.
 */
class BookingAddOnsApiTest extends TestCase
{
    use DatabaseTransactions;

    private const ROOT = 24;
    private const CHILD = 536;

    private const SINGLE_ROOM = 965;
    private const BREAKFAST = 855;
    private const HALF_BOARD = 857;
    private const FULL_BOARD = 856;
    private const POOL_VIEW = 854;

    private User $hotel;

    private User $guest;

    protected function setUp(): void
    {
        parent::setUp();

        $make = fn (string $type, array $extra = []) => User::create([
            'name' => 'Zz AddOnsApi ' . uniqid(),
            'email' => 'zz-addonsapi-' . uniqid() . '@test.local',
            'phone' => '01' . random_int(100000000, 999999999),
            'password' => Hash::make('Passw0rdTest'),
            'type' => $type,
            'api_token' => 'zz' . uniqid() . bin2hex(random_bytes(8)),
        ] + $extra);

        $this->hotel = $make(User::TYPE_BUSINESS, ['category_id' => self::ROOT, 'category_child_id' => self::CHILD]);
        $this->guest = $make(User::TYPE_CLIENT);
    }

    private function serviceId(): int
    {
        return (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->value('id');
    }

    private function hotelPuts(array $body)
    {
        return $this->actingAs($this->hotel, 'sanctum')->putJson('/api/v2/business/booking-add-ons', $body);
    }

    private function room206(): BookableItem
    {
        BusinessServicePrice::create([
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId(), 'child_id' => self::CHILD,
            'bookable_item_type' => 'booking_stay', 'line_option_id' => self::SINGLE_ROOM, 'price' => 800, 'currency' => 'EGP', 'is_active' => 1,
        ]);

        return BookableItem::create([
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId(), 'item_type' => 'booking_stay',
            'line_option_id' => self::SINGLE_ROOM, 'code' => '206', 'quantity' => 1, 'is_active' => 1,
        ]);
    }

    public function test_the_screen_offers_meal_plans_as_a_one_choice_group_and_the_view_as_a_feature(): void
    {
        $data = $this->actingAs($this->hotel, 'sanctum')->getJson('/api/v2/business/booking-add-ons')->assertOk()->json('data');

        $meals = collect($data['add_ons'])->firstWhere('group', 'نظام الوجبات');
        $this->assertNotNull($meals, 'the meal plans are an add-on');
        $this->assertSame('single', $meals['selection_type'], 'one choice by default — a radio button');
        $this->assertEqualsCanonicalizing([self::BREAKFAST, self::HALF_BOARD, self::FULL_BOARD], array_column($meals['options'], 'id'));

        $views = collect($data['features'])->firstWhere('group', 'إطلالة الوحدة');
        $this->assertNotNull($views, 'a view belongs to one room');
        $this->assertContains(self::POOL_VIEW, array_column($views['options'], 'id'));

        // the rooms are the base of the price — neither an add-on nor a feature
        $this->assertNull(collect($data['add_ons'])->firstWhere('group', 'الغرف'));
        $this->assertNull(collect($data['features'])->firstWhere('group', 'الغرف'));
    }

    public function test_the_hotel_prices_the_meal_plans_and_the_view_once_and_reads_them_back(): void
    {
        $this->hotelPuts([
            'add_ons' => [
                ['option_id' => self::HALF_BOARD, 'enabled' => true, 'value' => 250],
                ['option_id' => self::FULL_BOARD, 'enabled' => true, 'value' => 500],
                ['option_id' => self::BREAKFAST, 'enabled' => false, 'value' => 100],
            ],
            'features' => [['option_id' => self::POOL_VIEW, 'enabled' => true, 'value' => 150]],
        ])->assertOk();

        $data = $this->actingAs($this->hotel, 'sanctum')->getJson('/api/v2/business/booking-add-ons')->json('data');
        $options = collect(collect($data['add_ons'])->firstWhere('group', 'نظام الوجبات')['options'])->keyBy('id');

        $this->assertTrue($options[self::HALF_BOARD]['enabled']);
        $this->assertEquals(250, $options[self::HALF_BOARD]['value']);
        $this->assertFalse($options[self::BREAKFAST]['enabled'], 'a plan the hotel does not sell is not offered');

        $pool = collect(collect($data['features'])->firstWhere('group', 'إطلالة الوحدة')['options'])->firstWhere('id', self::POOL_VIEW);
        $this->assertEquals(150, $pool['value']);

        // the meal plans are single-choice; the hotel may allow several and the platform default is restored by saying so
        $group = (int) \App\Models\OptionGroup::query()->where('name_ar', 'نظام الوجبات')->value('id');
        $this->hotelPuts(['selection_types' => [$group => 'multiple']])->assertOk();
        $this->assertSame('multiple', collect($this->actingAs($this->hotel, 'sanctum')->getJson('/api/v2/business/booking-add-ons')->json('data.add_ons'))->firstWhere('group', 'نظام الوجبات')['selection_type']);
        $this->hotelPuts(['selection_types' => [$group => 'single']])->assertOk();
        $this->assertSame('single', collect($this->actingAs($this->hotel, 'sanctum')->getJson('/api/v2/business/booking-add-ons')->json('data.add_ons'))->firstWhere('group', 'نظام الوجبات')['selection_type']);
    }

    public function test_saving_never_wipes_what_the_screen_does_not_show(): void
    {
        // a business-wide row from another screen (not one of this screen's options)
        $this->hotel->syncOfferingOptions(null, [self::SINGLE_ROOM], [self::SINGLE_ROOM => ['type' => 'amount', 'value' => 7]]);

        $this->hotelPuts(['add_ons' => [['option_id' => self::HALF_BOARD, 'enabled' => true, 'value' => 250]]])->assertOk();

        $this->assertArrayHasKey(self::SINGLE_ROOM, $this->hotel->fresh()->currentOfferingAdjustments());
    }

    public function test_the_owners_example_a_rooms_own_price_and_the_meal_plan_added_to_each_night(): void
    {
        $room = $this->room206();

        $this->hotelPuts([
            'add_ons' => [['option_id' => self::HALF_BOARD, 'enabled' => true, 'value' => 250]],
            'features' => [['option_id' => self::POOL_VIEW, 'enabled' => true, 'value' => 150]],
        ])->assertOk();

        // 206 carries the pool view: the hotel says so on the room
        $this->actingAs($this->hotel, 'sanctum')->putJson("/api/v2/business/bookable-items/{$room->id}/features", ['option_ids' => [self::POOL_VIEW]])
            ->assertOk()->assertJsonPath('data.features.0.options.0.selected', fn ($v) => is_bool($v));

        $features = collect($this->actingAs($this->hotel, 'sanctum')->getJson("/api/v2/business/bookable-items/{$room->id}/features")->json('data.features'))
            ->flatMap(fn ($g) => $g['options'])->keyBy('id');
        $this->assertTrue($features[self::POOL_VIEW]['selected']);
        $this->assertEquals(150, $features[self::POOL_VIEW]['price']);

        // the guest sees the ROOM at 800 + 150 = 950 before choosing anything
        $unit = collect($this->actingAs($this->guest, 'sanctum')->getJson("/api/v2/discovery/units/{$this->hotel->id}")->assertOk()->json('data.kinds'))
            ->flatMap(fn ($g) => $g['units'] ?? [])->firstWhere('id', $room->id);
        $this->assertEquals(950, $unit['price']);

        // …then picks the meal plan and the nights: (950 + 250) × 2 nights = 2400
        $from = now()->addDays(3)->startOfDay()->addHours(14);
        $preview = $this->actingAs($this->guest, 'sanctum')->postJson('/api/v2/bookings/preview', [
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId(), 'bookable_id' => $room->id,
            'starts_at' => $from->toDateTimeString(), 'ends_at' => $from->copy()->addDays(2)->toDateTimeString(),
            'option_ids' => [self::HALF_BOARD],
        ])->assertOk();
        $this->assertEquals(2400, $preview->json('data.price'));
    }

    public function test_a_guest_cannot_take_two_meal_plans(): void
    {
        $room = $this->room206();
        $this->hotelPuts(['add_ons' => [
            ['option_id' => self::HALF_BOARD, 'enabled' => true, 'value' => 250],
            ['option_id' => self::FULL_BOARD, 'enabled' => true, 'value' => 500],
        ]])->assertOk();

        $from = now()->addDays(3)->startOfDay()->addHours(14);
        $body = [
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId(), 'bookable_id' => $room->id,
            'starts_at' => $from->toDateTimeString(), 'ends_at' => $from->copy()->addDay()->toDateTimeString(),
        ];

        $this->actingAs($this->guest, 'sanctum')->postJson('/api/v2/bookings/preview', $body + ['option_ids' => [self::HALF_BOARD]])->assertOk();
        $this->actingAs($this->guest, 'sanctum')->postJson('/api/v2/bookings/preview', $body + ['option_ids' => [self::HALF_BOARD, self::FULL_BOARD]])->assertStatus(422);
    }

    public function test_the_form_offers_meal_plans_as_radio_buttons(): void
    {
        $this->room206();
        $this->hotelPuts(['add_ons' => [
            ['option_id' => self::HALF_BOARD, 'enabled' => true, 'value' => 250],
            ['option_id' => self::FULL_BOARD, 'enabled' => true, 'value' => 500],
        ]])->assertOk();

        $modifiers = collect($this->actingAs($this->guest, 'sanctum')->getJson("/api/v2/bookings/form/{$this->hotel->id}")->assertOk()->json('data.modifiers'));

        $this->assertNotEmpty($modifiers);
        $this->assertSame(['single'], $modifiers->pluck('selection_type')->unique()->values()->all());
    }

    public function test_a_price_line_no_longer_offers_what_is_priced_elsewhere(): void
    {
        $options = $this->actingAs($this->hotel, 'sanctum')->getJson('/api/v2/business/prices/options')->assertOk();
        $groups = collect($options->json('data.modifiers'))->pluck('group');

        $this->assertFalse($groups->contains('نظام الوجبات'), 'the meal plans are an add-on now');
        $this->assertFalse($groups->contains('الغرف'), 'the rooms are the line, not a modifier of it');
        // …while the rooms stay available as the line itself
        $this->assertTrue(collect($options->json('data.lines'))->pluck('group')->contains('الغرف'));
    }

    public function test_another_business_cannot_touch_a_rooms_features(): void
    {
        $room = $this->room206();
        $other = User::query()->where('type', User::TYPE_BUSINESS)->where('id', '!=', $this->hotel->id)->firstOrFail();

        $this->actingAs($other, 'sanctum')->putJson("/api/v2/business/bookable-items/{$room->id}/features", ['option_ids' => [self::POOL_VIEW]])->assertNotFound();
    }
}
