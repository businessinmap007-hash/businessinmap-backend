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

    public function test_merchant_can_look_up_and_link_a_real_catalog_product(): void
    {
        $business = User::query()->where('type', 'business')->orderBy('id')->firstOrFail();
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
        $business = User::query()->where('type', 'business')->orderBy('id')->firstOrFail();
        Sanctum::actingAs($business);

        $lookup = $this->getJson('/api/v2/business/menu/catalog-lookup?q=' . urlencode('Dell Latitude'))
            ->assertOk()->json('data.items');

        $this->assertNotEmpty($lookup);
        $this->assertNotEmpty($lookup[0]['specs'], 'the lookup result must carry the products spec table');
        $this->assertArrayHasKey('processor', collect($lookup[0]['specs'])->keyBy('code'));
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
}
