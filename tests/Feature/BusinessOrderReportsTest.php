<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * GET business/orders/reports -- date-range aggregates behind the new
 * business reports/analytics screen. Never returns raw order rows.
 */
class BusinessOrderReportsTest extends TestCase
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

    private function makeOrder(string $status, string $fulfillmentType, float $total, ?Carbon $createdAt = null): Order
    {
        $order = Order::create([
            'user_id' => $this->customer->id, 'business_id' => $this->business->id,
            'fulfillment_type' => $fulfillmentType, 'status' => $status,
            'total' => $total, 'discount' => 0, 'delivery_fee' => 0, 'service_fee' => 0,
            'tax' => 0, 'final_total' => $total, 'payment_method' => 'cash', 'address' => 'x',
        ]);

        if ($createdAt !== null) {
            $order->created_at = $createdAt;
            $order->save();
        }

        return $order;
    }

    public function test_summary_and_daily_breakdown_are_correct(): void
    {
        $today = Carbon::today();

        $this->makeOrder('completed', Order::FULFILLMENT_PICKUP, 100, $today);
        $this->makeOrder('completed', Order::FULFILLMENT_DELIVERY, 200, $today);
        $this->makeOrder('cancelled', Order::FULFILLMENT_PICKUP, 50, $today);
        $this->makeOrder('pending', Order::FULFILLMENT_DINE_IN, 75, $today->copy()->subDay());

        $response = $this->actingAs($this->business, 'sanctum')
            ->getJson('/api/v2/business/orders/reports?from=' . $today->copy()->subDay()->toDateString() . '&to=' . $today->toDateString())
            ->assertOk();

        $response->assertJsonPath('summary.total_orders', 4);
        $response->assertJsonPath('summary.completed_orders', 2);
        $response->assertJsonPath('summary.cancelled_orders', 1);
        $response->assertJsonPath('summary.pending_orders', 1);
        $this->assertEquals(300.0, $response->json('summary.total_revenue'));
        $this->assertEquals(150.0, $response->json('summary.average_order_value'));
        $response->assertJsonPath('by_fulfillment_type.pickup', 2);
        $response->assertJsonPath('by_fulfillment_type.delivery', 1);
        $response->assertJsonPath('by_fulfillment_type.dine_in', 1);

        $daily = $response->json('daily');
        $this->assertCount(2, $daily);
    }

    public function test_defaults_to_the_last_30_days_with_no_params(): void
    {
        $response = $this->actingAs($this->business, 'sanctum')
            ->getJson('/api/v2/business/orders/reports')
            ->assertOk();

        $from = Carbon::parse($response->json('from'));
        $to = Carbon::parse($response->json('to'));
        $this->assertSame(29, $from->diffInDays($to));
    }

    public function test_only_sees_its_own_orders(): void
    {
        $otherBusiness = User::query()->where('type', 'business')->where('id', '!=', $this->business->id)->first()
            ?: $this->markTestSkipped('Needs a second business user.');

        Order::create([
            'user_id' => $this->customer->id, 'business_id' => $otherBusiness->id,
            'fulfillment_type' => Order::FULFILLMENT_PICKUP, 'status' => 'completed',
            'total' => 999, 'discount' => 0, 'delivery_fee' => 0, 'service_fee' => 0,
            'tax' => 0, 'final_total' => 999, 'payment_method' => 'cash', 'address' => 'x',
        ]);
        $this->makeOrder('completed', Order::FULFILLMENT_PICKUP, 10);

        $response = $this->actingAs($this->business, 'sanctum')
            ->getJson('/api/v2/business/orders/reports')
            ->assertOk();

        $this->assertEquals(10.0, $response->json('summary.total_revenue'));
    }
}
