<?php

namespace Tests\Feature;

use App\Models\BusinessCatalogListing;
use App\Models\BusinessRetailSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsMenu;
use Tests\Concerns\SeedsRetailCatalog;
use Tests\TestCase;

/**
 * The retail seller's own «حدٌّ أدنى للطلب», mirroring MenuMinimumOrderTest —
 * checked against the RETAIL lines of the order alone, never a menu line
 * sitting in the same cart.
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

    private function listing(float $price): int
    {
        return BusinessCatalogListing::create([
            'business_id' => $this->biz->id,
            'catalog_product_id' => $this->makeCatalogProduct(),
            'sku' => 'MIN-' . uniqid(),
            'price' => $price,
            'currency' => 'EGP',
            'stock' => 50,
            'is_active' => 1,
        ])->id;
    }

    public function test_checkout_is_refused_below_the_minimum(): void
    {
        BusinessRetailSetting::updateOrCreate(['business_id' => $this->biz->id], ['min_order_amount' => 100]);

        $listing = $this->listing(60.0);

        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'retail', 'offering_id' => $listing, 'qty' => 1])->assertCreated();

        $this->postJson("/api/v2/cart/{$this->biz->id}/checkout", ['fulfillment_type' => 'pickup'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cart');
    }

    public function test_checkout_succeeds_at_or_above_the_minimum(): void
    {
        BusinessRetailSetting::updateOrCreate(['business_id' => $this->biz->id], ['min_order_amount' => 100]);

        $listing = $this->listing(100.0);

        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'retail', 'offering_id' => $listing, 'qty' => 1])->assertCreated();

        $this->postJson("/api/v2/cart/{$this->biz->id}/checkout", ['fulfillment_type' => 'pickup'])->assertCreated();
    }

    public function test_no_minimum_configured_never_blocks_checkout(): void
    {
        BusinessRetailSetting::query()->where('business_id', $this->biz->id)->delete();

        $listing = $this->listing(1.0);

        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'retail', 'offering_id' => $listing, 'qty' => 1])->assertCreated();

        $this->postJson("/api/v2/cart/{$this->biz->id}/checkout", ['fulfillment_type' => 'pickup'])->assertCreated();
    }

    /** A cart with only menu lines has no retail subtotal — the retail minimum never applies to it. */
    public function test_a_pure_menu_cart_is_never_blocked_by_the_retail_minimum(): void
    {
        BusinessRetailSetting::updateOrCreate(['business_id' => $this->biz->id], ['min_order_amount' => 1000]);

        $item = $this->seedMenuItem($this->biz->id, null, 10.0)->id;

        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $item, 'qty' => 1])->assertCreated();

        $this->postJson("/api/v2/cart/{$this->biz->id}/checkout", ['fulfillment_type' => 'pickup'])->assertCreated();
    }

    public function test_merchant_can_read_and_set_their_own_minimum(): void
    {
        Sanctum::actingAs($this->biz);

        $this->getJson('/api/v2/business/retail-settings')
            ->assertOk()
            ->assertJsonPath('data.min_order_amount', null);

        $this->putJson('/api/v2/business/retail-settings', ['min_order_amount' => 250.5])
            ->assertOk()
            ->assertJsonPath('data.min_order_amount', 250.5);

        $this->getJson('/api/v2/business/retail-settings')
            ->assertOk()
            ->assertJsonPath('data.min_order_amount', 250.5);
    }

    public function test_merchant_can_clear_their_minimum(): void
    {
        BusinessRetailSetting::updateOrCreate(['business_id' => $this->biz->id], ['min_order_amount' => 100]);

        Sanctum::actingAs($this->biz);
        $this->putJson('/api/v2/business/retail-settings', ['min_order_amount' => null])
            ->assertOk()
            ->assertJsonPath('data.min_order_amount', null);
    }
}
