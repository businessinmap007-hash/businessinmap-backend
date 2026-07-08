<?php

namespace Tests\Feature;

use App\Models\BusinessCatalogListing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Customer retail discovery (Phase 3d): browse business_catalog_listings over
 * the deduped catalog master. Uses DatabaseTransactions — every row created
 * here is rolled back, so the dev database is never mutated.
 */
class RetailDiscoveryTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{0:int,1:int,2:int} [businessA, businessB, productId] */
    private function seedTwoSellersOfOneProduct(): array
    {
        $businesses = User::query()->where('type', 'business')->take(2)->pluck('id')->all();
        $productId = (int) DB::table('catalog_products')->whereNull('deleted_at')->value('id');

        if (count($businesses) < 2 || $productId <= 0) {
            $this->markTestSkipped('Needs two business users and one catalog master.');
        }

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
        $productId = (int) DB::table('catalog_products')->whereNull('deleted_at')->value('id');
        if (! $businesses || $productId <= 0) {
            $this->markTestSkipped('Needs a business user and a catalog master.');
        }

        BusinessCatalogListing::create(['business_id' => $businesses[0], 'catalog_product_id' => $productId, 'sku' => 'INACT', 'price' => 99.00, 'currency' => 'EGP', 'stock' => 1, 'is_active' => 0]);

        $res = $this->getJson("/api/v2/discovery/retail/products/{$productId}");
        $skus = array_map(fn ($o) => $o['sku'] ?? null, $res->json('data.offers') ?? []);
        $this->assertNotContains('INACT', $skus, 'inactive listings must not surface');
    }
}
