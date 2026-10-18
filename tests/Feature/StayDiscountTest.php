<?php

namespace Tests\Feature;

use App\Models\BookableItem;
use App\Models\BusinessServicePrice;
use App\Models\PlatformService;
use App\Models\User;
use App\Services\BookingStayDiscountService;
use App\Services\ServiceExecutionEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «خصم الأسبوع والشهر» — a unit let by the period costs less per day the longer it is kept. The day price and the
 * season rules stay what they were; the total takes the larger tier the stay reaches off.
 */
class StayDiscountTest extends TestCase
{
    use DatabaseTransactions;

    private const ROOT = 24;
    private const CHILD = 536;
    private const SINGLE_ROOM = 965;

    private function owner(): User
    {
        return User::create([
            'name' => 'Zz Let ' . uniqid(),
            'email' => 'zz-stay-' . uniqid() . '@test.local',
            'phone' => '01' . random_int(100000000, 999999999),
            'password' => Hash::make('Passw0rdTest'),
            'type' => User::TYPE_BUSINESS,
            'category_id' => self::ROOT,
            'category_child_id' => self::CHILD,
            'api_token' => 'zz' . uniqid() . bin2hex(random_bytes(8)),
        ]);
    }

    private function serviceId(): int
    {
        return (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->where('is_active', 1)->value('id');
    }

    /** A unit let at 100 a day. */
    private function unit(User $owner): BookableItem
    {
        BusinessServicePrice::create([
            'business_id' => $owner->id, 'child_id' => self::CHILD, 'service_id' => $this->serviceId(),
            'bookable_item_type' => 'booking_stay', 'line_option_id' => self::SINGLE_ROOM,
            'price' => 100, 'currency' => 'EGP', 'is_active' => 1,
        ]);

        return BookableItem::create([
            'business_id' => $owner->id, 'service_id' => $this->serviceId(), 'item_type' => 'booking_stay',
            'line_option_id' => self::SINGLE_ROOM, 'code' => 'Z-' . uniqid(), 'quantity' => 1, 'is_active' => 1,
        ]);
    }

    private function total(BookableItem $unit, int $nights): array
    {
        $price = app(\App\Services\BusinessServicePriceResolver::class)->resolveForBookableItem($unit);
        $from = Carbon::today()->addDays(3);

        return app(ServiceExecutionEngine::class)->resolvePriceBreakdown(
            service: PlatformService::findOrFail($price->service_id),
            businessPrice: $price,
            bookable: $unit->fresh(),
            quantity: 1,
            pricingDate: $from,
            optionIds: [],
            until: $from->copy()->addDays($nights),
        );
    }

    public function test_a_week_and_a_month_take_their_percentage_off_the_total(): void
    {
        $unit = $this->unit($this->owner());
        app(BookingStayDiscountService::class)->save($unit, 10, 25);

        $three = $this->total($unit, 3);
        $this->assertEquals(300, $three['final_price'], 'under a week nothing is taken off');
        $this->assertEquals(0, $three['stay_discount_percent']);

        $week = $this->total($unit, 7);
        $this->assertEquals(700, $week['original_price']);
        $this->assertEquals(10, $week['stay_discount_percent']);
        $this->assertEquals(630, $week['final_price']);

        $ten = $this->total($unit, 10);
        $this->assertEquals(900, $ten['final_price'], 'ten nights still sit in the weekly tier');

        $month = $this->total($unit, 30);
        $this->assertEquals(3000, $month['original_price']);
        $this->assertEquals(25, $month['stay_discount_percent']);
        $this->assertEquals(2250, $month['final_price']);
        $this->assertEquals(750, $month['discount_amount']);
    }

    public function test_a_unit_with_no_discounts_prices_as_before(): void
    {
        $unit = $this->unit($this->owner());

        $month = $this->total($unit, 30);

        $this->assertEquals(3000, $month['final_price']);
        $this->assertEquals(0, $month['stay_discount_amount']);
    }

    public function test_the_owner_sets_and_reads_them_and_only_for_his_own_unit(): void
    {
        $owner = $this->owner();
        $unit = $this->unit($owner);
        Sanctum::actingAs($owner);

        $this->getJson("/api/v2/business/bookable-items/{$unit->id}/stay-discounts")->assertOk()
            ->assertJsonPath('data.stay_discounts.weekly_percent', 0);

        $this->putJson("/api/v2/business/bookable-items/{$unit->id}/stay-discounts", ['weekly_percent' => 12.5, 'monthly_percent' => 30])
            ->assertOk()->assertJsonPath('data.stay_discounts.monthly_percent', 30);

        $this->assertEquals(12.5, app(BookingStayDiscountService::class)->settings($unit->fresh())['weekly_percent']);

        $this->putJson("/api/v2/business/bookable-items/{$unit->id}/stay-discounts", ['weekly_percent' => 90])->assertStatus(422);

        Sanctum::actingAs($this->owner());
        $this->putJson("/api/v2/business/bookable-items/{$unit->id}/stay-discounts", ['weekly_percent' => 5])->assertNotFound();
    }
}
