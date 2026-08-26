<?php

namespace Tests\Feature;

use App\Models\BusinessMenuSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsMenu;
use Tests\Concerns\SeedsRetailCatalog;
use Tests\TestCase;

/**
 * «حدٌّ أدنى للطلب» — المالك، 2026-08-25. Checked against the MENU lines of the
 * order alone, never a retail line sitting in the same cart.
 */
class MenuMinimumOrderTest extends TestCase
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

    public function test_checkout_is_refused_below_the_minimum(): void
    {
        BusinessMenuSetting::updateOrCreate(['business_id' => $this->biz->id], ['min_order_amount' => 100]);

        $item = $this->seedMenuItem($this->biz->id, null, 60.0)->id;

        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $item, 'qty' => 1])->assertCreated();

        $this->postJson("/api/v2/cart/{$this->biz->id}/checkout", ['fulfillment_type' => 'pickup'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cart');
    }

    public function test_checkout_succeeds_at_or_above_the_minimum(): void
    {
        BusinessMenuSetting::updateOrCreate(['business_id' => $this->biz->id], ['min_order_amount' => 100]);

        $item = $this->seedMenuItem($this->biz->id, null, 100.0)->id;

        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $item, 'qty' => 1])->assertCreated();

        $this->postJson("/api/v2/cart/{$this->biz->id}/checkout", ['fulfillment_type' => 'pickup'])->assertCreated();
    }

    public function test_no_minimum_configured_never_blocks_checkout(): void
    {
        BusinessMenuSetting::query()->where('business_id', $this->biz->id)->delete();

        $item = $this->seedMenuItem($this->biz->id, null, 1.0)->id;

        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $item, 'qty' => 1])->assertCreated();

        $this->postJson("/api/v2/cart/{$this->biz->id}/checkout", ['fulfillment_type' => 'pickup'])->assertCreated();
    }

    /** A cart with only retail lines has no menu subtotal — the minimum never applies to it. */
    public function test_a_pure_retail_cart_is_never_blocked_by_the_menu_minimum(): void
    {
        BusinessMenuSetting::updateOrCreate(['business_id' => $this->biz->id], ['min_order_amount' => 1000]);

        $product = $this->makeCatalogProduct('furniture');
        $listing = \App\Models\BusinessCatalogListing::create([
            'business_id' => $this->biz->id, 'catalog_product_id' => $product,
            'sku' => 'P1', 'price' => 10.0, 'currency' => 'EGP', 'stock' => 5, 'is_active' => 1,
        ])->id;

        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'retail', 'offering_id' => $listing, 'qty' => 1])->assertCreated();

        $this->postJson("/api/v2/cart/{$this->biz->id}/checkout", ['fulfillment_type' => 'pickup'])->assertCreated();
    }
}
