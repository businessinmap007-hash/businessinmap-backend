<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * "لو الصنف نفذ، تحب نعمل إيه؟" — the customer states one policy per order
 * at checkout (Order::out_of_stock_policy); a business discovering a specific
 * line can't actually be fulfilled applies it via
 * OrderController::businessMarkItemUnavailable. No wallet fee configured
 * (service_fee=0), so acceptance settles nothing and needs no wallet setup.
 * Rolls back.
 */
class MenuOrderItemUnavailableTest extends TestCase
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

    /** @param list<float> $linePrices */
    private function makeAcceptedOrder(?string $policy, array $linePrices): Order
    {
        $total = array_sum($linePrices);

        $order = Order::create([
            'user_id' => $this->customer->id,
            'business_id' => $this->business->id,
            'fulfillment_type' => Order::FULFILLMENT_DELIVERY,
            'status' => 'pending',
            'prep_status' => Order::PREP_ACCEPTED,
            'total' => $total,
            'discount' => 0,
            'delivery_fee' => 0,
            'service_fee' => 0,
            'tax' => 0,
            'final_total' => $total,
            'payment_method' => 'cash',
            'address' => 'x',
            'out_of_stock_policy' => $policy,
        ]);

        foreach ($linePrices as $i => $price) {
            OrderItem::create([
                'order_id' => $order->id,
                'offering_label' => 'صنف ' . ($i + 1),
                'qty' => 1,
                'price' => $price,
                'total_price' => $price,
            ]);
        }

        return $order->fresh('items');
    }

    private function endpoint(Order $order, OrderItem $item): string
    {
        return "/api/v2/business/orders/{$order->id}/items/{$item->id}/unavailable";
    }

    public function test_substitute_requires_a_note_and_keeps_the_total(): void
    {
        $order = $this->makeAcceptedOrder(Order::OUT_OF_STOCK_SUBSTITUTE, [50, 30]);
        $item = $order->items->first();

        $response = $this->actingAs($this->business, 'sanctum')
            ->postJson($this->endpoint($order, $item), ['note' => 'بيبسي بدل كوكاكولا'])
            ->assertOk();
        $this->assertEqualsWithDelta(80.0, (float) $response->json('data.totals.final_total'), 0.001);

        $this->assertSame(OrderItem::RESOLUTION_SUBSTITUTED, $item->fresh()->resolution);
        $this->assertSame('بيبسي بدل كوكاكولا', $item->fresh()->resolution_note);
        $this->assertSame('pending', $order->fresh()->status, 'a substitution never touches order status');
    }

    public function test_substitute_without_a_note_is_rejected(): void
    {
        $order = $this->makeAcceptedOrder(Order::OUT_OF_STOCK_SUBSTITUTE, [50]);
        $item = $order->items->first();

        $this->actingAs($this->business, 'sanctum')
            ->postJson($this->endpoint($order, $item), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['note']);

        $this->assertNull($item->fresh()->resolution);
    }

    public function test_remove_drops_the_line_from_the_total(): void
    {
        $order = $this->makeAcceptedOrder(Order::OUT_OF_STOCK_REMOVE, [50, 30]);
        $missing = $order->items->first();

        $response = $this->actingAs($this->business, 'sanctum')
            ->postJson($this->endpoint($order, $missing))
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');
        $this->assertEqualsWithDelta(30.0, (float) $response->json('data.totals.final_total'), 0.001);

        $this->assertSame(OrderItem::RESOLUTION_REMOVED, $missing->fresh()->resolution);
        $this->assertEqualsWithDelta(30.0, (float) $order->fresh()->final_total, 0.001);
    }

    public function test_cancel_cancels_the_whole_order(): void
    {
        $order = $this->makeAcceptedOrder(Order::OUT_OF_STOCK_CANCEL, [50, 30]);
        $item = $order->items->first();

        $this->actingAs($this->business, 'sanctum')
            ->postJson($this->endpoint($order, $item))
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_no_stated_policy_refuses_to_guess(): void
    {
        $order = $this->makeAcceptedOrder(null, [50]);
        $item = $order->items->first();

        $this->actingAs($this->business, 'sanctum')
            ->postJson($this->endpoint($order, $item))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['policy']);

        $this->assertNull($item->fresh()->resolution);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_an_already_resolved_line_cannot_be_marked_again(): void
    {
        $order = $this->makeAcceptedOrder(Order::OUT_OF_STOCK_REMOVE, [50, 30]);
        $item = $order->items->first();

        $this->actingAs($this->business, 'sanctum')->postJson($this->endpoint($order, $item))->assertOk();
        $this->actingAs($this->business, 'sanctum')->postJson($this->endpoint($order, $item))->assertStatus(409);
    }

    public function test_a_foreign_business_cannot_mark_another_businesss_order_item(): void
    {
        $order = $this->makeAcceptedOrder(Order::OUT_OF_STOCK_REMOVE, [50]);
        $item = $order->items->first();
        $otherBusiness = User::query()->where('type', 'business')->where('id', '!=', $this->business->id)->first()
            ?: $this->markTestSkipped('Needs a second business user.');

        $this->actingAs($otherBusiness, 'sanctum')
            ->postJson($this->endpoint($order, $item))
            ->assertNotFound();

        $this->assertNull($item->fresh()->resolution);
    }

    public function test_marking_unavailable_once_the_order_is_ready_is_refused(): void
    {
        $order = $this->makeAcceptedOrder(Order::OUT_OF_STOCK_REMOVE, [50]);
        $order->update(['prep_status' => Order::PREP_READY]);
        $item = $order->items->first();

        $this->actingAs($this->business, 'sanctum')
            ->postJson($this->endpoint($order, $item))
            ->assertStatus(409);
    }

    /** Through the real checkout endpoint, not the direct Order::create() shortcut the rest of this file uses. */
    public function test_checkout_persists_the_chosen_policy(): void
    {
        $item = MenuItem::query()->create([
            'business_id' => $this->business->id,
            'name_ar' => 'صنف الاختبار',
            'price' => 40,
            'is_active' => true,
        ]);

        $this->actingAs($this->customer, 'sanctum')->postJson('/api/v2/cart/items', [
            'kind' => 'menu',
            'offering_id' => $item->id,
            'qty' => 1,
        ])->assertSuccessful();

        $orderId = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v2/cart/' . $this->business->id . '/checkout', [
                'fulfillment_type' => 'pickup',
                'out_of_stock_policy' => Order::OUT_OF_STOCK_SUBSTITUTE,
            ])->assertCreated()->json('data.order.id');

        $this->assertSame(Order::OUT_OF_STOCK_SUBSTITUTE, Order::findOrFail($orderId)->out_of_stock_policy);
    }
}
