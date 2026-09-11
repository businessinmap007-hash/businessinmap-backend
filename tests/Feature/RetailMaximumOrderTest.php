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
 * The retail seller's own «حدٌّ أقصى للطلب» — the counterpart to
 * RetailMinimumOrderTest: caps how much of ONE listing a single order may
 * take, so a wholesale buyer can't clear out the whole shelf in one
 * checkout. See CustomerCartService::assertMeetsRetailQtyBounds() and
 * BusinessCatalogListing::max_order_qty.
 */
class RetailMaximumOrderTest extends TestCase
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

    private function listing(float $price, ?int $minOrderQty = null, ?int $maxOrderQty = null, int $stock = 500): int
    {
        return BusinessCatalogListing::create([
            'business_id' => $this->biz->id,
            'catalog_product_id' => $this->makeCatalogProduct(),
            'sku' => 'MAX-' . uniqid(),
            'price' => $price,
            'currency' => 'EGP',
            'stock' => $stock,
            'min_order_qty' => $minOrderQty,
            'max_order_qty' => $maxOrderQty,
            'is_active' => 1,
        ])->id;
    }

    public function test_checkout_is_refused_above_the_maximum_quantity(): void
    {
        $listing = $this->listing(10.0, null, 50);

        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'retail', 'offering_id' => $listing, 'qty' => 60])->assertCreated();

        $this->postJson("/api/v2/cart/{$this->biz->id}/checkout", ['fulfillment_type' => 'pickup', 'pickup_at' => now()->addHour()->toIso8601String()])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cart');
    }

    public function test_checkout_succeeds_at_or_below_the_maximum_quantity(): void
    {
        $listing = $this->listing(10.0, null, 50);

        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'retail', 'offering_id' => $listing, 'qty' => 50])->assertCreated();

        $this->postJson("/api/v2/cart/{$this->biz->id}/checkout", ['fulfillment_type' => 'pickup', 'pickup_at' => now()->addHour()->toIso8601String()])->assertCreated();
    }

    public function test_no_maximum_configured_never_blocks_checkout(): void
    {
        $listing = $this->listing(1.0, null, null);

        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'retail', 'offering_id' => $listing, 'qty' => 400])->assertCreated();

        $this->postJson("/api/v2/cart/{$this->biz->id}/checkout", ['fulfillment_type' => 'pickup', 'pickup_at' => now()->addHour()->toIso8601String()])->assertCreated();
    }

    /** Both bounds may be set together: inside the [min, max] window checkout succeeds. */
    public function test_both_bounds_together_allow_a_quantity_in_between(): void
    {
        $listing = $this->listing(10.0, 20, 50);

        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'retail', 'offering_id' => $listing, 'qty' => 35])->assertCreated();

        $this->postJson("/api/v2/cart/{$this->biz->id}/checkout", ['fulfillment_type' => 'pickup', 'pickup_at' => now()->addHour()->toIso8601String()])->assertCreated();
    }

    public function test_merchant_cannot_set_a_maximum_below_the_minimum(): void
    {
        $listingId = $this->listing(10.0, null, null);

        Sanctum::actingAs($this->biz);

        $this->putJson("/api/v2/business/retail-listings/{$listingId}", [
            'price' => 10.0,
            'stock' => 500,
            'min_order_qty' => 50,
            'max_order_qty' => 20,
        ])->assertStatus(422)->assertJsonValidationErrors('max_order_qty');
    }

    public function test_merchant_can_update_the_maximum_quantity_on_a_listing(): void
    {
        $listingId = $this->listing(30.0, null, 50);

        Sanctum::actingAs($this->biz);

        $this->getJson("/api/v2/business/retail-listings/{$listingId}")
            ->assertOk()
            ->assertJsonPath('data.max_order_qty', 50);

        $this->putJson("/api/v2/business/retail-listings/{$listingId}", [
            'price' => 30.0,
            'stock' => 500,
            'max_order_qty' => 80,
            'is_active' => true,
        ])->assertOk()
            ->assertJsonPath('data.max_order_qty', 80);
    }
}
