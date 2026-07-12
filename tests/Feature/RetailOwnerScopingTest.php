<?php

namespace Tests\Feature;

use App\Models\BusinessCatalogListing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsRetailCatalog;
use Tests\TestCase;

/**
 * "My Products" catalog scoping: an owner may only see/list catalog products
 * whose product_category_child slug is among the retail item types allowed for
 * their category child (the 1:1 mirror bridge). Owners of non-retail children
 * are blocked from the screen entirely.
 */
class RetailOwnerScopingTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRetailCatalog;

    /** A business owner whose category_child has retail active (آثاث → home_furnishings). */
    private function retailOwner(): User
    {
        $childId = (int) DB::table('category_children_master')->where('name_ar', 'آثاث')->value('id');
        $user = User::query()->where('type', 'business')->firstOrFail();
        // In-memory only (rolled back anyway); childId() reads this attribute.
        $user->category_child_id = $childId;

        return $user;
    }

    public function test_lookup_returns_only_in_branch_products(): void
    {
        $inScope = $this->makeCatalogProduct('furniture', 'كنبة اختبار');
        $outScope = $this->makeCatalogProduct('gold_jewelry', 'خاتم اختبار');

        $this->actingAs($this->retailOwner());

        $res = $this->getJson(route('business.products.lookup', ['q' => 'اختبار'], false))->assertOk();

        $ids = array_map(fn ($i) => (int) $i['id'], $res->json('items'));
        $this->assertContains($inScope, $ids, 'in-branch product must appear');
        $this->assertNotContains($outScope, $ids, 'out-of-branch product must be hidden');
    }

    public function test_store_rejects_out_of_scope_product(): void
    {
        $outScope = $this->makeCatalogProduct('gold_jewelry', 'سلسلة اختبار');

        $this->actingAs($this->retailOwner());

        $this->postJson(route('business.products.store', [], false), [
            'catalog_product_id' => $outScope,
            'price' => 100,
            'stock' => 5,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('business_catalog_listings', ['catalog_product_id' => $outScope]);
    }

    public function test_store_accepts_in_scope_product(): void
    {
        $inScope = $this->makeCatalogProduct('mattresses', 'مرتبة اختبار');
        $owner = $this->retailOwner();

        $this->actingAs($owner);

        $this->post(route('business.products.store', [], false), [
            'catalog_product_id' => $inScope,
            'price' => 250,
            'stock' => 10,
        ])->assertRedirect(route('business.products.index'));

        $this->assertDatabaseHas('business_catalog_listings', [
            'business_id' => $owner->id,
            'catalog_product_id' => $inScope,
        ]);
    }

    public function test_non_retail_child_is_blocked(): void
    {
        $product = $this->makeCatalogProduct('furniture');

        $owner = User::query()->where('type', 'business')->firstOrFail();
        // A child id with no retail service link at all.
        $owner->category_child_id = 0;

        $this->actingAs($owner);

        $this->getJson(route('business.products.lookup', [], false))->assertStatus(403);
        $this->postJson(route('business.products.store', [], false), [
            'catalog_product_id' => $product,
            'price' => 10,
        ])->assertStatus(403);
    }
}
