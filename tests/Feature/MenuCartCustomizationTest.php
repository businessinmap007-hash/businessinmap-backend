<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsMenu;
use Tests\TestCase;

/**
 * Menu cart customisation: variants (size) and extras (add-ons) are priced
 * server-side, distinct selections stay separate lines, identical ones merge,
 * and the placed order carries the customisation.
 */
class MenuCartCustomizationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsMenu;

    private User $customer;
    private User $biz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::query()->orderBy('id')->firstOrFail();
        $this->biz = User::query()->where('type', 'business')->firstOrFail();
    }

    public function test_variant_and_extras_priced_server_side(): void
    {
        $item = $this->seedMenuItem($this->biz->id, null, 50.0);
        $large = $this->seedVariant($item, 'كبير', price: 70.0);
        $cheese = $this->seedExtra($item, 'جبنة', price: 10.0);

        Sanctum::actingAs($this->customer);

        // Client tries to inject a price; server ignores it. Unit = 70 + 10 = 80.
        $this->postJson('/api/v2/cart/items', [
            'kind' => 'menu', 'offering_id' => $item->id, 'qty' => 2,
            'size_id' => $large->id, 'extras' => [$cheese->id], 'price' => 1,
        ])->assertCreated();

        $cart = Order::query()->where('user_id', $this->customer->id)
            ->where('business_id', $this->biz->id)->where('status', 'cart')->first();
        $line = $cart->items()->first();

        $this->assertSame('80.00', (string) $line->price);
        $this->assertSame('160.00', (string) $line->total_price);
        $this->assertSame($large->id, (int) $line->size_id);
        $this->assertSame($cheese->id, (int) $line->addons[0]['id']);
    }

    public function test_different_selections_stay_separate_identical_merge(): void
    {
        $item = $this->seedMenuItem($this->biz->id, null, 50.0);
        $small = $this->seedVariant($item, 'صغير', price: 40.0);
        $large = $this->seedVariant($item, 'كبير', price: 70.0);

        Sanctum::actingAs($this->customer);

        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $item->id, 'qty' => 1, 'size_id' => $small->id])->assertCreated();
        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $item->id, 'qty' => 1, 'size_id' => $large->id])->assertCreated();
        // identical to the first -> should merge, not add a 3rd line
        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $item->id, 'qty' => 2, 'size_id' => $small->id])->assertCreated();

        $cart = Order::query()->where('user_id', $this->customer->id)
            ->where('business_id', $this->biz->id)->where('status', 'cart')->first();

        $this->assertSame(2, $cart->items()->count(), 'two distinct sizes = two lines');
        $smallLine = $cart->items()->where('size_id', $small->id)->first();
        $this->assertSame(3, (int) $smallLine->qty, 'identical selection merged (1+2)');
    }

    public function test_extra_not_belonging_to_item_is_rejected(): void
    {
        $item = $this->seedMenuItem($this->biz->id, null, 50.0);
        $otherItem = $this->seedMenuItem($this->biz->id, null, 30.0, 'صنف آخر');
        $foreignExtra = $this->seedExtra($otherItem, 'صوص', price: 5.0);

        Sanctum::actingAs($this->customer);

        $this->postJson('/api/v2/cart/items', [
            'kind' => 'menu', 'offering_id' => $item->id, 'qty' => 1, 'extras' => [$foreignExtra->id],
        ])->assertStatus(422);
    }

    public function test_checkout_keeps_customisation(): void
    {
        $item = $this->seedMenuItem($this->biz->id, null, 50.0);
        $large = $this->seedVariant($item, 'كبير', price: 70.0);

        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $item->id, 'qty' => 1, 'size_id' => $large->id])->assertCreated();

        $res = $this->postJson("/api/v2/cart/{$this->biz->id}/checkout", ['fulfillment_type' => 'pickup'])->assertCreated();
        $orderId = (int) $res->json('data.order.id');

        $order = Order::query()->whereKey($orderId)->firstOrFail();
        $this->assertSame('pending', $order->status);
        $this->assertSame($large->id, (int) $order->items()->first()->size_id);
    }
}
