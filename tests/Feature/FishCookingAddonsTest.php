<?php

namespace Tests\Feature;

use App\Models\MenuItemExtra;
use App\Models\MenuItemExtraGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «طريقة طهى السمك لدى التاجر فى التسعير: المشوى 50 المقلى 80 الصينية 100 للكيلو — العميل اختار سمك
 * وصينية: السمك وسطر الصينية فى الفاتورة» — المالك، 2026-10-04. A cooking method is a SERVICE of the
 * shop, priced once, per unit bought, added on top of the item. Rolls back.
 */
class FishCookingAddonsTest extends TestCase
{
    use DatabaseTransactions;

    private function shop(): User
    {
        $shop = User::query()->where('type', 'business')->where('category_child_id', 101)->orderBy('id')->first()
            ?: $this->markTestSkipped('No business stands on child #101 (أسماك).');
        DB::table('business_addon_prices')->where('business_id', $shop->id)->delete();
        DB::table('business_working_hours')->where('business_id', $shop->id)->delete();

        return $shop;
    }

    private function method(string $name): int
    {
        return (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')->where('g.name_ar', 'طريقة الطهي')->where('o.name_ar', $name)->value('o.id');
    }

    private function fish(): int
    {
        $shrimp = (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')->where('g.name_ar', 'أنواع الأسماك والمأكولات البحرية')->where('o.name_ar', 'جمبري')->value('o.id');

        return (int) $this->postJson('/api/v2/business/menu/items', ['name_ar' => 'جمبري للطهي', 'base_price' => 300, 'line_option_id' => $shrimp, 'available_quantity' => 50])->assertCreated()->json('data.id');
    }

    private function price(array $prices): array
    {
        return $this->putJson('/api/v2/business/menu/addons', ['prices' => $prices])->assertOk()->json('data.addons');
    }

    public function test_raw_is_the_base_price_and_cleaning_and_cutting_are_services_of_fish_and_poultry(): void
    {
        $this->assertSame(0, DB::table('options')->where('name_ar', 'نيء (بدون طهي)')->count(), 'raw is the base price, not a service');
        $this->assertSame(1, DB::table('options')->where('name_ar', 'مشوي جريل')->count());
        $this->assertSame(1, DB::table('options')->where('name_ar', 'مشوي زيت وليمون')->count());

        Sanctum::actingAs($this->shop());
        $groups = collect($this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/business/menu/addons')->assertOk()->json('data.addons'));
        $prep = $groups->firstWhere('group_name', 'التجهيز');
        $this->assertNotNull($prep, 'the fish shop offers preparation');
        $this->assertSame(['تنظيف', 'تقطيع', 'تنظيف وتقطيع'], array_column($prep['options'], 'name'));

        $poultry = (int) DB::table('service_option_group_placements')->where('option_group_id', DB::table('option_groups')->where('name_ar', 'التجهيز')->value('id'))->where('child_id', 229)->where('usage', 'addon')->count();
        $this->assertSame(1, $poultry, 'and so does the poultry shop');
    }

    public function test_the_shop_lists_its_cooking_methods_and_prices_them_once(): void
    {
        Sanctum::actingAs($this->shop());

        $first = $this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/business/menu/addons')->assertOk()->json('data.addons');
        $this->assertSame('طريقة الطهي', $first[0]['group_name']);
        $this->assertSame(array_fill(0, 7, null), array_column($first[0]['options'], 'price'), 'nothing priced yet');

        $saved = $this->price([$this->method('مشوي') => 50, $this->method('مقلي') => 80, $this->method('صنية بالفرن') => 100]);
        $this->assertEquals([50, null, null, 80, 100, null, null], array_column($saved[0]['options'], 'price'));
    }

    public function test_every_item_of_the_shop_carries_the_priced_methods_as_a_single_choice(): void
    {
        $shop = $this->shop();
        Sanctum::actingAs($shop);
        $before = $this->fish();

        $this->price([$this->method('مشوي') => 50, $this->method('مقلي') => 80, $this->method('صنية بالفرن') => 100]);
        $after = $this->fish(); // an item added AFTER the prices were set

        foreach ([$before, $after] as $item) {
            $group = MenuItemExtraGroup::query()->where('menu_item_id', $item)->first();
            $this->assertSame('طريقة الطهي', $group->name_ar);
            $this->assertTrue($group->isSingle(), 'one cooking method per fish');
            $this->assertEqualsCanonicalizing([50.0, 80.0, 100.0], MenuItemExtra::query()->where('menu_item_id', $item)->active()->pluck('price')->map(fn ($p) => (float) $p)->all());
        }
    }

    public function test_the_invoice_reads_the_fish_and_the_service_as_two_lines(): void
    {
        $shop = $this->shop();
        Sanctum::actingAs($shop);
        $item = $this->fish();
        $this->price([$this->method('صنية بالفرن') => 100]);
        $tray = (int) MenuItemExtra::query()->where('menu_item_id', $item)->where('source_option_id', $this->method('صنية بالفرن'))->value('id');

        Sanctum::actingAs(User::query()->where('type', '!=', 'business')->where('id', '!=', $shop->id)->orderBy('id')->firstOrFail());
        $cart = $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $item, 'qty' => 2, 'extras' => [$tray]])->assertCreated()->json('data.cart');
        $line = collect($cart['items'])->first();

        $this->assertEquals(300.0, $line['base_price'], 'the fish is 300 a kilo');
        $this->assertEquals(400.0, $line['price'], 'with the tray, 400 a kilo');
        $this->assertEquals(800.0, $line['total_price'], '2 kg = 600 of fish + 200 of tray');
        $this->assertCount(1, $line['extras_detail']);
        $this->assertEquals([100, 1, 200], [$line['extras_detail'][0]['unit_price'], $line['extras_detail'][0]['qty'], $line['extras_detail'][0]['total']]);
    }

    public function test_a_method_the_shop_stops_pricing_is_retired_and_a_group_the_merchant_switched_off_stays_off(): void
    {
        $shop = $this->shop();
        Sanctum::actingAs($shop);
        $item = $this->fish();
        $this->price([$this->method('مشوي') => 50, $this->method('مقلي') => 80]);

        $this->price([$this->method('مقلي') => 0]);
        $this->assertSame([50.0], MenuItemExtra::query()->where('menu_item_id', $item)->active()->pluck('price')->map(fn ($p) => (float) $p)->all());

        // The merchant unticks every service on this item: a re-priced shop does not bring them back.
        $this->putJson("/api/v2/business/menu/items/{$item}/addon-options", ['option_ids' => []])->assertOk();
        $this->price([$this->method('مشوي') => 55]);
        $this->assertFalse((bool) MenuItemExtraGroup::query()->where('menu_item_id', $item)->value('is_active'), 'the merchant switched it off for this item');
    }

    public function test_a_trade_that_offers_no_services_has_none(): void
    {
        $shop = User::query()->where('type', 'business')->where('category_child_id', 73)->orderBy('id')->firstOrFail();
        Sanctum::actingAs($shop);

        $this->assertSame([], $this->getJson('/api/v2/business/menu/addons')->assertOk()->json('data.addons'));
    }
}
