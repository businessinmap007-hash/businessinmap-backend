<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderInstallment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «خلي طريقة الدفع تقسم دفعات اذا كانت قسط على تواريخ فى النتيجة وحسب عدد الاشهر» —
 * المالك، 2026-10-03; payment plans are the ITEM's own (not a variant), 2026-10-04. The merchant says
 * what an instalment plan costs and over how many months; an order bought that way carries its
 * payments: one per month, with its date and amount, adding up to what the instalment lines cost.
 * The cart says it before the order is placed. Cash lines are not scheduled. Rolls back.
 */
class InstallmentPlanTest extends TestCase
{
    use DatabaseTransactions;

    private User $shop;
    private User $customer;
    private int $itemId;
    private int $planId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = User::query()->where('type', 'business')->where('category_child_id', 116)->where('category_id', 23)->orderBy('id')->firstOrFail();

        $this->offerDelivery($this->shop);
        $this->customer = User::query()->where('type', '!=', 'business')->where('id', '!=', $this->shop->id)->orderBy('id')->firstOrFail();
        DB::table('business_working_hours')->where('business_id', $this->shop->id)->delete();

        $bedroom = (int) DB::table('options')->where('group_id', 3)->where('name_ar', 'غرفة نوم')->value('id');
        DB::table('option_user')->updateOrInsert(['user_id' => $this->shop->id, 'option_id' => $bedroom], []);

        Sanctum::actingAs($this->shop);
        $this->itemId = (int) $this->postJson('/api/v2/business/menu/items', ['name_ar' => 'غرفة نوم بالتقسيط', 'base_price' => 30000, 'line_option_id' => $bedroom, 'available_quantity' => 10])->assertCreated()->json('data.id');
        $this->planId = $this->plans([['months' => 3, 'total_price' => 100000]])[0]['id'];
    }

    /** @return list<array<string,mixed>> */
    private function plans(array $plans): array
    {
        Sanctum::actingAs($this->shop);

        return $this->putJson("/api/v2/business/menu/items/{$this->itemId}/payment-plans", ['plans' => $plans])->assertOk()->json('data.payment_plans');
    }

    /** @param  list<array{0:?int,1:int}>  $lines  [plan id or null for cash, qty] */
    private function place(array $lines): int
    {
        Sanctum::actingAs($this->customer);
        foreach ($lines as [$plan, $qty]) {
            $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $this->itemId, 'qty' => $qty] + ($plan ? ['plan_id' => $plan] : []))->assertCreated();
        }

        return (int) $this->postJson("/api/v2/cart/{$this->shop->id}/checkout", ['fulfillment_type' => 'pickup', 'pickup_at' => now()->addDay()->toIso8601String()])->assertCreated()->json('data.order.id');
    }

    public function test_the_plan_is_the_items_own_and_the_customer_is_given_its_months(): void
    {
        $plans = $this->getJson("/api/v2/discovery/menu-items/{$this->itemId}")->assertOk()->json('data.item.payment_plans');

        $this->assertCount(1, $plans);
        $this->assertSame(3, $plans[0]['months']);
        $this->assertEquals(100000, $plans[0]['unit_price']);

        Sanctum::actingAs($this->shop);
        $this->putJson("/api/v2/business/menu/items/{$this->itemId}/payment-plans", ['plans' => [['months' => 1, 'total_price' => 50000]]])->assertUnprocessable();
    }

    public function test_the_cart_says_what_the_plan_will_be_before_the_order_is_placed(): void
    {
        Sanctum::actingAs($this->customer);

        $cart = $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $this->itemId, 'qty' => 1, 'plan_id' => $this->planId])->assertCreated()->json('data.cart');

        $this->assertSame(3, $cart['installment_plan']['count']);
        $this->assertSame(33333.33, $cart['installment_plan']['monthly']);
        $this->assertEquals(100000, $cart['installment_plan']['total']);
        $this->assertSame(now()->startOfDay()->addMonthNoOverflow()->toDateString(), $cart['installment_plan']['first_due_on']);
    }

    public function test_a_placed_order_carries_one_payment_per_month_adding_up_to_the_line(): void
    {
        $orderId = $this->place([[$this->planId, 1]]);

        $rows = OrderInstallment::query()->where('order_id', $orderId)->orderBy('seq')->get();

        $this->assertCount(3, $rows);
        $this->assertSame(['33333.33', '33333.33', '33333.34'], $rows->pluck('amount')->map(fn ($a) => (string) $a)->all(), 'the last month takes the rounding');
        $this->assertSame(100000.0, round($rows->sum(fn ($r) => (float) $r->amount), 2));
        $today = now()->startOfDay();
        $this->assertSame([$today->copy()->addMonthNoOverflow()->toDateString(), $today->copy()->addMonthsNoOverflow(2)->toDateString(), $today->copy()->addMonthsNoOverflow(3)->toDateString()], $rows->map(fn ($r) => $r->due_on->toDateString())->all());

        // The customer and the merchant both read the schedule on the order.
        $mine = $this->getJson("/api/v2/orders/{$orderId}")->assertOk()->json('data.installments');
        $this->assertCount(3, $mine);
        $this->assertSame(33333.33, (float) $mine[0]['amount']);
        $this->assertNull($mine[0]['paid_at']);

        Sanctum::actingAs($this->shop);
        $theirs = $this->getJson("/api/v2/business/orders/{$orderId}")->assertOk()->json('data.installments');
        $this->assertSame($mine, $theirs);
    }

    public function test_an_order_paid_in_one_go_has_no_schedule(): void
    {
        $orderId = $this->place([[null, 1]]);

        $this->assertSame(0, OrderInstallment::query()->where('order_id', $orderId)->count());
        $this->assertSame([], $this->getJson("/api/v2/orders/{$orderId}")->assertOk()->json('data.installments'));
    }

    public function test_only_the_instalment_lines_are_scheduled_when_cash_and_instalments_are_mixed(): void
    {
        $orderId = $this->place([[null, 1], [$this->planId, 2]]);

        $rows = OrderInstallment::query()->where('order_id', $orderId)->get();

        $this->assertCount(3, $rows);
        $this->assertSame(200000.0, round($rows->sum(fn ($r) => (float) $r->amount), 2), 'two instalment units; the cash 30000 is not scheduled');
        $this->assertSame(230000.0, round((float) Order::query()->findOrFail($orderId)->total, 2));
    }

    public function test_a_down_payment_goes_with_the_first_month_and_the_rest_is_split(): void
    {
        $down = $this->plans([['months' => 12, 'down' => 12000, 'total_price' => 36000]])[0];
        $this->assertEquals(12000, $this->getJson("/api/v2/discovery/menu-items/{$this->itemId}")->assertOk()->json('data.item.payment_plans.0.down'));

        Sanctum::actingAs($this->customer);
        $cart = $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $this->itemId, 'qty' => 1, 'plan_id' => $down['id']])->assertCreated()->json('data.cart');
        $this->assertSame(2000.0, (float) $cart['installment_plan']['monthly']);
        $this->assertSame(14000.0, (float) $cart['installment_plan']['first_amount']);

        $orderId = (int) $this->postJson("/api/v2/cart/{$this->shop->id}/checkout", ['fulfillment_type' => 'pickup', 'pickup_at' => now()->addDay()->toIso8601String()])->assertCreated()->json('data.order.id');
        $rows = OrderInstallment::query()->where('order_id', $orderId)->orderBy('seq')->get()->map(fn ($r) => (float) $r->amount);

        $this->assertCount(12, $rows);
        $this->assertSame(14000.0, $rows[0], '12000 down + the first 2000');
        $this->assertSame(22000.0, round($rows->slice(1)->sum(), 2), 'the other 11 months');
        $this->assertSame(36000.0, round($rows->sum(), 2));
    }

    public function test_a_plan_follows_the_cash_price_it_is_a_markup_over(): void
    {
        $this->plans([['months' => 12, 'total_price' => 36000]]); // +20% over 30000

        Sanctum::actingAs($this->shop);
        $bedroom = (int) DB::table('options')->where('group_id', 3)->where('name_ar', 'غرفة نوم')->value('id');
        $this->putJson("/api/v2/business/menu/items/{$this->itemId}", ['name_ar' => 'غرفة نوم بالتقسيط', 'base_price' => 40000, 'line_option_id' => $bedroom])->assertOk();

        $this->assertEquals(48000, $this->getJson("/api/v2/discovery/menu-items/{$this->itemId}")->assertOk()->json('data.item.payment_plans.0.unit_price'), 'the cash price moved, the 20% markup came with it');
    }

    public function test_a_plan_is_never_cheaper_than_cash_and_the_down_payment_is_less_than_the_plan(): void
    {
        Sanctum::actingAs($this->shop);

        $this->putJson("/api/v2/business/menu/items/{$this->itemId}/payment-plans", ['plans' => [['months' => 6, 'total_price' => 20000]]])->assertUnprocessable();
        $this->putJson("/api/v2/business/menu/items/{$this->itemId}/payment-plans", ['plans' => [['months' => 6, 'down' => 36000, 'total_price' => 36000]]])->assertUnprocessable();
    }

    public function test_food_is_never_sold_on_instalments(): void
    {
        $fishShop = User::query()->where('type', 'business')->where('category_child_id', 101)->orderBy('id')->firstOrFail();
        $shrimp = (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')->where('g.name_ar', 'أنواع الأسماك والمأكولات البحرية')->where('o.name_ar', 'جمبري')->value('o.id');
        Sanctum::actingAs($fishShop);
        $fish = (int) $this->postJson('/api/v2/business/menu/items', ['name_ar' => 'جمبري', 'base_price' => 300, 'line_option_id' => $shrimp])->assertCreated()->json('data.id');

        $this->putJson("/api/v2/business/menu/items/{$fish}/payment-plans", ['plans' => [['months' => 6, 'total_price' => 400]]])->assertUnprocessable();
        $this->assertFalse($this->getJson("/api/v2/business/menu/items/{$fish}")->assertOk()->json('data.allows_payment_plans'));
    }

    public function test_instalment_is_no_longer_a_variant_of_the_item(): void
    {
        Sanctum::actingAs($this->shop);

        $this->postJson("/api/v2/business/menu/items/{$this->itemId}/variants", ['type' => 'payment', 'name_ar' => 'تقسيط', 'price' => 36000, 'installment_months' => 12])->assertUnprocessable();
    }
}
