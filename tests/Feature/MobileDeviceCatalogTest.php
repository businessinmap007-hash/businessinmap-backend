<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «يجب الفصل بين الموبيلات والتابلت وايضا سمارت واتش … اختار الماركة اوبو
 * … لو اخترت F يظهر كل الموديلات F … اضافة فون جديد … يدويا اذا لم يكن
 * موجود» — المالك، 2026-10-01. Rolls back.
 */
class MobileDeviceCatalogTest extends TestCase
{
    use DatabaseTransactions;

    private function phoneShop(): User
    {
        return User::query()->where('type', 'business')->where('category_child_id', 186)->orderBy('id')->first()
            ?: $this->markTestSkipped('No business stands on child #186.');
    }

    private function branch(string $name): int
    {
        return (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', 'أجهزة الموبايل')->where('o.name_ar', $name)->value('o.id')
            ?: $this->markTestSkipped("No «{$name}» branch.");
    }

    private function lookup(array $query): array
    {
        return $this->getJson('/api/v2/business/menu/catalog-lookup?' . http_build_query($query))->assertOk()->json('data');
    }

    public function test_each_device_branch_offers_only_its_own_devices(): void
    {
        Sanctum::actingAs($this->phoneShop());

        foreach (['موبايل', 'تابلت', 'ساعة ذكية'] as $name) {
            $branch = $this->branch($name);
            $ids = array_column($this->lookup(['line_option_id' => $branch])['items'], 'id');

            $this->assertNotEmpty($ids, "«{$name}» opens onto an empty picker");
            $this->assertSame(
                [$branch],
                DB::table('catalog_products')->whereIn('id', $ids)->distinct()->pluck('line_option_id')->map(fn ($id) => (int) $id)->all(),
                "«{$name}» offers another branch's products"
            );
        }
    }

    public function test_brand_then_series_narrows_the_picker(): void
    {
        Sanctum::actingAs($this->phoneShop());
        $phones = $this->branch('موبايل');

        $oppo = (int) DB::table('catalog_brands')->where('name_en', 'Oppo')->value('id');
        $this->assertContains($oppo, array_column($this->lookup(['line_option_id' => $phones])['facets']['brands'], 'id'));

        $byBrand = $this->lookup(['line_option_id' => $phones, 'brand_id' => $oppo]);
        $this->assertContains('F', array_column($byBrand['facets']['series'], 'name'));
        $this->assertSame([$oppo], array_values(array_unique(array_column($byBrand['items'], 'brand_id'))));

        $f = $this->lookup(['line_option_id' => $phones, 'brand_id' => $oppo, 'series' => 'F'])['items'];
        $this->assertNotEmpty($f);
        $this->assertSame(['F'], array_values(array_unique(array_column($f, 'series'))));
    }

    public function test_a_missing_model_is_added_pending_and_only_its_proposer_sees_it(): void
    {
        $shop = $this->phoneShop();
        Sanctum::actingAs($shop);
        $phones = $this->branch('موبايل');
        $oppo = (int) DB::table('catalog_brands')->where('name_en', 'Oppo')->value('id');

        $product = $this->postJson('/api/v2/business/menu/catalog-products', [
            'line_option_id' => $phones, 'brand_id' => $oppo, 'series' => 'F', 'model' => 'F99 Test Edition',
            'ram_gb' => 8, 'storage' => '256GB', 'screen_inches' => 6.7,
        ])->assertCreated()->json('data.product');

        $this->assertTrue($product['pending']);
        $this->assertSame('F', $product['series']);
        $this->assertContains('256GB', array_column($product['specs'], 'value'));

        // Proposing it again is the same product, not a duplicate.
        $again = $this->postJson('/api/v2/business/menu/catalog-products', [
            'line_option_id' => $phones, 'brand_id' => $oppo, 'model' => 'F99 Test Edition',
        ])->assertOk()->json('data.product');
        $this->assertSame($product['id'], $again['id']);

        $mine = array_column($this->lookup(['line_option_id' => $phones, 'brand_id' => $oppo, 'series' => 'F'])['items'], 'id');
        $this->assertContains($product['id'], $mine);

        $this->postJson('/api/v2/business/menu/items', [
            'name_ar' => 'اوبو F99', 'base_price' => 9000, 'catalog_product_id' => $product['id'], 'line_option_id' => $phones,
        ])->assertCreated();

        // Another shop neither finds nor links an unreviewed proposal.
        $other = User::query()->where('type', 'business')->where('id', '!=', $shop->id)->orderBy('id')->first();
        Sanctum::actingAs($other);

        $this->assertNotContains($product['id'], array_column($this->lookup(['q' => 'F99 Test'])['items'], 'id'));
        $this->postJson('/api/v2/business/menu/items', [
            'name_ar' => 'منسوخ', 'base_price' => 1, 'catalog_product_id' => $product['id'],
        ])->assertUnprocessable();
    }

    public function test_the_seeder_is_idempotent(): void
    {
        $before = DB::table('catalog_products')->count();
        $this->seed(\Database\Seeders\MobileDeviceCatalogSeeder::class);
        $this->assertSame($before, DB::table('catalog_products')->count());
    }
}
