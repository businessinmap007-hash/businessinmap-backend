<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\UserServiceFeeConsent;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\DeliveryDispatchService;
use App\Services\MenuBillingService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Menu order prep lifecycle (accept → preparing → ready) and the platform fee
 * settlement it triggers against the business wallet. Rolls back.
 */
class OrderFeeSettlementLifecycleTest extends TestCase
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

        // Business in the fee programme, wallet funded.
        UserServiceFeeConsent::updateOrCreate(
            ['user_id' => $this->business->id],
            ['fee_auto_charge_enabled' => true, 'rating_enabled' => true, 'enabled_at' => now()]
        );
        app(WalletService::class)->getOrCreateWallet((int) $this->business->id)
            ->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => 100, 'locked_balance' => 0]);
    }

    private function makeOrder(float $fee): Order
    {
        return Order::create([
            'user_id' => $this->customer->id, 'business_id' => $this->business->id,
            'fulfillment_type' => Order::FULFILLMENT_DELIVERY, 'status' => 'pending',
            'total' => 50, 'discount' => 0, 'delivery_fee' => 0, 'service_fee' => $fee,
            'tax' => 0, 'final_total' => 50 + $fee, 'payment_method' => 'cash', 'address' => 'x',
        ]);
    }

    public function test_accept_settles_fee_from_business_wallet_and_advances_prep(): void
    {
        $order = $this->makeOrder(5.00);

        $this->actingAs($this->business, 'sanctum')
            ->postJson("/api/v2/business/orders/{$order->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.prep_status', Order::PREP_ACCEPTED);

        $this->assertEquals(95.0, (float) Wallet::where('user_id', $this->business->id)->value('balance'));
        $this->assertDatabaseHas('wallet_transactions', [
            'idempotency_key' => 'order_fee:' . $order->id,
            'type' => WalletTransaction::TYPE_PLATFORM_FEE,
            'reference_type' => 'order',
        ]);
    }

    public function test_accept_blocked_when_business_wallet_insufficient(): void
    {
        Wallet::where('user_id', $this->business->id)->update(['balance' => 2]);
        $order = $this->makeOrder(5.00);

        $this->actingAs($this->business, 'sanctum')
            ->postJson("/api/v2/business/orders/{$order->id}/accept")
            ->assertStatus(422);

        $this->assertNull($order->fresh()->prep_status, 'prep stays null when blocked');
        $this->assertEquals(2.0, (float) Wallet::where('user_id', $this->business->id)->value('balance'));
    }

    public function test_preparing_exposes_delivery_order_to_drivers(): void
    {
        $order = $this->makeOrder(5.00);

        $this->actingAs($this->business, 'sanctum')->postJson("/api/v2/business/orders/{$order->id}/accept")->assertOk();
        $this->actingAs($this->business, 'sanctum')->postJson("/api/v2/business/orders/{$order->id}/preparing")->assertOk();

        $visible = app(DeliveryDispatchService::class)->availableOrders(200)->pluck('id')->all();
        $this->assertContains($order->id, $visible);
    }

    public function test_customer_cannot_cancel_after_acceptance(): void
    {
        $order = $this->makeOrder(5.00);
        $this->actingAs($this->business, 'sanctum')->postJson("/api/v2/business/orders/{$order->id}/accept")->assertOk();

        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v2/orders/{$order->id}/cancel")
            ->assertStatus(409);
    }

    public function test_no_service_fee_row_for_business_without_consent(): void
    {
        UserServiceFeeConsent::updateOrCreate(
            ['user_id' => $this->business->id],
            ['fee_auto_charge_enabled' => false]
        );

        $this->assertNull(app(MenuBillingService::class)->feeRowForBusiness((int) $this->business->id));
    }
}
