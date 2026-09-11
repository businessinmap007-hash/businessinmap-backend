<?php

namespace Tests\Feature;

use App\Models\BusinessCatalogListing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsRetailCatalog;
use Tests\TestCase;

/**
 * A retail listing's own stock takes the hit at checkout — see
 * CustomerCartService::assertAndDecrementRetailStock(). Null stock means
 * "not tracked" and is never touched or enforced.
 */
class RetailStockDecrementTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRetailCatalog;

    private User $customer;
    private User $biz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->biz = User::query()->where('type', 'business')->firstOrFail();
        $this->customer = User::query()->where('id', '!=', $this->biz->id)->orderBy('id')->firstOrFail();
    }

    private function listing(int $stock): BusinessCatalogListing
    {
        return BusinessCatalogListing::create([
            'business_id' => $this->biz->id,
            'catalog_product_id' => $this->makeCatalogProduct(),
            'sku' => 'STOCK-' . uniqid(),
            'price' => 10.0,
            'currency' => 'EGP',
            'stock' => $stock,
            'is_active' => 1,
        ]);
    }

    public function test_checkout_decrements_stock_by_the_ordered_quantity(): void
    {
        $listing = $this->listing(50);

        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'retail', 'offering_id' => $listing->id, 'qty' => 12])->assertCreated();
        $this->postJson("/api/v2/cart/{$this->biz->id}/checkout", ['fulfillment_type' => 'pickup', 'pickup_at' => now()->addHour()->toIso8601String()])
            ->assertCreated();

        $this->assertSame(38, (int) $listing->fresh()->stock);
    }

    public function test_checkout_is_refused_when_the_order_exceeds_stock(): void
    {
        $listing = $this->listing(5);

        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'retail', 'offering_id' => $listing->id, 'qty' => 10])->assertCreated();
        $this->postJson("/api/v2/cart/{$this->biz->id}/checkout", ['fulfillment_type' => 'pickup', 'pickup_at' => now()->addHour()->toIso8601String()])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cart');

        $this->assertSame(5, (int) $listing->fresh()->stock, 'a refused checkout must not touch stock');
    }

    public function test_untracked_stock_is_never_checked_or_touched(): void
    {
        $listing = BusinessCatalogListing::create([
            'business_id' => $this->biz->id,
            'catalog_product_id' => $this->makeCatalogProduct(),
            'sku' => 'STOCK-' . uniqid(),
            'price' => 10.0,
            'currency' => 'EGP',
            'stock' => null,
            'is_active' => 1,
        ]);

        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'retail', 'offering_id' => $listing->id, 'qty' => 999])->assertCreated();
        $this->postJson("/api/v2/cart/{$this->biz->id}/checkout", ['fulfillment_type' => 'pickup', 'pickup_at' => now()->addHour()->toIso8601String()])
            ->assertCreated();

        $this->assertNull($listing->fresh()->stock);
    }

    public function test_exactly_meeting_stock_succeeds(): void
    {
        $listing = $this->listing(20);

        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'retail', 'offering_id' => $listing->id, 'qty' => 20])->assertCreated();
        $this->postJson("/api/v2/cart/{$this->biz->id}/checkout", ['fulfillment_type' => 'pickup', 'pickup_at' => now()->addHour()->toIso8601String()])
            ->assertCreated();

        $this->assertSame(0, (int) $listing->fresh()->stock);
    }
}
