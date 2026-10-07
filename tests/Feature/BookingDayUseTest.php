<?php

namespace Tests\Feature;

use App\Models\BookableItem;
use App\Models\Booking;
use App\Models\BusinessDepositPolicy;
use App\Models\BusinessServicePrice;
use App\Models\Deposit;
use App\Models\PlatformService;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «واضف خدمة Day use للحجز فى الفنادق» — المالك، 2026-10-07.
 *
 * A hotel room type is also sold through the day: a window, a flat price, one room held for that window only.
 * Rolls back.
 */
class BookingDayUseTest extends TestCase
{
    use DatabaseTransactions;

    private User $hotel;

    private User $guest;

    private BookableItem $type;

    private int $serviceId;

    protected function setUp(): void
    {
        parent::setUp();

        $booking = Booking::withTrashed()
            ->whereNotNull('user_id')->whereNotNull('business_id')->whereColumn('user_id', '!=', 'business_id')
            ->first();

        if ($booking && $booking->trashed()) {
            $booking->restore();
        }

        $hotel = $booking?->business;

        if (! $booking || ! $hotel || (string) $hotel->type !== User::TYPE_BUSINESS) {
            $this->markTestSkipped('Needs a booking whose business is a business account.');
        }

        $this->hotel = $hotel;
        $this->guest = $booking->user;
        $this->serviceId = (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->value('id');

        $this->type = BookableItem::create([
            'business_id' => $hotel->id,
            'service_id' => $this->serviceId,
            'item_type' => 'booking_stay',
            'line_option_id' => 965,
            'title' => 'غرفة مزدوجة',
            'code' => 'TYPE-A',
            'capacity' => 2,
            'quantity' => 1,
            'is_active' => 1,
        ]);

        $childId = (int) ($hotel->category_child_id ?? 0);
        BusinessServicePrice::query()->where('business_id', $hotel->id)->where('service_id', $this->serviceId)->where('child_id', $childId)->delete();
        BusinessServicePrice::create([
            'business_id' => $hotel->id, 'service_id' => $this->serviceId, 'child_id' => $childId,
            'bookable_item_type' => BusinessServicePrice::DEFAULT_ITEM_TYPE,
            'price' => 1000, 'currency' => 'EGP', 'is_active' => 1,
        ]);
        BusinessDepositPolicy::query()->where('business_id', $hotel->id)->delete();
        Deposit::query()->where('target_type', Booking::class)->whereIn('target_id', Booking::query()->where('bookable_id', $this->type->id)->pluck('id'))->delete();
        DB::table('category_child_service_fees')->where('category_id', (int) $hotel->category_id)->where('child_id', $childId)->delete();
    }

    private function enable(array $overrides = [])
    {
        return $this->actingAs($this->hotel, 'sanctum')->putJson(
            "/api/v2/business/bookable-items/{$this->type->id}/day-use",
            $overrides + ['enabled' => true, 'from' => '09:00', 'to' => '18:00', 'price' => 350]
        );
    }

    private function body(array $extra = []): array
    {
        return $extra + [
            'business_id' => $this->hotel->id,
            'service_id' => $this->serviceId,
            'bookable_id' => $this->type->id,
            'day_use' => true,
            'date' => now()->addDays(3)->toDateString(),
        ];
    }

    public function test_the_hotel_sets_a_window_and_a_flat_price_on_a_room_type(): void
    {
        $this->actingAs($this->hotel, 'sanctum')->getJson("/api/v2/business/bookable-items/{$this->type->id}/day-use")
            ->assertOk()->assertJsonPath('data.day_use.enabled', false);

        $this->enable()->assertOk()
            ->assertJsonPath('data.day_use.enabled', true)
            ->assertJsonPath('data.day_use.from', '09:00')
            ->assertJsonPath('data.day_use.price', 350);

        // the room type reports it to the hotel's own list
        $this->actingAs($this->hotel, 'sanctum')->getJson("/api/v2/business/bookable-items/{$this->type->id}")
            ->assertOk()->assertJsonPath('data.day_use.enabled', true);
    }

    public function test_a_window_too_short_or_a_missing_price_is_refused(): void
    {
        $this->enable(['from' => '09:00', 'to' => '10:00'])->assertStatus(422);
        $this->enable(['from' => '18:00', 'to' => '09:00'])->assertStatus(422);
        $this->enable(['from' => '00:00', 'to' => '23:00'])->assertStatus(422);
        $this->enable(['price' => null])->assertStatus(422);
        $this->enable(['price' => 0])->assertStatus(422);

        // switching it off needs neither
        $this->enable(['enabled' => false, 'price' => null])->assertOk()->assertJsonPath('data.day_use.enabled', false);
    }

    public function test_only_a_stay_room_type_has_day_use(): void
    {
        $table = BookableItem::create([
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'item_type' => 'booking_table', 'code' => 'T1', 'quantity' => 1, 'is_active' => 1,
        ]);

        $this->actingAs($this->hotel, 'sanctum')->putJson("/api/v2/business/bookable-items/{$table->id}/day-use", ['enabled' => true, 'from' => '09:00', 'to' => '18:00', 'price' => 100])
            ->assertStatus(422);
    }

    public function test_another_business_cannot_set_it(): void
    {
        $other = User::query()->where('type', User::TYPE_BUSINESS)->where('id', '!=', $this->hotel->id)->firstOrFail();

        $this->actingAs($other, 'sanctum')->putJson("/api/v2/business/bookable-items/{$this->type->id}/day-use", ['enabled' => true, 'from' => '09:00', 'to' => '18:00', 'price' => 1])
            ->assertNotFound();
    }

    public function test_the_guest_is_told_the_room_type_offers_day_use_only_when_it_does(): void
    {
        $units = fn () => collect($this->actingAs($this->guest, 'sanctum')->getJson("/api/v2/discovery/units/{$this->hotel->id}")->assertOk()->json('data.kinds'))
            ->flatMap(fn ($g) => $g['units'] ?? [])->firstWhere('id', $this->type->id);

        $this->assertNull($units()['day_use'] ?? null);

        $this->enable()->assertOk();
        $this->assertEquals(['from' => '09:00', 'to' => '18:00', 'price' => 350], $units()['day_use']);
    }

    public function test_the_preview_prices_the_flat_day_use_price_not_a_night(): void
    {
        $this->enable()->assertOk();

        $res = $this->actingAs($this->guest, 'sanctum')->postJson('/api/v2/bookings/preview', $this->body())->assertOk();

        $this->assertEquals(350, $res->json('data.price'));
        $this->assertSame('day_use', $res->json('data.price_breakdown.source'));
    }

    public function test_day_use_meals_ride_on_the_flat_price_and_a_nights_meal_plan_does_not(): void
    {
        $this->seed(\Database\Seeders\BookingDesignOptionsSeeder::class);
        $this->enable()->assertOk();

        $meal = fn (string $name) => (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', 'وجبات Day use')->where('o.name_ar', $name)->value('o.id');
        $breakfast = $meal('فطار');
        $lunch = $meal('غداء');
        $nightPlan = (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', 'نظام الوجبات')->where('o.name_ar', 'شامل الإفطار')->value('o.id');

        $this->assertGreaterThan(0, $breakfast);
        $this->assertGreaterThan(0, $nightPlan);

        $this->hotel->syncOfferingOptions(null, [$breakfast, $lunch, $nightPlan], [
            $breakfast => ['type' => 'amount', 'value' => 100],
            $lunch => ['type' => 'amount', 'value' => 150],
            $nightPlan => ['type' => 'amount', 'value' => 80],
        ]);

        $price = fn (array $optionIds) => $this->actingAs($this->guest, 'sanctum')
            ->postJson('/api/v2/bookings/preview', $this->body(['option_ids' => $optionIds]))->assertOk();

        $this->assertEquals(350, $price([])->json('data.price'));
        $this->assertEquals(500, $price([$lunch])->json('data.price'), 'the lunch is added to the flat price');
        $this->assertEquals(600, $price([$breakfast, $lunch])->json('data.price'), 'meals add up — it is a multiple choice');
        $this->assertEquals(350, $price([$nightPlan])->json('data.price'), 'a night meal plan means nothing for a few hours');
        $this->assertSame('day_use', $price([$lunch])->json('data.price_breakdown.source'));

        // the guest's form tells each group which form it belongs to
        $modifiers = collect($this->actingAs($this->guest, 'sanctum')->getJson("/api/v2/bookings/form/{$this->hotel->id}")->assertOk()->json('data.modifiers'));
        $this->assertSame('day_use', $modifiers->firstWhere('option_id', $lunch)['applies_to']);
        $this->assertSame('night', $modifiers->firstWhere('option_id', $nightPlan)['applies_to']);

        // and the booking carries the meals in its price
        $res = $this->actingAs($this->guest, 'sanctum')->postJson('/api/v2/bookings', $this->body(['option_ids' => [$lunch]]))->assertCreated();
        $this->assertEquals(500, (float) Booking::findOrFail($res->json('data.booking.id'))->price);
    }

    public function test_the_design_options_are_choices_of_the_right_trades_only(): void
    {
        $this->seed(\Database\Seeders\BookingDesignOptionsSeeder::class);

        $linked = fn (string $group, string $trade) => DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->join('category_children_master as c', 'c.id', '=', 'cco.child_id')
            ->where('g.name_ar', $group)->where('c.name_ar', $trade)->exists();

        $this->assertTrue($linked('وجبات Day use', 'فندق'));
        $this->assertFalse($linked('وجبات Day use', 'مالك وحدة مصيفية'), 'a private let feeds nobody');
        $this->assertFalse($linked('وجبات Day use', 'عيادة'));
        foreach (['عيادة', 'مركز طبي', 'مستشفى'] as $trade) {
            $this->assertTrue($linked('تخصصات طبية', $trade), $trade);
        }
        $this->assertSame(0, DB::table('option_groups')->where('name_ar', 'نوع الزيارة')->count(), 'the visit kinds of a clinic are item types already');
        $this->assertTrue($linked('إطلالة الوحدة', 'فندق'));
        $this->assertSame('modifier', DB::table('option_groups')->where('name_ar', 'وجبات Day use')->value('price_role'));
    }

    public function test_the_guest_books_day_use_and_holds_the_room_for_the_window_only(): void
    {
        $this->enable()->assertOk();
        $date = now()->addDays(3)->toDateString();

        $res = $this->actingAs($this->guest, 'sanctum')->postJson('/api/v2/bookings', $this->body())->assertCreated();
        $booking = Booking::findOrFail($res->json('data.booking.id'));

        $this->assertSame("{$date} 09:00:00", $booking->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame("{$date} 18:00:00", $booking->ends_at->format('Y-m-d H:i:s'));
        $this->assertEquals(350, (float) $booking->price);
        $this->assertFalse((bool) $booking->all_day);
        $this->assertTrue((bool) ($booking->meta['day_use'] ?? false));

        // the one room of this type is held for that window: a second guest cannot take the same afternoon…
        $second = $this->actingAs($this->guest, 'sanctum')->postJson('/api/v2/bookings', $this->body())->assertStatus(422);

        // …but the next day is free
        $this->actingAs($this->guest, 'sanctum')->postJson('/api/v2/bookings', $this->body(['date' => now()->addDays(4)->toDateString()]))->assertCreated();
    }

    public function test_a_room_type_without_day_use_refuses_it_and_a_past_window_is_refused(): void
    {
        $this->actingAs($this->guest, 'sanctum')->postJson('/api/v2/bookings', $this->body())->assertStatus(422);

        $this->enable()->assertOk();
        $this->actingAs($this->guest, 'sanctum')->postJson('/api/v2/bookings', $this->body(['date' => now()->subDay()->toDateString()]))->assertStatus(422);
        $this->actingAs($this->guest, 'sanctum')->postJson('/api/v2/bookings', $this->body(['bookable_id' => null]))->assertStatus(422);
    }
}
