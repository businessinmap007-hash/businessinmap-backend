<?php

namespace Tests\Feature;

use App\Models\BusinessCatalogListing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsMenu;
use Tests\Concerns\SeedsRetailCatalog;
use Tests\TestCase;

/**
 * The retail seller's own «حدٌّ أدنى للطلب» — a per-LISTING minimum QUANTITY
 * (e.g. 20 كيلو), not a cart-wide currency amount: see
 * CustomerCartService::assertMeetsRetailMinimumQty() and
 * BusinessCatalogListing::min_order_qty.
 */
class RetailMinimumOrderTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsMenu;
    use SeedsRetailCatalog;

    private User $customer;
    private User $biz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->biz = User::query()->where('type', 'business')->firstOrFail();
        $this->customer = User::query()->where('id', '!=', $this->biz->id)->orderBy('id')->firstOrFail();
    }

    private function listing(float $price, ?int $minOrderQty = null): int
    {
        return BusinessCatalogListing::create([
            'business_id' => $this->biz->id,
            'catalog_product_id' => $this->makeCatalogProduct(),
            'sku' => 'MIN-' . uniqid(),
            'price' => $price,
            'currency' => 'EGP',
            'stock' => 500,
            'min_order_qty' => $minOrderQty,
            'is_active' => 1,
        ])->id;
    }

    public function test_checkout_is_refused_below_the_minimum_quantity(): void
    {
        $listing = $this->listing(10.0, 20);

        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'retail', 'offering_id' => $listing, 'qty' => 5])->assertCreated();

        $this->postJson("/api/v2/cart/{$this->biz->id}/checkout", ['fulfillment_type' => 'pickup'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cart');
    }

    public function test_checkout_succeeds_at_or_above_the_minimum_quantity(): void
    {
        $listing = $this->listing(10.0, 20);

        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'retail', 'offering_id' => $listing, 'qty' => 20])->assertCreated();

        $this->postJson("/api/v2/cart/{$this->biz->id}/checkout", ['fulfillment_type' => 'pickup'])->assertCreated();
    }

    public function test_no_minimum_configured_never_blocks_checkout(): void
    {
        $listing = $this->listing(1.0, null);

        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'retail', 'offering_id' => $listing, 'qty' => 1])->assertCreated();

        $this->postJson("/api/v2/cart/{$this->biz->id}/checkout", ['fulfillment_type' => 'pickup'])->assertCreated();
    }

    /** A cart with only menu lines has no retail lines — the retail minimum never applies to it. */
    public function test_a_pure_menu_cart_is_never_blocked_by_the_retail_minimum(): void
    {
        $item = $this->seedMenuItem($this->biz->id, null, 10.0)->id;

        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $item, 'qty' => 1])->assertCreated();

        $this->postJson("/api/v2/cart/{$this->biz->id}/checkout", ['fulfillment_type' => 'pickup'])->assertCreated();
    }

    /** Accumulating quantity across two adds still counts toward the same line's minimum. */
    public function test_merged_quantity_across_two_adds_counts_toward_the_minimum(): void
    {
        $listing = $this->listing(10.0, 20);

        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'retail', 'offering_id' => $listing, 'qty' => 12])->assertCreated();
        $this->postJson('/api/v2/cart/items', ['kind' => 'retail', 'offering_id' => $listing, 'qty' => 8])->assertCreated();

        $this->postJson("/api/v2/cart/{$this->biz->id}/checkout", ['fulfillment_type' => 'pickup'])->assertCreated();
    }

    public function test_merchant_can_update_the_minimum_quantity_on_a_listing(): void
    {
        $listingId = $this->listing(30.0, 20);

        Sanctum::actingAs($this->biz);

        $this->getJson("/api/v2/business/retail-listings/{$listingId}")
            ->assertOk()
            ->assertJsonPath('data.min_order_qty', 20);

        $this->putJson("/api/v2/business/retail-listings/{$listingId}", [
            'price' => 30.0,
            'stock' => 500,
            'min_order_qty' => 25,
            'is_active' => true,
        ])->assertOk()->assertJsonPath('data.min_order_qty', 25);
    }
}
