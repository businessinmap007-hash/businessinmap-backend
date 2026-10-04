<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\MenuItemExtra;
use App\Models\MenuItemExtraGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «طريقة الطهى ممكن تستخدم مع مطاعم وتجهيز لحوم ودواجن» — المالك، 2026-10-04. The cooking method is a service of
 * any kitchen: a butcher and a poultry shop carry it on every item; a restaurant's merchant chooses the dishes
 * that offer it (the grill, not the salad). Rolls back.
 */
class CookingMethodForEveryKitchenTest extends TestCase
{
    use DatabaseTransactions;

    /** A merchant of [$child] — one is borrowed for the request when the trade has no account (rolled back). */
    private function merchant(int $child): User
    {
        $shop = User::query()->where('type', 'business')->where('category_child_id', $child)->orderBy('id')->first()
            ?? tap(User::query()->where('type', 'business')->orderBy('id')->firstOrFail(), fn ($u) => $u->forceFill(['category_child_id' => $child])->save());
        DB::table('business_addon_prices')->where('business_id', $shop->id)->delete();
        DB::table('business_working_hours')->where('business_id', $shop->id)->delete();

        return $shop;
    }

    private function method(string $name): int
    {
        return (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')->where('g.name_ar', 'طريقة الطهي')->where('o.name_ar', $name)->value('o.id');
    }

    private function group(): int
    {
        return (int) DB::table('option_groups')->where('name_ar', 'طريقة الطهي')->value('id');
    }

    private function item(string $name): int
    {
        return (int) $this->postJson('/api/v2/business/menu/items', ['name_ar' => $name, 'base_price' => 200])->assertCreated()->json('data.id');
    }

    private function price(array $prices): void
    {
        $this->putJson('/api/v2/business/menu/addons', ['prices' => $prices])->assertOk();
    }

    public function test_a_butcher_and_a_poultry_shop_carry_the_method_on_every_item(): void
    {
        foreach ([553, 229] as $child) {
            Sanctum::actingAs($this->merchant($child));
            $this->price([$this->method('مشوي') => 60, $this->method('مسلوق') => 40]);
            $item = $this->item('صنف لحم ' . $child);

            $group = MenuItemExtraGroup::query()->where('menu_item_id', $item)->first();
            $this->assertSame('طريقة الطهي', $group->name_ar, "#{$child} cooks what it sells");
            $this->assertEqualsCanonicalizing([60.0, 40.0], MenuItemExtra::query()->where('menu_item_id', $item)->active()->pluck('price')->map(fn ($p) => (float) $p)->all());
        }
    }

    public function test_a_restaurant_offers_it_only_on_the_dishes_its_merchant_chooses(): void
    {
        Sanctum::actingAs($this->merchant(245));
        $this->price([$this->method('مشوي') => 70, $this->method('مقلي') => 90]);
        $grill = $this->item('كباب');
        $salad = $this->item('سلطة');

        $this->assertNull(MenuItemExtraGroup::query()->where('menu_item_id', $grill)->first(), 'nothing is offered until the merchant says so');

        $choices = $this->putJson("/api/v2/business/menu/items/{$grill}/addons", ['group_ids' => [$this->group()]])->assertOk()->json('data.addon_choices');
        $this->assertTrue($choices[0]['enabled']);

        $this->assertTrue((bool) MenuItemExtraGroup::query()->where('menu_item_id', $grill)->value('is_active'));
        $this->assertEqualsCanonicalizing([70.0, 90.0], MenuItemExtra::query()->where('menu_item_id', $grill)->active()->pluck('price')->map(fn ($p) => (float) $p)->all());
        $this->assertNull(MenuItemExtraGroup::query()->where('menu_item_id', $salad)->first(), 'the salad is not cooked by method');

        // The item screen reads which services the dish offers.
        $this->assertSame([true], array_column($this->getJson("/api/v2/business/menu/items/{$grill}")->assertOk()->json('data.addon_choices'), 'enabled'));
        $this->assertSame([false], array_column($this->getJson("/api/v2/business/menu/items/{$salad}")->assertOk()->json('data.addon_choices'), 'enabled'));

        // Switching it off retires the service on that dish only.
        $this->putJson("/api/v2/business/menu/items/{$grill}/addons", ['group_ids' => []])->assertOk();
        $this->assertFalse((bool) MenuItemExtraGroup::query()->where('menu_item_id', $grill)->value('is_active'));
    }

    public function test_a_price_change_reaches_the_dishes_that_offer_it_and_not_the_others(): void
    {
        Sanctum::actingAs($this->merchant(245));
        $this->price([$this->method('مشوي') => 70]);
        $grill = $this->item('مشاوي');
        $salad = $this->item('سلطة خضراء');
        $this->putJson("/api/v2/business/menu/items/{$grill}/addons", ['group_ids' => [$this->group()]])->assertOk();

        $this->price([$this->method('مشوي') => 75]);

        $this->assertEquals([75.0], MenuItemExtra::query()->where('menu_item_id', $grill)->active()->pluck('price')->map(fn ($p) => (float) $p)->all());
        $this->assertNull(MenuItemExtraGroup::query()->where('menu_item_id', $salad)->first());
    }

    public function test_a_shop_that_carries_it_on_every_item_has_no_per_item_switch(): void
    {
        Sanctum::actingAs($this->merchant(101));
        $item = $this->item('سمك للتجربة');

        $this->assertSame([], $this->getJson("/api/v2/business/menu/items/{$item}")->assertOk()->json('data.addon_choices'));
    }

    public function test_a_service_the_trade_does_not_offer_cannot_be_switched_on(): void
    {
        Sanctum::actingAs($this->merchant(73)); // cosmetics
        $item = $this->item('كريم');

        $this->putJson("/api/v2/business/menu/items/{$item}/addons", ['group_ids' => [$this->group()]])->assertOk();

        $this->assertNull(MenuItemExtraGroup::query()->where('menu_item_id', $item)->first());
        $this->assertSame(0, MenuItem::query()->findOrFail($item)->extraGroups()->count());
    }
}
