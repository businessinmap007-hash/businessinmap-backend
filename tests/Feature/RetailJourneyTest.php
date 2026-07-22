<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsRetailCatalog;
use Tests\TestCase;

/**
 * Retail — the last typed service to get a single continuous journey test.
 *
 * The two halves were already covered in isolation (RetailOwnerScopingTest for
 * the merchant "My Products" screen, RetailDiscoveryTest for the customer API),
 * but nothing walked the loop as one story, and the discovery `filters`
 * rollup had no coverage at all. This walks it end to end:
 *
 *   two retail owners each list the SAME catalog master through the real
 *   business panel POST → a customer browses the filter facets → finds the
 *   product with a price range spanning both sellers → opens it and sees both
 *   offers cheapest-first → one owner deactivates its listing through the panel
 *   → the customer now sees a single seller.
 *
 * Discipline (see the journey-test rule): the product id a customer acts on is
 * the one the merchant flow created a listing for; the listing ids come from the
 * discovery responses, never hardcoded.
 */
class RetailJourneyTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRetailCatalog;

    /**
     * A business owner whose category child ("آثاث") has the retail service
     * active, so its scope includes the 'furniture' catalog child. Mirrors
     * RetailOwnerScopingTest::retailOwner(). The category_child_id is set in
     * memory only — rolled back with the transaction.
     */
    private function retailOwner(int $index = 0): User
    {
        $childId = (int) DB::table('category_children_master')->where('name_ar', 'آثاث')->value('id');

        if ($childId <= 0) {
            $this->markTestSkipped('Business taxonomy child "آثاث" not seeded.');
        }

        $user = User::query()->where('type', 'business')->orderBy('id')->skip($index)->first();

        if (! $user) {
            $this->markTestSkipped('Needs at least two business users.');
        }

        $user->category_child_id = $childId;

        return $user;
    }

    public function test_two_owners_list_then_a_customer_discovers_the_product(): void
    {
        $ownerA = $this->retailOwner(0);
        $ownerB = $this->retailOwner(1);

        if ($ownerA->id === $ownerB->id) {
            $this->markTestSkipped('Needs two distinct business users.');
        }

        // A shared catalog master under the 'furniture' retail child.
        $productId = $this->makeCatalogProduct('furniture', 'كنبة الرحلة');

        // --- Merchant side: each owner lists the product through the real panel.
        $this->actingAs($ownerA)
            ->post(route('business.products.store', [], false), [
                'catalog_product_id' => $productId,
                'price' => 1200,
                'stock' => 4,
                'is_active' => 1,
            ])
            ->assertRedirect(route('business.products.index'));

        $this->actingAs($ownerB)
            ->post(route('business.products.store', [], false), [
                'catalog_product_id' => $productId,
                'price' => 1500,
                'stock' => 2,
                'is_active' => 1,
            ])
            ->assertRedirect(route('business.products.index'));

        $this->assertDatabaseHas('business_catalog_listings', ['business_id' => $ownerA->id, 'catalog_product_id' => $productId]);
        $this->assertDatabaseHas('business_catalog_listings', ['business_id' => $ownerB->id, 'catalog_product_id' => $productId]);

        // --- Customer side: the filter facets now include this product's branch
        //     and category (previously untested endpoint).
        $filters = $this->getJson('/api/v2/discovery/retail/filters')->assertOk()->assertJsonPath('success', true);

        $branchIds = array_map(fn ($b) => (int) $b['id'], $filters->json('data.branches'));
        $categoryIds = array_map(fn ($c) => (int) $c['id'], $filters->json('data.categories'));
        $this->assertContains(12, $branchIds, 'the furniture branch (product_category 12) must appear in facets');
        $this->assertContains(58, $categoryIds, 'the furniture child (58) must appear in facets');

        // --- Customer browse: the product surfaces with a price range spanning
        //     both sellers and a business count of 2.
        $browse = $this->getJson('/api/v2/discovery/retail/products?child_id=58&per_page=50')->assertOk();
        $row = collect($browse->json('data.products.data'))->firstWhere('id', $productId);
        $this->assertNotNull($row, 'the just-listed product must be discoverable');
        $this->assertSame(1200.0, (float) $row['min_price']);
        $this->assertSame(1500.0, (float) $row['max_price']);
        $this->assertSame(2, (int) $row['businesses']);

        // --- Customer detail: both offers, cheapest first, each naming a seller.
        $detail = $this->getJson("/api/v2/discovery/retail/products/{$productId}")->assertOk();
        $offers = $detail->json('data.offers');
        $this->assertCount(2, $offers);
        $this->assertSame(1200.0, (float) $offers[0]['price'], 'cheapest offer must be first');
        $sellerIds = array_map(fn ($o) => (int) $o['business']['id'], $offers);
        $this->assertContains($ownerA->id, $sellerIds);
        $this->assertContains($ownerB->id, $sellerIds);

        // --- Merchant deactivates: owner B pulls its listing through the panel,
        //     and the customer immediately sees a single seller.
        $listingBId = (int) collect($offers)->firstWhere('business.id', $ownerB->id)['listing_id'];

        $this->actingAs($ownerB)
            ->put(route('business.products.update', ['id' => $listingBId], false), [
                'price' => 1500,
                'stock' => 2,
                'is_active' => 0,
            ])
            ->assertRedirect();

        $after = $this->getJson("/api/v2/discovery/retail/products/{$productId}")->assertOk();
        $afterSellers = array_map(fn ($o) => (int) $o['business']['id'], $after->json('data.offers'));
        $this->assertContains($ownerA->id, $afterSellers, 'the active listing stays visible');
        $this->assertNotContains($ownerB->id, $afterSellers, 'the deactivated listing must drop out');
    }
}
