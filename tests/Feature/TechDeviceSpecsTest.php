<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\TechDeviceSpecsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «كتالوج تفصيلي» applied to real laptop and phone catalog masters (not
 * name-parsed like appliances — a curated lookup, since "HP ProBook 15.6
 * inch" says nothing about its CPU). Both children (69 «أجهزه كمبيوتر» and
 * 186 «موبيلات و اكسسوار») already carry the condition/payment option groups
 * and the retail item-type scope, so this only proves the spec table itself.
 * Rolls back.
 */
class TechDeviceSpecsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_real_laptop_and_phone_carry_their_curated_specs(): void
    {
        (new TechDeviceSpecsSeeder())->run();
        (new TechDeviceSpecsSeeder())->run(); // idempotent

        $laptop = DB::table('catalog_products')->where('name_en', 'Dell Latitude Laptop 14 inch')->first();
        $this->assertNotNull($laptop, 'the seeded laptop catalog master exists');
        $this->assertSame(
            5,
            DB::table('catalog_product_attribute_values')->where('product_id', $laptop->id)->count(),
            'processor + ram + storage + screen + os, once each'
        );

        $phone = DB::table('catalog_products')->where('name_en', 'Apple iPhone 15 128GB')->first();
        $this->assertNotNull($phone);

        $laptopSeller = User::query()->where('type', 'business')->where('category_child_id', 69)->first();
        $laptopSeller ??= tap(User::query()->where('type', 'business')->orderBy('id')->firstOrFail(), function ($u) {
            $u->category_child_id = 69;
            $u->category_id = 17;
            $u->save();
        });

        DB::table('business_catalog_listings')->insert([
            'business_id' => $laptopSeller->id,
            'catalog_product_id' => $laptop->id,
            'price' => 22000,
            'currency' => 'EGP',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($laptopSeller);
        $data = $this->withHeaders(['Accept-Language' => 'ar'])
            ->getJson('/api/v2/discovery/retail/business/' . $laptopSeller->id)
            ->assertOk()->json('data.listings');

        $row = collect($data)->firstWhere('product.id', $laptop->id);
        $this->assertNotNull($row, 'the listing carries the laptop product');
        $byCode = collect($row['product']['specs'])->keyBy('code');
        $this->assertSame('Intel Core i7-1355U', $byCode['processor']['value']);
        $this->assertSame('512GB SSD', $byCode['storage']['value']);

        $page = $this->getJson('/api/v2/discovery/retail/products/' . $phone->id)->assertOk()->json('data.product.specs');
        $this->assertContains('processor', array_column($page, 'code'));
    }
}
