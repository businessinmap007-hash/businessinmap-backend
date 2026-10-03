<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «اختبر أضف للسلة بسعر التقسيط حتى الفاتورة» — المالك، 2026-10-03. The furniture
 * factory gives one bedroom two prices (كاش 30000، تقسيط 36000); the customer
 * picks «تقسيط», adds two to the cart, checks out — and every step carries the
 * INSTALMENT price AND says it is an instalment: the cart line and total, the
 * placed order's lines and totals, the order the customer reads back, and the
 * merchant's incoming order. Rolls back.
 */
class PaymentPriceThroughToOrderTest extends TestCase
{
    use DatabaseTransactions;

    private User $shop;
    private User $customer;
    private int $itemId;
    private int $cashId;
    private int $instalmentId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = User::query()->where('type', 'business')->where('category_child_id', 116)->where('category_id', 23)->orderBy('id')->firstOrFail();
        $this->customer = User::query()->where('type', '!=', 'business')->where('id', '!=', $this->shop->id)->orderBy('id')->firstOrFail();

        // The shop is open whenever a customer checks out in this test.
        DB::table('business_working_hours')->where('business_id', $this->shop->id)->delete();

        $bedroom = (int) DB::table('options')->where('group_id', 3)->where('name_ar', 'غرفة نوم')->value('id');
        DB::table('option_user')->updateOrInsert(['user_id' => $this->shop->id, 'option_id' => $bedroom], []);

        Sanctum::actingAs($this->shop);
        $this->itemId = (int) $this->postJson('/api/v2/business/menu/items', [
            'name_ar' => 'غرفة نوم بسعرين للفاتورة', 'base_price' => 30000, 'line_option_id' => $bedroom, 'available_quantity' => 10,
        ])->assertCreated()->json('data.id');
        $this->cashId = (int) $this->postJson("/api/v2/business/menu/items/{$this->itemId}/variants", ['type' => 'payment', 'name_ar' => 'كاش', 'price' => 30000, 'is_default' => true])->assertCreated()->json('data.id');
        $this->instalmentId = (int) $this->postJson("/api/v2/business/menu/items/{$this->itemId}/variants", ['type' => 'payment', 'name_ar' => 'تقسيط', 'price' => 36000])->assertCreated()->json('data.id');
    }

    public function test_the_instalment_price_goes_from_the_cart_to_the_placed_order(): void
    {
        Sanctum::actingAs($this->customer);

        $cart = $this->withHeaders(['Accept-Language' => 'ar'])
            ->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $this->itemId, 'qty' => 2, 'size_id' => $this->instalmentId, 'price' => 1])
            ->assertCreated()->json('data.cart');

        // The client cannot name a price; the picked variant's price is used.
        $line = Order::query()->where('user_id', $this->customer->id)->where('business_id', $this->shop->id)->where('status', 'cart')->firstOrFail()->items()->firstOrFail();
        $this->assertSame('36000.00', (string) $line->price, 'one unit at the instalment price');
        $this->assertSame('72000.00', (string) $line->total_price);
        $this->assertSame($this->instalmentId, (int) $line->size_id);
        $this->assertStringContainsString('تقسيط', $cart['items'][0]['name'], 'the cart line says it is an instalment');
        $this->assertSame(72000.0, (float) $cart['final_total']);

        $placed = $this->postJson("/api/v2/cart/{$this->shop->id}/checkout", ['fulfillment_type' => 'pickup', 'pickup_at' => now()->addDay()->toIso8601String()])->assertCreated();
        $orderId = (int) $placed->json('data.order.id');

        // The placed order: the same line, and totals built on 72,000 — never on the cash 60,000.
        $order = Order::query()->with('items')->findOrFail($orderId);
        $this->assertSame('pending', $order->status);
        $this->assertSame('72000.00', (string) $order->items->first()->total_price);
        $this->assertSame($this->instalmentId, (int) $order->items->first()->size_id);
        $this->assertSame(72000.0, round((float) $order->total, 2), 'the order total is the instalment lines, not the cash 60,000');

        // What the customer reads back — the «invoice»: price AND how it is paid.
        $detail = $this->withHeaders(['Accept-Language' => 'ar'])->getJson("/api/v2/orders/{$orderId}")->assertOk()->json('data');
        $this->assertSame(72000.0, (float) $detail['totals']['final_total']);
        $this->assertSame(36000.0, (float) $detail['items'][0]['price']);
        $this->assertSame(72000.0, (float) $detail['items'][0]['total_price']);
        $this->assertStringContainsString('تقسيط', $detail['items'][0]['name'], 'the invoice line says «تقسيط»');
        $this->assertStringNotContainsString('كاش', $detail['items'][0]['name']);

        // The merchant's order opens with the same line: price and «تقسيط».
        Sanctum::actingAs($this->shop);
        $mine = $this->withHeaders(['Accept-Language' => 'ar'])->getJson("/api/v2/business/orders/{$orderId}")->assertOk()->json('data');
        $this->assertSame(36000.0, (float) $mine['items'][0]['price']);
        $this->assertSame(72000.0, (float) $mine['totals']['final_total']);
        $this->assertStringContainsString('تقسيط', $mine['items'][0]['name'], 'the merchant sees it is an instalment');
    }

    public function test_the_cash_price_is_the_default_when_nothing_is_picked(): void
    {
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $this->itemId, 'qty' => 1])->assertCreated();

        $line = Order::query()->where('user_id', $this->customer->id)->where('business_id', $this->shop->id)->where('status', 'cart')->firstOrFail()->items()->firstOrFail();
        $this->assertSame('30000.00', (string) $line->price, 'no pick: the item price, which is the cash one');
        $this->assertStringNotContainsString('تقسيط', (string) $line->offering_label);
    }

    public function test_cash_picked_is_priced_and_named_as_cash(): void
    {
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $this->itemId, 'qty' => 1, 'size_id' => $this->cashId])->assertCreated();

        $line = Order::query()->where('user_id', $this->customer->id)->where('business_id', $this->shop->id)->where('status', 'cart')->firstOrFail()->items()->firstOrFail();
        $this->assertSame('30000.00', (string) $line->price);
        $this->assertStringContainsString('كاش', (string) $line->offering_label);
    }
}
