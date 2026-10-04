<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderInstallment;
use App\Models\User;
use App\Models\UserServiceFeeConsent;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\InstallmentPlan;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «القاعدة العامة فى تحصيل الرسوم: من فتح التقييم يخصم من حسابه تلقائيا عند كل عملية —
 * الرسوم ليس لها علاقة بكاش أو قسط» — المالك، 2026-10-03.
 * An instalment order is one operation like any other: its platform fee is the one a cash
 * order of the same value carries, it is taken from the wallet of the party in the fee
 * programme when the business accepts (never again per payment), and the fee is not part
 * of the instalment schedule. Rolls back.
 */
class InstallmentFeesTest extends TestCase
{
    use DatabaseTransactions;

    private User $business;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = User::query()->where('type', 'business')->where('category_child_id', 116)->where('category_id', 23)->orderBy('id')->firstOrFail();
        $this->customer = User::query()->where('type', '!=', 'business')->where('id', '!=', $this->business->id)->orderBy('id')->firstOrFail();

        UserServiceFeeConsent::updateOrCreate(
            ['user_id' => $this->business->id],
            ['fee_auto_charge_enabled' => true, 'rating_enabled' => true, 'enabled_at' => now()]
        );
        // The customer opened their rating too: the fee line is theirs to carry.
        UserServiceFeeConsent::updateOrCreate(
            ['user_id' => $this->customer->id],
            ['fee_auto_charge_enabled' => true, 'rating_enabled' => true, 'enabled_at' => now()]
        );
        app(WalletService::class)->getOrCreateWallet((int) $this->business->id)
            ->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => 1000, 'locked_balance' => 0]);
    }

    /** An instalment order (36000 over 12 months) carrying a platform fee, as checkout leaves it. */
    private function instalmentOrder(float $fee): Order
    {
        $order = Order::create([
            'user_id' => $this->customer->id, 'business_id' => $this->business->id,
            'fulfillment_type' => Order::FULFILLMENT_PICKUP, 'status' => 'pending',
            'total' => 36000, 'discount' => 0, 'delivery_fee' => 0, 'service_fee' => $fee,
            'tax' => 0, 'final_total' => 36000 + $fee, 'payment_method' => 'cash', 'address' => 'x',
        ]);
        DB::table('order_items')->insert([
            'order_id' => $order->id, 'offering_type' => 'App\\Models\\MenuItem', 'offering_id' => 1, 'offering_label' => 'تقسيط',
            'qty' => 1, 'price' => 36000, 'total_price' => 36000, 'installment_months' => 12,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        app(InstallmentPlan::class)->rebuild($order);

        return $order;
    }

    private function balance(): float
    {
        return (float) Wallet::where('user_id', $this->business->id)->value('balance');
    }

    public function test_the_fee_is_taken_once_at_accept_whatever_the_payment_plan(): void
    {
        $order = $this->instalmentOrder(5.00);

        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/business/orders/{$order->id}/accept")->assertOk();

        $this->assertEquals(995.0, $this->balance());
        $this->assertSame(1, WalletTransaction::where('idempotency_key', 'order_fee:' . $order->id)->count());
    }

    public function test_collecting_the_payments_never_charges_a_fee_again(): void
    {
        $order = $this->instalmentOrder(5.00);

        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/business/orders/{$order->id}/accept")->assertOk();
        $after = $this->balance();

        foreach (range(1, 12) as $seq) {
            $this->postJson("/api/v2/business/orders/{$order->id}/installments/{$seq}/collect")->assertOk();
        }

        $this->assertEquals($after, $this->balance(), 'a payment collected is not an operation');
        $this->assertSame(1, WalletTransaction::where('reference_type', 'order')->where('reference_id', $order->id)->where('type', WalletTransaction::TYPE_PLATFORM_FEE)->count());
    }

    public function test_the_fee_is_not_part_of_the_schedule(): void
    {
        $order = $this->instalmentOrder(5.00);

        $this->assertSame(36000.0, round((float) OrderInstallment::where('order_id', $order->id)->sum('amount'), 2), 'the schedule is the goods; the fee is its own deduction');
        $this->assertEquals(36005.0, (float) $order->final_total);
    }

    public function test_an_instalment_carries_the_same_fee_as_cash_of_the_same_value(): void
    {
        $bedroom = (int) DB::table('options')->where('group_id', 3)->where('name_ar', 'غرفة نوم')->value('id');
        DB::table('option_user')->updateOrInsert(['user_id' => $this->business->id, 'option_id' => $bedroom], []);
        DB::table('business_working_hours')->where('business_id', $this->business->id)->delete();

        Sanctum::actingAs($this->business);
        $item = (int) $this->postJson('/api/v2/business/menu/items', ['name_ar' => 'غرفة رسوم', 'base_price' => 36000, 'line_option_id' => $bedroom, 'available_quantity' => 10])->assertCreated()->json('data.id');
        // Same total on both ways of paying: the fee is the operation's, not the way it is paid.
        $instalment = (int) $this->putJson("/api/v2/business/menu/items/{$item}/payment-plans", ['plans' => [['months' => 12, 'total_price' => 36000]]])->assertOk()->json('data.payment_plans.0.id');

        $fees = [];
        foreach ([null, $instalment] as $plan) {
            Sanctum::actingAs($this->customer);
            $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $item, 'qty' => 1] + ($plan ? ['plan_id' => $plan] : []))->assertCreated();
            $orderId = (int) $this->postJson("/api/v2/cart/{$this->business->id}/checkout", ['fulfillment_type' => 'pickup', 'pickup_at' => now()->addDay()->toIso8601String()])->assertCreated()->json('data.order.id');
            $fees[] = (float) Order::findOrFail($orderId)->service_fee;
        }

        $this->assertGreaterThan(0, $fees[0], 'the business is in the fee programme, so the operation carries a fee');
        $this->assertSame($fees[0], $fees[1], 'cash or instalment, the operation costs the same');
    }
}
