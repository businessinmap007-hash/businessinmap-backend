<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Catalog\ApplianceSpecExtractor;
use Database\Seeders\ApplianceSpecsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsRetailCatalog;
use Tests\TestCase;

/**
 * Product page for an appliance: a structured spec table (type, operation,
 * capacity, power…) read off the catalog master, shown with the brand first —
 * the «مواصفات» half of the Amazon-style product page. Rolls back.
 */
class ApplianceSpecsTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRetailCatalog;

    public function test_the_extractor_reads_type_operation_and_measures_off_the_name(): void
    {
        $x = new ApplianceSpecExtractor();

        $fridge = $x->extract('Toshiba El Araby No-Frost Refrigerator 16ft');
        $this->assertSame('Refrigerator', $fridge['appliance_type']['en']);
        $this->assertSame('نوفروست', $fridge['operation_type']['ar']);
        $this->assertSame(16.0, $fridge['capacity_cu_ft']['number']);

        $ac = $x->extract('Sharp Split Air Conditioner 2.25HP');
        $this->assertSame(2.25, $ac['power_hp']['number']);
        $this->assertSame('Split', $ac['operation_type']['en']);

        $washer = $x->extract('Kiriazi Semi-Automatic Washing Machine 9kg');
        $this->assertSame('Semi-automatic', $washer['operation_type']['en']);
        $this->assertSame(9.0, $washer['wash_capacity_kg']['number']);

        $this->assertSame(55.0, $x->extract('LG Smart TV 55 inch')['screen_inches']['number']);
        $this->assertSame(5.0, $x->extract('Kiriazi Gas Cooker 5 Burners')['burners']['number']);

        // A name with no measure yields only what it states.
        $this->assertArrayNotHasKey('capacity_liters', $x->extract('Fresh Stand Fan'));
    }

    public function test_the_storefront_and_the_product_page_carry_the_spec_table(): void
    {
        $product = $this->makeCatalogProduct('home_appliances', 'ثلاجة اختبار المواصفات');
        DB::table('catalog_products')->where('id', $product)->update(['name_en' => 'Test No-Frost Refrigerator 18ft']);

        (new ApplianceSpecsSeeder())->run();
        (new ApplianceSpecsSeeder())->run(); // idempotent

        $this->assertSame(
            3,
            DB::table('catalog_product_attribute_values')->where('product_id', $product)->count(),
            'type + operation + capacity, once each'
        );

        $seller = User::query()->where('type', 'business')->orderBy('id')->first();
        DB::table('business_catalog_listings')->insert([
            'business_id' => $seller->id,
            'catalog_product_id' => $product,
            'price' => 9000,
            'currency' => 'EGP',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rows = collect($this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/discovery/retail/business/' . $seller->id)
            ->assertOk()->json('data.listings'))->where('product.id', $product);

        $specs = $rows->first()['product']['specs'];
        $byCode = collect($specs)->keyBy('code');
        $this->assertSame('ثلاجة', $byCode['appliance_type']['value']);
        $this->assertSame('نوفروست', $byCode['operation_type']['value']);
        $this->assertSame('18 قدم', $byCode['capacity_cu_ft']['value']);

        $page = $this->getJson('/api/v2/discovery/retail/products/' . $product)->assertOk()->json('data.product.specs');
        $this->assertContains('capacity_cu_ft', array_column($page, 'code'));
    }
}
