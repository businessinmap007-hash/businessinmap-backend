<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * "استلام من المكان" (pickup) requires a pickup_at time up front, at
 * checkout — the same way delivery requires an address. See
 * CustomerCartService::placeOrder() and CartController::checkout().
 */
class CheckoutPickupTimeTest extends TestCase
{
    use DatabaseTransactions;

    private User $customer;
    private int $businessId;
    private int $menuId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::query()->orderBy('id')->firstOrFail();
        $businessId = User::query()->where('type', 'business')->value('id');

        if (! $businessId) {
            $this->markTestSkipped('Needs a business.');
        }

        $this->businessId = (int) $businessId;

        $this->menuId = MenuItem::create([
            'business_id' => $this->businessId, 'name_ar' => 'صنف اختبار',
            'name_en' => 'Test', 'base_price' => 50.00, 'is_active' => 1,
        ])->id;
    }

    private function fillCart(): void
    {
        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $this->menuId, 'qty' => 1])
            ->assertCreated();
    }

    private function placedOrder(): Order
    {
        return Order::query()->where('user_id', $this->customer->id)
            ->where('business_id', $this->businessId)->where('status', 'pending')->latest('id')->firstOrFail();
    }

    public function test_pickup_without_a_time_is_rejected(): void
    {
        Sanctum::actingAs($this->customer);
        $this->fillCart();

        $this->postJson("/api/v2/cart/{$this->businessId}/checkout", [
            'fulfillment_type' => 'pickup',
        ])->assertStatus(422)->assertJsonValidationErrors(['pickup_at']);
    }

    public function test_a_past_pickup_time_is_rejected(): void
    {
        Sanctum::actingAs($this->customer);
        $this->fillCart();

        $this->postJson("/api/v2/cart/{$this->businessId}/checkout", [
            'fulfillment_type' => 'pickup',
            'pickup_at' => now()->subHour()->toIso8601String(),
        ])->assertStatus(422)->assertJsonValidationErrors(['pickup_at']);
    }

    public function test_pickup_with_a_future_time_is_stored_and_returned(): void
    {
        Sanctum::actingAs($this->customer);
        $this->fillCart();

        $when = now()->addHours(2)->second(0)->microsecond(0);

        $res = $this->postJson("/api/v2/cart/{$this->businessId}/checkout", [
            'fulfillment_type' => 'pickup',
            'pickup_at' => $when->toIso8601String(),
        ])->assertCreated();

        $this->assertNotNull($res->json('data.order.pickup_at'));

        $order = $this->placedOrder();
        $this->assertNotNull($order->pickup_at);
        $this->assertEqualsWithDelta($when->timestamp, $order->pickup_at->timestamp, 1);
    }

    public function test_delivery_never_requires_a_pickup_time(): void
    {
        Sanctum::actingAs($this->customer);
        $this->fillCart();

        $this->postJson("/api/v2/cart/{$this->businessId}/checkout", [
            'fulfillment_type' => 'delivery',
            'address' => 'شارع النصر',
        ])->assertCreated();

        $order = $this->placedOrder();
        $this->assertNull($order->pickup_at);
    }
}
