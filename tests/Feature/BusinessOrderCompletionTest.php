<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Pickup/dine-in orders had no way to ever reach status=completed -- only
 * delivery orders could, through DeliveryDispatchService's QR handover. This
 * covers the new one-tap business "complete" action that closes that gap.
 */
class BusinessOrderCompletionTest extends TestCase
{
    use DatabaseTransactions;

    private User $business;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = User::query()->where('type', 'business')->orderBy('id')->first()
            ?: $this->markTestSkipped('Needs a business user.');
        $this->customer = User::query()->where('id', '!=', $this->business->id)->orderBy('id')->firstOrFail();
    }

    private function makeOrder(string $fulfillmentType): Order
    {
        return Order::create([
            'user_id' => $this->customer->id, 'business_id' => $this->business->id,
            'fulfillment_type' => $fulfillmentType, 'status' => 'pending',
            'total' => 50, 'discount' => 0, 'delivery_fee' => 0, 'service_fee' => 0,
            'tax' => 0, 'final_total' => 50, 'payment_method' => 'cash', 'address' => 'x',
        ]);
    }

    private function advanceToReady(Order $order): void
    {
        $order->prep_status = Order::PREP_READY;
        $order->save();
    }

    public function test_business_can_complete_a_ready_pickup_order(): void
    {
        $order = $this->makeOrder(Order::FULFILLMENT_PICKUP);
        $this->advanceToReady($order);

        $this->actingAs($this->business, 'sanctum')
            ->postJson("/api/v2/business/orders/{$order->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_business_can_complete_a_ready_dine_in_order(): void
    {
        $order = $this->makeOrder(Order::FULFILLMENT_DINE_IN);
        $this->advanceToReady($order);

        $this->actingAs($this->business, 'sanctum')
            ->postJson("/api/v2/business/orders/{$order->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_delivery_orders_must_complete_through_the_qr_handover_instead(): void
    {
        $order = $this->makeOrder(Order::FULFILLMENT_DELIVERY);
        $this->advanceToReady($order);

        $this->actingAs($this->business, 'sanctum')
            ->postJson("/api/v2/business/orders/{$order->id}/complete")
            ->assertStatus(409);

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_a_not_yet_ready_order_cannot_be_completed(): void
    {
        $order = $this->makeOrder(Order::FULFILLMENT_PICKUP);
        $order->prep_status = Order::PREP_PREPARING;
        $order->save();

        $this->actingAs($this->business, 'sanctum')
            ->postJson("/api/v2/business/orders/{$order->id}/complete")
            ->assertStatus(409);
    }

    public function test_completion_notifies_the_customer_naming_the_business(): void
    {
        $order = $this->makeOrder(Order::FULFILLMENT_PICKUP);
        $this->advanceToReady($order);

        $this->actingAs($this->business, 'sanctum')
            ->postJson("/api/v2/business/orders/{$order->id}/complete")
            ->assertOk();

        $notification = AppNotification::query()
            ->where('user_id', $this->customer->id)
            ->where('meta->order_id', $order->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString((string) $this->business->name, (string) $notification->body_ar);
        $this->assertSame('open_customer_order', $notification->action_type);
        $this->assertSame('/orders/' . $order->id, $notification->action_url);
    }

    public function test_another_businesss_order_cannot_be_completed(): void
    {
        $otherBusiness = User::query()->where('type', 'business')->where('id', '!=', $this->business->id)->first()
            ?: $this->markTestSkipped('Needs a second business user.');

        $order = $this->makeOrder(Order::FULFILLMENT_PICKUP);
        $this->advanceToReady($order);

        $this->actingAs($otherBusiness, 'sanctum')
            ->postJson("/api/v2/business/orders/{$order->id}/complete")
            ->assertStatus(404);
    }
}
