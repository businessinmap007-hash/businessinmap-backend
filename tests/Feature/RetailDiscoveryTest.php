<?php

namespace Tests\Feature;

use App\Models\BusinessCatalogListing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsRetailCatalog;
use Tests\TestCase;

/**
 * Customer retail discovery (Phase 3d): browse business_catalog_listings over
 * the deduped catalog master. Uses DatabaseTransactions — every row created
 * here is rolled back, so the dev database is never mutated.
 */
class RetailDiscoveryTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRetailCatalog;

    /** @return array{0:int,1:int,2:int} [businessA, businessB, productId] */
    private function seedTwoSellersOfOneProduct(): array
    {
        $businesses = User::query()->where('type', 'business')->take(2)->pluck('id')->all();

        if (count($businesses) < 2) {
            $this->markTestSkipped('Needs two business users.');
        }

        $productId = $this->makeCatalogProduct();

        [$a, $b] = $businesses;
        BusinessCatalogListing::create(['business_id' => $a, 'catalog_product_id' => $productId, 'sku' => 'RA', 'price' => 10.00, 'currency' => 'EGP', 'stock' => 5, 'is_active' => 1]);
        BusinessCatalogListing::create(['business_id' => $b, 'catalog_product_id' => $productId, 'sku' => 'RB', 'price' => 14.50, 'currency' => 'EGP', 'stock' => 3, 'is_active' => 1]);

        return [(int) $a, (int) $b, $productId];
    }

    public function test_products_browse_shows_price_range_and_business_count(): void
    {
        [, , $productId] = $this->seedTwoSellersOfOneProduct();

        $res = $this->getJson('/api/v2/discovery/retail/products?per_page=50');

        $res->assertOk()->assertJsonPath('success', true);

        $row = collect($res->json('data.products.data'))->firstWhere('id', $productId);
        $this->assertNotNull($row, 'seeded product should appear in the browse');
        $this->assertSame(10.0, (float) $row['min_price']);
        $this->assertSame(14.5, (float) $row['max_price']);
        $this->assertSame(2, (int) $row['businesses']);
    }

    public function test_show_lists_every_seller_cheapest_first(): void
    {
        [, , $productId] = $this->seedTwoSellersOfOneProduct();

        $res = $this->getJson("/api/v2/discovery/retail/products/{$productId}");

        $res->assertOk();
        $offers = $res->json('data.offers');
        $this->assertGreaterThanOrEqual(2, count($offers));

        $prices = array_map(fn ($o) => (float) $o['price'], $offers);
        $sorted = $prices;
        sort($sorted);
        $this->assertSame($sorted, $prices, 'offers must be cheapest-first');
    }

    public function test_show_returns_404_for_missing_product(): void
    {
        $this->getJson('/api/v2/discovery/retail/products/999999999')->assertNotFound();
    }

    public function test_inactive_listing_is_not_discoverable(): void
    {
        $businesses = User::query()->where('type', 'business')->take(1)->pluck('id')->all();
        if (! $businesses) {
            $this->markTestSkipped('Needs a business user.');
        }
        $productId = $this->makeCatalogProduct();

        BusinessCatalogListing::create(['business_id' => $businesses[0], 'catalog_product_id' => $productId, 'sku' => 'INACT', 'price' => 99.00, 'currency' => 'EGP', 'stock' => 1, 'is_active' => 0]);

        $res = $this->getJson("/api/v2/discovery/retail/products/{$productId}");
        $skus = array_map(fn ($o) => $o['sku'] ?? null, $res->json('data.offers') ?? []);
        $this->assertNotContains('INACT', $skus, 'inactive listings must not surface');
    }

    /**
     * One card per LISTING (business + product + price) — the feed behind
     * the Categories screen's "Retail" service chip, not the product-first
     * `products()` shape above.
     */
    public function test_listings_returns_one_card_per_listing_with_business_and_product(): void
    {
        [$businessA, $businessB, $productId] = $this->seedTwoSellersOfOneProduct();

        $res = $this->getJson('/api/v2/discovery/retail/listings?per_page=50')->assertOk();

        $rows = collect($res->json('data.listings.data'));
        $forProduct = $rows->where('product.id', $productId);
        $this->assertCount(2, $forProduct, 'both sellers of the seeded product must appear as separate cards');

        $sellerIds = $forProduct->pluck('business.id')->all();
        $this->assertContains($businessA, $sellerIds);
        $this->assertContains($businessB, $sellerIds);

        $row = $forProduct->first();
        $this->assertNotEmpty($row['product']['name']);
        $this->assertNotEmpty($row['business']['name']);
        $this->assertArrayHasKey('price', $row);
    }

    public function test_listings_category_id_narrows_to_sellers_under_that_root(): void
    {
        [$businessA, , $productId] = $this->seedTwoSellersOfOneProduct();

        $rootId = (int) User::query()->find($businessA)->category_id;
        if ($rootId <= 0) {
            $this->markTestSkipped('Seeded seller has no root category.');
        }

        $ids = collect(
            $this->getJson("/api/v2/discovery/retail/listings?category_id={$rootId}&per_page=50")
                ->assertOk()->json('data.listings.data')
        )->where('product.id', $productId)->pluck('business.id')->all();

        $this->assertContains($businessA, $ids, 'a seller under the requested root must still appear');
    }

    /** The storefront a listings() card opens into: one seller's whole shelf. */
    public function test_business_storefront_lists_every_active_listing_and_the_minimum(): void
    {
        $businesses = User::query()->where('type', 'business')->take(1)->pluck('id')->all();
        if (! $businesses) {
            $this->markTestSkipped('Needs a business user.');
        }
        $businessId = (int) $businesses[0];

        \App\Models\BusinessRetailSetting::updateOrCreate(['business_id' => $businessId], ['min_order_amount' => 75]);

        $productA = $this->makeCatalogProduct();
        $productB = $this->makeCatalogProduct();
        BusinessCatalogListing::create(['business_id' => $businessId, 'catalog_product_id' => $productA, 'sku' => 'SFA', 'price' => 20, 'currency' => 'EGP', 'stock' => 5, 'is_active' => 1]);
        BusinessCatalogListing::create(['business_id' => $businessId, 'catalog_product_id' => $productB, 'sku' => 'SFB', 'price' => 30, 'currency' => 'EGP', 'stock' => 2, 'is_active' => 1]);
        // Inactive — must not surface.
        BusinessCatalogListing::create(['business_id' => $businessId, 'catalog_product_id' => $this->makeCatalogProduct(), 'sku' => 'SFC', 'price' => 40, 'currency' => 'EGP', 'stock' => 1, 'is_active' => 0]);

        $res = $this->getJson("/api/v2/discovery/retail/business/{$businessId}")->assertOk();

        $this->assertSame(75.0, (float) $res->json('data.business.min_order_amount'));
        $productIds = collect($res->json('data.listings'))->pluck('product.id')->all();
        $this->assertContains($productA, $productIds);
        $this->assertContains($productB, $productIds);
        $this->assertCount(2, $productIds, 'the inactive listing must not surface');
    }

    public function test_business_storefront_returns_404_for_a_non_business(): void
    {
        $customer = User::query()->where('type', '!=', 'business')->value('id');
        if (! $customer) {
            $this->markTestSkipped('Needs a non-business user.');
        }

        $this->getJson("/api/v2/discovery/retail/business/{$customer}")->assertNotFound();
    }

    public function test_listings_hides_a_restricted_listing_from_a_stranger(): void
    {
        $businesses = User::query()->where('type', 'business')->take(1)->pluck('id')->all();
        if (! $businesses) {
            $this->markTestSkipped('Needs a business user.');
        }
        $productId = $this->makeCatalogProduct();

        BusinessCatalogListing::create([
            'business_id' => $businesses[0],
            'catalog_product_id' => $productId,
            'sku' => 'RESTRICTED-CARD',
            'price' => 55.00,
            'currency' => 'EGP',
            'stock' => 1,
            'is_active' => 1,
            'visibility' => \App\Services\Retail\RetailListingVisibility::RESTRICTED,
        ]);

        $skus = collect(
            $this->getJson('/api/v2/discovery/retail/listings?per_page=50')->assertOk()->json('data.listings.data')
        )->pluck('product.id')->all();

        $this->assertNotContains($productId, $skus, 'a restricted listing must not surface as a card to a guest');
    }
}
