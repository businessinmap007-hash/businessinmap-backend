<?php

namespace Tests\Feature;

use App\Models\MenuItemExtra;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «اجعل الإضافات تشيك بوكس، اختار منها ما هو متاح لهذا النوع» — المالك، 2026-10-05. The shop prices its services
 * once; each item ticks the ones it offers. Rolls back.
 */
class ItemTicksItsServicesTest extends TestCase
{
    use \Tests\Concerns\AnswersDelivery;
    use DatabaseTransactions;

    private User $shop;
    private int $item;
    private int $grill;
    private int $fry;
    private int $tray;
    private int $line;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = User::query()->where('type', 'business')->where('category_child_id', 101)->orderBy('id')->firstOrFail();
        DB::table('business_addon_prices')->where('business_id', $this->shop->id)->delete();
        $method = fn (string $name) => (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')->where('g.name_ar', 'طريقة الطهي')->where('o.name_ar', $name)->value('o.id');
        $this->grill = $method('مشوي');
        $this->fry = $method('مقلي');
        $this->tray = $method('صنية بالفرن');
        $this->line = $mullet = (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')->where('g.name_ar', 'أنواع الأسماك والمأكولات البحرية')->where('o.name_ar', 'أسماك بوري')->value('o.id');

        Sanctum::actingAs($this->shop);
        $this->putJson('/api/v2/business/menu/addons', ['prices' => [$this->grill => 100, $this->fry => 80, $this->tray => 120]])->assertOk();
        $this->item = (int) $this->postJson('/api/v2/business/menu/items', ['name_ar' => 'بورى للتجربة', 'base_price' => 300, 'line_option_id' => $mullet])->assertCreated()->json('data.id');
    }

    private function services(): array
    {
        $groups = collect($this->withHeaders(['Accept-Language' => 'ar'])->getJson("/api/v2/business/menu/items/{$this->item}")->assertOk()->json('data.addon_services'));

        return $groups->firstWhere('group_name', 'طريقة الطهي')['options'];
    }

    private function activeExtras(): array
    {
        return MenuItemExtra::query()->where('menu_item_id', $this->item)->where('is_active', true)->pluck('source_option_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
    }

    public function test_a_new_item_offers_every_priced_service_ticked(): void
    {
        $options = collect($this->services());

        $this->assertEqualsCanonicalizing([$this->grill, $this->fry, $this->tray], $options->pluck('id')->all(), 'only what the shop prices is listed');
        $this->assertTrue($options->every(fn ($o) => $o['enabled']));
    }

    public function test_unticking_a_service_takes_it_off_that_item_only(): void
    {
        $other = (int) $this->postJson('/api/v2/business/menu/items', ['name_ar' => 'صنف آخر', 'base_price' => 100, 'line_option_id' => $this->line])->assertCreated()->json('data.id');

        $saved = $this->putJson("/api/v2/business/menu/items/{$this->item}/addon-options", ['option_ids' => [$this->grill, $this->fry]])->assertOk()->json('data.addon_services');

        $flags = collect($saved[0]['options'])->pluck('enabled', 'id')->all();
        $this->assertSame([$this->grill => true, $this->fry => true, $this->tray => false], $flags);
        $this->assertEqualsCanonicalizing([$this->grill, $this->fry], $this->activeExtras());
        $this->assertSame(3, MenuItemExtra::query()->where('menu_item_id', $other)->where('is_active', true)->count(), 'the other item keeps all three');
    }

    public function test_the_choice_survives_a_change_of_the_shops_prices(): void
    {
        $this->putJson("/api/v2/business/menu/items/{$this->item}/addon-options", ['option_ids' => [$this->grill]])->assertOk();
        $this->putJson('/api/v2/business/menu/addons', ['prices' => [$this->grill => 110, $this->fry => 85, $this->tray => 125]])->assertOk();

        $this->assertSame([$this->grill], $this->activeExtras(), 'a re-priced shop does not re-offer what the item turned off');
        $this->assertEquals(110, MenuItemExtra::query()->where('menu_item_id', $this->item)->where('source_option_id', $this->grill)->value('price'));
    }

    public function test_nothing_ticked_leaves_no_empty_choice_on_the_customers_sheet(): void
    {
        $this->putJson("/api/v2/business/menu/items/{$this->item}/addon-options", ['option_ids' => []])->assertOk();

        $this->assertSame([], $this->activeExtras());
        $this->assertSame(0, DB::table('menu_item_extra_groups')->where('menu_item_id', $this->item)->where('is_active', 1)->count());
    }

    public function test_a_customer_cannot_order_a_service_the_item_does_not_offer(): void
    {
        $fry = (int) MenuItemExtra::query()->where('menu_item_id', $this->item)->where('source_option_id', $this->fry)->value('id');
        $grill = (int) MenuItemExtra::query()->where('menu_item_id', $this->item)->where('source_option_id', $this->grill)->value('id');
        $this->putJson("/api/v2/business/menu/items/{$this->item}/addon-options", ['option_ids' => [$this->grill]])->assertOk();

        Sanctum::actingAs(User::query()->where('type', '!=', 'business')->where('id', '!=', $this->shop->id)->orderBy('id')->firstOrFail());

        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $this->item, 'qty' => 1, 'extras' => [$fry]])->assertUnprocessable();
        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $this->item, 'qty' => 1, 'extras' => [$grill]])->assertCreated();
    }
}
