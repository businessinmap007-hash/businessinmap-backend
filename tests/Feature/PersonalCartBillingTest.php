<?php

namespace Tests\Feature;

use App\Models\BusinessCatalogListing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsMenu;
use Tests\Concerns\SeedsRetailCatalog;
use Tests\TestCase;

/**
 * Personal cart billing parity with the shared cart: menu (food) lines carry the
 * service fee + tax (honouring inclusive flags); retail lines are billed plain.
 */
class PersonalCartBillingTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsMenu;
    use SeedsRetailCatalog;

    private User $customer;
    private User $biz;

    protected function setUp(): void
    {
        parent::setUp();
        config(['bim.menu_tax_rate_percent' => 14]);

        $this->biz = User::query()->where('type', 'business')->firstOrFail();
        $this->customer = User::query()->where('id', '!=', $this->biz->id)->orderBy('id')->firstOrFail();

        $menuId = (int) DB::table('platform_services')->where('key', 'menu')->value('id');
        $childId = (int) DB::table('category_children_master')
            ->whereNotIn('id', function ($q) use ($menuId) {
                $q->from('category_child_service_fees')->where('platform_service_id', $menuId)->select('child_id');
            })
            ->orderBy('id')->value('id');
        if (! $childId) {
            $this->markTestSkipped('No free category child for the fee fixture.');
        }

        $this->biz->forceFill(['category_child_id' => $childId])->save();
        DB::table('category_child_service_fees')->insert([
            'category_id' => (int) DB::table('categories')->min('id'),
            'child_id' => $childId,
            'platform_service_id' => $menuId,
            'client_fee_enabled' => 1,
            'client_fee_type' => 'percent',
            'client_fee_amount' => 10,
            'currency' => 'EGP',
            'is_active' => 1,
            'sort_order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_menu_gets_fee_and_tax_retail_stays_plain(): void
    {
        $menu = $this->seedMenuItem($this->biz->id, null, 100.0)->id;
        $product = $this->makeCatalogProduct('furniture');
        $listing = BusinessCatalogListing::create([
            'business_id' => $this->biz->id, 'catalog_product_id' => $product,
            'sku' => 'P1', 'price' => 50.0, 'currency' => 'EGP', 'stock' => 10, 'is_active' => 1,
        ])->id;

        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $menu, 'qty' => 1])->assertCreated();
        $this->postJson('/api/v2/cart/items', ['kind' => 'retail', 'offering_id' => $listing, 'qty' => 1])->assertCreated();

        $cart = collect($this->getJson('/api/v2/cart')->json('data.carts'))->firstWhere('business.id', $this->biz->id);
        $bill = $cart['bill'];

        // Menu: 100 → fee 10 → tax (110*.14)=15.4 ; retail 50 plain.
        $this->assertSame(100.0, (float) $bill['menu_subtotal']);
        $this->assertSame(10.0, (float) $bill['service_fee']);
        $this->assertSame(15.4, (float) $bill['tax']);
        $this->assertSame(50.0, (float) $bill['retail_subtotal']);
        // final = menu(125.4) + retail(50) = 175.4
        $this->assertSame(175.4, (float) $cart['final_total']);
    }

    public function test_inclusive_flag_applies_to_personal_cart(): void
    {
        DB::table('business_menu_settings')->insert([
            'business_id' => $this->biz->id,
            'prices_include_service' => 1,
            'prices_include_tax' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $menu = $this->seedMenuItem($this->biz->id, null, 125.40)->id;

        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $menu, 'qty' => 1])->assertCreated();

        $cart = collect($this->getJson('/api/v2/cart')->json('data.carts'))->firstWhere('business.id', $this->biz->id);

        $this->assertTrue((bool) $cart['bill']['service_included']);
        $this->assertTrue((bool) $cart['bill']['tax_included']);
        $this->assertSame(125.40, (float) $cart['final_total']); // nothing added on top
    }
}
