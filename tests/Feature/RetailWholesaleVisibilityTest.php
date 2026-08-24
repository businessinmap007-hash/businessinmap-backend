<?php

namespace Tests\Feature;

use App\Models\BusinessCatalogListing;
use App\Models\CatalogListingAudience;
use App\Models\User;
use App\Services\Retail\RetailListingVisibility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsRetailCatalog;
use Tests\TestCase;

/**
 * «المصنع ينتج ويعرض منتجاته حصريًا للشركات التي يحددها … ولا يستطيع رؤية هذه
 *  المنتجات وأسعارها إلا الشركات التي حددها المصنع، ويمكنه تحديد محلات بعينها.
 *  الشركة تشتري بسعر الجملة ثم تعيد البيع للمحلات بسعر تحدده هي» — المالك،
 *  2026-08-23.
 *
 * Every retail listing was public: a factory publishing wholesale numbers would
 * have been showing them to its customers' customers and to its customers'
 * competitors. The tables were empty, which is the only reason this is a build
 * and not an incident.
 *
 * The rule this file holds is INVISIBLE, not price-hidden. A factory's product
 * LIST is itself commercial information — what lines it runs, what it has
 * stopped making — so a listing a viewer may not see does not exist for that
 * viewer, in discovery, in the counts, and in the cart.
 */
class RetailWholesaleVisibilityTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRetailCatalog;

    private RetailListingVisibility $visibility;

    protected function setUp(): void
    {
        parent::setUp();

        $this->visibility = app(RetailListingVisibility::class);
    }

    /** @return array{0:User,1:User,2:User} factory, the named company, a stranger */
    private function cast(): array
    {
        $businesses = User::query()->where('type', 'business')->orderBy('id')->take(3)->get();

        if ($businesses->count() < 3) {
            $this->markTestSkipped('Needs three business users.');
        }

        return [$businesses[0], $businesses[1], $businesses[2]];
    }

    /**
     * A catalog master the given business is actually ALLOWED to list.
     *
     * `catalog_product_id` is validated against the merchant's own retail scope
     * — the item types his child may sell — so a fixture under «furniture»
     * fails first for a business that sells food, and the guard under test
     * never runs. Read the scope the way the controller does and seed inside
     * it.
     */
    private function productInScopeOf(User $business): int
    {
        $slug = DB::table('category_platform_services as l')
            ->join('platform_services as s', 's.id', '=', 'l.platform_service_id')
            ->join('category_service_configs as c', function ($j) {
                $j->on('c.category_id', '=', 'l.category_id')
                    ->on('c.child_id', '=', 'l.child_id')
                    ->on('c.platform_service_id', '=', 'l.platform_service_id');
            })
            ->where('s.key', 'retail')->where('l.is_active', 1)->where('c.is_active', 1)
            ->where('l.child_id', (int) $business->category_child_id)
            ->value('c.config');

        $types = json_decode((string) $slug, true)['allowed_item_types'] ?? [];

        $child = DB::table('product_category_children')->whereIn('slug', $types)
            ->whereNull('deleted_at')->first(['slug']);

        if (! $child) {
            $this->markTestSkipped('This business sells no retail type — nothing to list.');
        }

        return $this->makeCatalogProduct($child->slug);
    }

    private function listing(User $seller, int $productId, float $price, string $mode): BusinessCatalogListing
    {
        return BusinessCatalogListing::create([
            'business_id' => $seller->id,
            'catalog_product_id' => $productId,
            'price' => $price,
            'currency' => 'EGP',
            'stock' => 100,
            'is_active' => 1,
            'visibility' => $mode,
        ]);
    }

    private function nameAudience(BusinessCatalogListing $listing, string $type, int $id): void
    {
        CatalogListingAudience::create([
            'business_catalog_listing_id' => $listing->id,
            'audience_type' => $type,
            'audience_id' => $id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | The rule itself
    |--------------------------------------------------------------------------
    */

    /** A public listing is the shelf, and nothing changes for it. */
    public function test_a_public_listing_is_seen_by_everyone_including_a_guest(): void
    {
        [$factory, $company] = $this->cast();
        $product = $this->makeCatalogProduct();

        $row = $this->listing($factory, $product, 100, RetailListingVisibility::PUBLIC);

        $this->assertTrue($this->visibility->canSee($row, null), 'a guest lost the shelf');
        $this->assertTrue($this->visibility->canSee($row, $company));
    }

    /** A restricted listing is seen by its author, its named buyer, and nobody else. */
    public function test_a_restricted_listing_reaches_only_who_was_named(): void
    {
        [$factory, $company, $stranger] = $this->cast();
        $product = $this->makeCatalogProduct();

        $row = $this->listing($factory, $product, 100, RetailListingVisibility::RESTRICTED);
        $this->nameAudience($row, CatalogListingAudience::TYPE_BUSINESS, (int) $company->id);

        $this->assertTrue($this->visibility->canSee($row, $factory), 'the factory lost its own row');
        $this->assertTrue($this->visibility->canSee($row, $company));

        $this->assertFalse($this->visibility->canSee($row, $stranger));
        $this->assertFalse($this->visibility->canSee($row, null), 'a guest read a wholesale price');
    }

    /**
     * …and a classification names four hundred buyers in one line.
     *
     * Not a convenience: a factory with four hundred customers is not going to
     * tick four hundred names, and a rule that can only be written one row at a
     * time is a rule nobody writes.
     */
    public function test_a_whole_trade_or_a_whole_root_can_be_named(): void
    {
        [$factory, $company, $stranger] = $this->cast();
        $product = $this->makeCatalogProduct();

        if (! $company->category_child_id) {
            $this->markTestSkipped('The second business stands under no child.');
        }

        $byTrade = $this->listing($factory, $product, 100, RetailListingVisibility::RESTRICTED);
        $this->nameAudience($byTrade, CatalogListingAudience::TYPE_CATEGORY_CHILD, (int) $company->category_child_id);

        $this->assertTrue($this->visibility->canSee($byTrade, $company));

        if ((int) $stranger->category_child_id !== (int) $company->category_child_id) {
            $this->assertFalse($this->visibility->canSee($byTrade, $stranger));
        }

        // …and the root, which is «كل الشركات».
        $byRoot = $this->listing($factory, $this->makeCatalogProduct(), 100, RetailListingVisibility::RESTRICTED);
        $this->nameAudience($byRoot, CatalogListingAudience::TYPE_CATEGORY, (int) $company->category_id);

        $this->assertTrue($this->visibility->canSee($byRoot, $company));
    }

    /*
    |--------------------------------------------------------------------------
    | …applied where it counts
    |--------------------------------------------------------------------------
    */

    /**
     * Discovery: the row is gone AND the count is gone.
     *
     * Filtering rows after the query would leave «١٢ منتجًا» printed over a page
     * of four, and a wrong count is worse than a missing row — it is the count
     * that tells a competitor how many lines the factory runs.
     */
    public function test_a_restricted_listing_is_absent_from_discovery_for_a_stranger(): void
    {
        [$factory, $company, $stranger] = $this->cast();
        $product = $this->makeCatalogProduct();

        $row = $this->listing($factory, $product, 100, RetailListingVisibility::RESTRICTED);
        $this->nameAudience($row, CatalogListingAudience::TYPE_BUSINESS, (int) $company->id);

        $sellersFor = function (?User $viewer) use ($product) {
            $viewer ? Sanctum::actingAs($viewer) : null;

            $res = $this->getJson('/api/v2/discovery/retail/products/' . $product);

            return $res->status() === 200 ? collect($res->json('data.offers') ?? []) : collect();
        };

        $this->assertTrue(
            $sellersFor($company)->contains(fn ($o) => (int) $o['listing_id'] === (int) $row->id),
            'the named company cannot see what it was named for'
        );

        $this->assertFalse(
            $sellersFor($stranger)->contains(fn ($o) => (int) $o['listing_id'] === (int) $row->id),
            'a stranger read the wholesale price'
        );

        // And the index: the product surfaces only where somebody may buy it.
        Sanctum::actingAs($stranger);

        $ids = collect($this->getJson('/api/v2/discovery/retail/products')->json('data.products') ?? [])
            ->pluck('id')->map(fn ($id) => (int) $id);

        $this->assertNotContains($product, $ids->all(), 'the product surfaced with no visible seller behind it');
    }

    /**
     * The cart: hiding it in discovery and taking it at checkout hides nothing.
     *
     * The id is a small integer and the cart hands back the price.
     */
    public function test_a_stranger_cannot_put_a_restricted_listing_in_a_cart(): void
    {
        [$factory, $company, $stranger] = $this->cast();
        $product = $this->makeCatalogProduct();

        $row = $this->listing($factory, $product, 100, RetailListingVisibility::RESTRICTED);
        $this->nameAudience($row, CatalogListingAudience::TYPE_BUSINESS, (int) $company->id);

        $cart = app(\App\Services\CustomerCartService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $cart->addItem((int) $stranger->id, 'retail', (int) $row->id, 1);
    }

    /*
    |--------------------------------------------------------------------------
    | The resale chain
    |--------------------------------------------------------------------------
    */

    /**
     * A company resells what it can see, at its own price, to its own audience.
     *
     * «الشركة تشتري بسعر الجملة ثم تعيد البيع للمحلات بسعر تحدده هي.»
     */
    public function test_a_company_may_relist_what_it_can_see_at_its_own_price(): void
    {
        [$factory, $company, $shop] = $this->cast();
        $product = $this->makeCatalogProduct();

        $wholesale = $this->listing($factory, $product, 100, RetailListingVisibility::RESTRICTED);
        $this->nameAudience($wholesale, CatalogListingAudience::TYPE_BUSINESS, (int) $company->id);

        $resale = $this->listing($company, $product, 140, RetailListingVisibility::RESTRICTED);
        $resale->update(['source_listing_id' => $wholesale->id]);
        $this->nameAudience($resale, CatalogListingAudience::TYPE_BUSINESS, (int) $shop->id);

        // The shop sees the company's 140 and never the factory's 100.
        $this->assertTrue($this->visibility->canSee($resale, $shop));
        $this->assertFalse($this->visibility->canSee($wholesale, $shop), 'the wholesale price leaked down the chain');

        $this->assertSame((int) $wholesale->id, (int) $resale->fresh()->source_listing_id);
    }

    /**
     * …and may not resell what it cannot see.
     *
     * Without this, «reselling» is a way to READ a wholesale price: point at
     * it, be refused nothing, and read the number off your own row.
     */
    public function test_a_stranger_cannot_resell_what_he_cannot_see(): void
    {
        [$factory, $company, $stranger] = $this->cast();

        // Seeded inside the STRANGER's own retail scope, so `catalog_product_id`
        // passes and the source guard is the thing under test. The factory's
        // listing is written through the model, which has no scope check —
        // that is the panel's job, not the table's.
        $product = $this->productInScopeOf($stranger);

        $wholesale = $this->listing($factory, $product, 100, RetailListingVisibility::RESTRICTED);
        $this->nameAudience($wholesale, CatalogListingAudience::TYPE_BUSINESS, (int) $company->id);

        Sanctum::actingAs($stranger);

        $res = $this->postJson('/api/v2/business/retail-listings', [
            'catalog_product_id' => $product,
            'price' => 140,
            'source_listing_id' => $wholesale->id,
        ]);

        if ($res->status() === 422 && $res->json('errors.catalog_product_id')) {
            $this->markTestSkipped('The stranger may not list this retail type at all — the source guard never ran.');
        }

        $this->assertContains($res->status(), [403, 422], 'a stranger was allowed to resell a hidden listing');

        if ($res->status() === 422) {
            // The same message as «not found»: «you may not see this» confirms
            // both that the id is real and that it is restricted.
            $this->assertSame(
                __('المنتج المصدر غير موجود.'),
                $res->json('errors.source_listing_id.0')
            );
        }
    }

    /** A restriction addressed to nobody is refused rather than silently hiding the row. */
    public function test_restricted_with_no_audience_is_refused(): void
    {
        [$factory] = $this->cast();
        $product = $this->productInScopeOf($factory);

        Sanctum::actingAs($factory);

        $res = $this->postJson('/api/v2/business/retail-listings', [
            'catalog_product_id' => $product,
            'price' => 100,
            'visibility' => RetailListingVisibility::RESTRICTED,
        ]);

        if ($res->status() === 403) {
            $this->markTestSkipped('Retail is not enabled for this business.');
        }

        $res->assertStatus(422)->assertJsonValidationErrors('visibility');
    }

    /** Nothing that exists today changed: every pre-existing row is public. */
    public function test_the_default_is_public_so_nothing_already_listed_moved(): void
    {
        $notPublic = DB::table('business_catalog_listings')
            ->where('visibility', '!=', RetailListingVisibility::PUBLIC)
            ->count();

        $this->assertSame(0, $notPublic, 'a live listing was restricted by the migration');

        [$factory] = $this->cast();

        $row = BusinessCatalogListing::create([
            'business_id' => $factory->id,
            'catalog_product_id' => $this->makeCatalogProduct(),
            'price' => 10,
            'currency' => 'EGP',
            'is_active' => 1,
        ]);

        $this->assertSame(RetailListingVisibility::PUBLIC, (string) $row->fresh()->visibility);
    }
}
