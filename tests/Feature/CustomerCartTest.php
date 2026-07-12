<?php

namespace Tests\Feature;

use App\Models\BusinessCatalogListing;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsRetailCatalog;
use Tests\TestCase;

/**
 * Customer cart over the offering layer (Phase 3d): a cart is a draft Order
 * (status='cart') per business; checkout flips it to 'pending'. All rows are
 * created inside a rolled-back transaction.
 */
class CustomerCartTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRetailCatalog;

    private User $customer;

    private int $businessA;

    private int $businessB;

    private int $listingA;

    private int $listingB;

    private int $menuA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::query()->orderBy('id')->first();
        $businesses = User::query()->where('type', 'business')->take(2)->pluck('id')->all();

        if (! $this->customer || count($businesses) < 2) {
            $this->markTestSkipped('Needs a user and two businesses.');
        }

        $products = [$this->makeCatalogProduct('furniture'), $this->makeCatalogProduct('mattresses')];

        [$this->businessA, $this->businessB] = array_map('intval', $businesses);

        $this->listingA = BusinessCatalogListing::create(['business_id' => $this->businessA, 'catalog_product_id' => $products[0], 'sku' => 'CA', 'price' => 10.00, 'currency' => 'EGP', 'stock' => 50, 'is_active' => 1])->id;
        $this->listingB = BusinessCatalogListing::create(['business_id' => $this->businessB, 'catalog_product_id' => $products[1], 'sku' => 'CB', 'price' => 4.00, 'currency' => 'EGP', 'stock' => 50, 'is_active' => 1])->id;
        $this->menuA = MenuItem::create(['business_id' => $this->businessA, 'name_ar' => 'صنف اختبار', 'name_en' => 'Test', 'base_price' => 75.00, 'is_active' => 1])->id;
    }

    public function test_adding_offerings_groups_into_one_cart_per_business(): void
    {
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/v2/cart/items', ['kind' => 'retail', 'offering_id' => $this->listingA, 'qty' => 2])->assertCreated();
        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $this->menuA, 'qty' => 1])->assertCreated();
        $this->postJson('/api/v2/cart/items', ['kind' => 'retail', 'offering_id' => $this->listingB, 'qty' => 1])->assertCreated();

        $res = $this->getJson('/api/v2/cart')->assertOk();

        $this->assertSame(2, (int) $res->json('data.totals.businesses'));
        // businessA cart = 2*10 + 1*75 = 95 ; businessB cart = 4 ; grand = 99
        $this->assertSame(99.0, (float) $res->json('data.totals.grand_total'));
    }

    public function test_adding_same_offering_merges_quantity(): void
    {
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/v2/cart/items', ['kind' => 'retail', 'offering_id' => $this->listingA, 'qty' => 2])->assertCreated();
        $this->postJson('/api/v2/cart/items', ['kind' => 'retail', 'offering_id' => $this->listingA, 'qty' => 3])->assertCreated();

        $cart = Order::query()->where('user_id', $this->customer->id)->where('business_id', $this->businessA)->where('status', 'cart')->first();
        $this->assertNotNull($cart);
        $this->assertSame(1, $cart->items()->count(), 'same offering must merge into one line');
        $this->assertSame(5, (int) $cart->items()->first()->qty);
        $this->assertSame('50.00', (string) $cart->total);
    }

    public function test_checkout_flips_cart_to_pending_order(): void
    {
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/v2/cart/items', ['kind' => 'retail', 'offering_id' => $this->listingA, 'qty' => 2])->assertCreated();

        $res = $this->postJson("/api/v2/cart/{$this->businessA}/checkout", ['fulfillment_type' => 'pickup'])->assertCreated();

        $orderId = (int) $res->json('data.order.id');
        $this->assertSame('pending', Order::query()->whereKey($orderId)->value('status'));
        $this->assertSame(0, Order::query()->where('user_id', $this->customer->id)->where('business_id', $this->businessA)->where('status', 'cart')->count());
    }

    public function test_price_comes_from_the_offering_not_the_client(): void
    {
        Sanctum::actingAs($this->customer);

        // Client cannot inject a price; server sources it from the listing (10.00).
        $this->postJson('/api/v2/cart/items', ['kind' => 'retail', 'offering_id' => $this->listingA, 'qty' => 1, 'price' => 0.01])->assertCreated();

        $cart = Order::query()->where('user_id', $this->customer->id)->where('business_id', $this->businessA)->where('status', 'cart')->first();
        $this->assertSame('10.00', (string) $cart->items()->first()->price);
    }
}
