<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsMenu;
use Tests\TestCase;

/**
 * «ربط نظام المواصفات مع المنيو» — a menu item may optionally link a real
 * catalog master (the same one retail's «كتالوج تفصيلي» prices — see
 * [[three-catalog-shapes]]), so a mobile/laptop shop's menu item shows a
 * real spec table instead of retyping it. Rolls back.
 */
class MenuCatalogSpecsLinkTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsMenu;

    /** A business whose own retail scope actually carries computers/laptops. */
    private function laptopShop(): User
    {
        return User::query()->where('type', 'business')->where('category_child_id', 69)->orderBy('id')->first()
            ?: $this->markTestSkipped('No business stands on child #69 (computers/laptops retail scope).');
    }

    public function test_merchant_can_look_up_and_link_a_real_catalog_product(): void
    {
        $business = $this->laptopShop();
        Sanctum::actingAs($business);

        $lookup = $this->getJson('/api/v2/business/menu/catalog-lookup?q=' . urlencode('Dell Latitude'))
            ->assertOk()->json('data.items');
        $this->assertNotEmpty($lookup, 'the seeded laptop is findable by name');
        $laptopId = $lookup[0]['id'];

        $item = $this->postJson('/api/v2/business/menu/items', [
            'name_ar' => 'ديل لاتيتيود',
            'base_price' => 22000,
            'catalog_product_id' => $laptopId,
        ])->assertCreated()->json('data');

        $this->assertSame($laptopId, $item['catalog_product_id']);
        $this->assertNotNull($item['catalog_product']);
        $this->assertNotEmpty($item['catalog_product']['specs'], 'the linked laptop carries its spec table');
        $byCode = collect($item['catalog_product']['specs'])->keyBy('code');
        $this->assertArrayHasKey('processor', $byCode);
    }

    /**
     * A merchant picking a product in «التسعير والتفاصيل» sees its spec table
     * BEFORE saving anything — the search result itself carries `specs`, not
     * just `id`/`name`/`image`. See [[tech-spec-menu-implementation]].
     */
    public function test_the_catalog_lookup_result_carries_the_products_specs(): void
    {
        $business = $this->laptopShop();
        Sanctum::actingAs($business);

        $lookup = $this->getJson('/api/v2/business/menu/catalog-lookup?q=' . urlencode('Dell Latitude'))
            ->assertOk()->json('data.items');

        $this->assertNotEmpty($lookup);
        $this->assertNotEmpty($lookup[0]['specs'], 'the lookup result must carry the products spec table');
        $this->assertArrayHasKey('processor', collect($lookup[0]['specs'])->keyBy('code'));
    }

    /**
     * «حالة اختبار على المحاكي» — المالك، 2026-09-30: an unscoped lookup let
     * a mobiles shop's product picker turn up refrigerators and spice jars
     * right alongside phones. Scoped to the owner's own retail catalog
     * (`catalogScope()`, mirrors BusinessRetailListingController's own
     * `retailScope()`) — a laptop shop's empty-query lookup must not surface
     * a phone-only product, and vice versa.
     */
    public function test_the_catalog_lookup_is_scoped_to_the_owners_own_retail_catalog(): void
    {
        $laptopShop = $this->laptopShop();
        $mobileShop = User::query()->where('type', 'business')->where('category_child_id', 186)->orderBy('id')->first()
            ?: $this->markTestSkipped('No business stands on child #186 (mobiles_accessories).');

        $phoneName = (string) DB::table('catalog_products')->where('bim_code', 'BIM-RT-MOBI-020')->value('name_ar');
        $this->assertNotSame('', $phoneName, 'the seeded phone exists');

        Sanctum::actingAs($laptopShop);
        $lookup = $this->getJson('/api/v2/business/menu/catalog-lookup?q=' . urlencode($phoneName))
            ->assertOk()->json('data.items');
        $this->assertEmpty($lookup, 'a laptop shop must not see a phone-only product in its own lookup');

        Sanctum::actingAs($mobileShop);
        $lookup = $this->getJson('/api/v2/business/menu/catalog-lookup?q=' . urlencode($phoneName))
            ->assertOk()->json('data.items');
        $this->assertNotEmpty($lookup, 'the mobiles shop must still find its own phone');
    }

    /**
     * «بالضغط على اختار منتجا بتفتح منتجات ابو عوف وليس الموبيلات» —
     * المالك، 2026-09-30: this exact live bug. Retail sat INACTIVE for the
     * mobiles child (an unrelated config edit elsewhere), and the old
     * `catalogScope()` read `allowed_item_types` through `servicesForChild()`
     * — which requires the retail LINK active — so it silently fell back to
     * unscoped and a phone search surfaced spice jars. `allowed_item_types`
     * is a taxonomy fact about what this child SELLS, not a live toggle of
     * whether retail-the-sales-channel happens to be on; a menu-only
     * detailed business (retail off, menu on) must still scope correctly.
     */
    public function test_the_catalog_lookup_stays_scoped_even_when_retail_itself_is_inactive(): void
    {
        $mobileShop = User::query()->where('type', 'business')->where('category_child_id', 186)->orderBy('id')->first()
            ?: $this->markTestSkipped('No business stands on child #186 (mobiles_accessories).');

        $retailId = DB::table('platform_services')->where('key', 'retail')->value('id');
        DB::table('category_platform_services')
            ->where('child_id', 186)->where('platform_service_id', $retailId)
            ->update(['is_active' => 0]);
        DB::table('category_service_configs')
            ->where('child_id', 186)->where('platform_service_id', $retailId)
            ->update(['is_active' => 0]);

        $phoneName = (string) DB::table('catalog_products')->where('bim_code', 'BIM-RT-MOBI-020')->value('name_ar');
        $this->assertNotSame('', $phoneName, 'the seeded phone exists');

        Sanctum::actingAs($mobileShop);
        $lookup = $this->getJson('/api/v2/business/menu/catalog-lookup?q=' . urlencode('Abu Auf'))
            ->assertOk()->json('data.items');
        $this->assertEmpty($lookup, 'a mobiles shop must not see a grocery product even with retail inactive');

        $lookup = $this->getJson('/api/v2/business/menu/catalog-lookup?q=' . urlencode($phoneName))
            ->assertOk()->json('data.items');
        $this->assertNotEmpty($lookup, 'the mobiles shop must still find its own phone with retail inactive');
    }

    public function test_the_public_menu_discovery_shows_the_linked_products_specs(): void
    {
        $business = User::query()->where('type', 'business')->orderBy('id')->firstOrFail();
        Sanctum::actingAs($business);

        $laptopId = (int) DB::table('catalog_products')->where('name_en', 'Dell Latitude Laptop 14 inch')->value('id');
        $this->assertNotNull($laptopId, 'the seeded laptop exists');

        $item = $this->seedMenuItem($business->id, null, 22000, 'ديل لاتيتيود');
        $item->update(['catalog_product_id' => $laptopId]);

        $data = $this->withHeaders(['Accept-Language' => 'ar'])
            ->getJson('/api/v2/discovery/menu/' . $business->id)
            ->assertOk()->json('data');

        $row = collect($data['sections'] ?? [])->flatMap(fn ($s) => $s['items'] ?? [])->firstWhere('id', $item->id);
        $this->assertNotNull($row, 'the linked item appears in the public menu');
        $this->assertNotEmpty($row['specs']);
        $this->assertSame('Intel Core i7-1355U', collect($row['specs'])->firstWhere('code', 'processor')['value']);
    }

    /**
     * The «جديد»/«مستعمل» badge TechProductDetail shows beside the price
     * comes from the item's own «حالة المنتج» modifier — matched by group
     * name, never a hardcoded option id. See [[tech-spec-menu-implementation]].
     */
    public function test_the_public_menu_discovery_shows_the_items_condition(): void
    {
        $business = User::query()->where('type', 'business')->orderBy('id')->firstOrFail();
        Sanctum::actingAs($business);

        $used = (int) DB::table('options')->where('group_id', 48)->where('name_ar', 'مستعمل')->value('id');
        $this->assertNotSame(0, $used, 'the «حالة المنتج» group must carry «مستعمل»');

        $item = $this->seedMenuItem($business->id, null, 15000, 'موبايل مستعمل');
        $item->syncOfferingOptions(null, [$used], []);

        $data = $this->getJson('/api/v2/discovery/menu/' . $business->id)->assertOk()->json('data');

        $row = collect($data['sections'] ?? [])->flatMap(fn ($s) => $s['items'] ?? [])->firstWhere('id', $item->id);
        $this->assertNotNull($row, 'the item appears in the public menu');
        $this->assertNotNull($row['condition'], 'the condition must be exposed to the customer');
        $this->assertSame($used, $row['condition']['id']);
    }
}
