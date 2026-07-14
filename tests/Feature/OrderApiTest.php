<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * v2 placed-order API: customer history + party-only detail + cancel (before
 * acceptance) and the business queue scoping. Rolls back.
 */
class OrderApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $customer;

    private User $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = User::query()->where('type', 'business')->orderBy('id')->first()
            ?: $this->markTestSkipped('Needs a business user.');
        $this->customer = User::query()->where('id', '!=', $this->business->id)->orderBy('id')->firstOrFail();
    }

    private function makeOrder(string $status = 'pending'): Order
    {
        return Order::create([
            'user_id' => $this->customer->id, 'business_id' => $this->business->id,
            'fulfillment_type' => Order::FULFILLMENT_DELIVERY, 'status' => $status,
            'total' => 40, 'discount' => 0, 'delivery_fee' => 0, 'service_fee' => 0,
            'tax' => 0, 'final_total' => 40, 'payment_method' => 'cash', 'address' => 'x',
        ]);
    }

    public function test_customer_index_lists_placed_but_not_cart_orders(): void
    {
        $placed = $this->makeOrder('pending');
        $cart = $this->makeOrder('cart');

        $ids = collect(
            $this->actingAs($this->customer, 'sanctum')->getJson('/api/v2/orders')->assertOk()->json('data')
        )->pluck('id')->all();

        $this->assertContains($placed->id, $ids);
        $this->assertNotContains($cart->id, $ids);
    }

    public function test_show_is_party_only(): void
    {
        $order = $this->makeOrder();
        $stranger = User::query()
            ->whereNotIn('id', [$this->customer->id, $this->business->id])
            ->orderBy('id')->first();

        $this->actingAs($this->customer, 'sanctum')->getJson("/api/v2/orders/{$order->id}")->assertOk();

        if ($stranger) {
            $this->actingAs($stranger, 'sanctum')->getJson("/api/v2/orders/{$order->id}")->assertForbidden();
        }
    }

    public function test_customer_can_cancel_pending_order_before_acceptance(): void
    {
        $order = $this->makeOrder('pending');

        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v2/orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_business_queue_is_scoped_to_the_business(): void
    {
        $order = $this->makeOrder('pending');

        $ids = collect(
            $this->actingAs($this->business, 'sanctum')->getJson('/api/v2/business/orders')->assertOk()->json('data')
        )->pluck('id')->all();

        $this->assertContains($order->id, $ids);

        // A non-party business must not see it.
        $otherBiz = User::query()->where('type', 'business')->where('id', '!=', $this->business->id)->first();
        if ($otherBiz) {
            $otherIds = collect(
                $this->actingAs($otherBiz, 'sanctum')->getJson('/api/v2/business/orders')->assertOk()->json('data')
            )->pluck('id')->all();
            $this->assertNotContains($order->id, $otherIds);
        }
    }
}
