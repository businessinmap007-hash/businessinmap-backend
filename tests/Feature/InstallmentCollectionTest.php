<?php

namespace Tests\Feature;

use App\Models\AgendaItem;
use App\Models\Order;
use App\Models\OrderInstallment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «اختارت تقسيط ولم تظهر الدفعات فى اجندتى … واين يظهر للتاجر مواعيد الاقساط التى سيتم
 * تحصيلها وكم المبالغ وكم الاجمالى» — المالك، 2026-10-03.
 * An instalment order puts one reminder per payment on the customer's agenda; the
 * business records what it collects; its reports page gets a second «تقسيط» view —
 * only when it sells on instalments, with what is still to collect by date.
 * Rolls back.
 */
class InstallmentCollectionTest extends TestCase
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
        DB::table('business_working_hours')->where('business_id', $this->shop->id)->delete();

        $bedroom = (int) DB::table('options')->where('group_id', 3)->where('name_ar', 'غرفة نوم')->value('id');
        DB::table('option_user')->updateOrInsert(['user_id' => $this->shop->id, 'option_id' => $bedroom], []);

        Sanctum::actingAs($this->shop);
        $this->itemId = (int) $this->postJson('/api/v2/business/menu/items', ['name_ar' => 'غرفة نوم للتحصيل', 'base_price' => 30000, 'line_option_id' => $bedroom, 'available_quantity' => 10])->assertCreated()->json('data.id');
        $this->cashId = (int) $this->postJson("/api/v2/business/menu/items/{$this->itemId}/variants", ['type' => 'payment', 'name_ar' => 'كاش', 'price' => 30000, 'is_default' => true])->assertCreated()->json('data.id');
        $this->instalmentId = (int) $this->postJson("/api/v2/business/menu/items/{$this->itemId}/variants", ['type' => 'payment', 'name_ar' => 'تقسيط', 'price' => 36000, 'installment_months' => 12, 'installment_down' => 12000])->assertCreated()->json('data.id');
    }

    private function place(int $variantId): array
    {
        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $this->itemId, 'qty' => 1, 'size_id' => $variantId])->assertCreated();

        return $this->postJson("/api/v2/cart/{$this->shop->id}/checkout", ['fulfillment_type' => 'pickup', 'pickup_at' => now()->addDay()->toIso8601String()])->assertCreated()->json('data.order');
    }

    public function test_the_placed_order_says_its_schedule_and_what_is_paid(): void
    {
        $order = $this->place($this->instalmentId);

        $this->assertCount(12, $order['installments']);
        $this->assertSame(14000.0, (float) $order['installments'][0]['amount']);
        $this->assertNull($order['installments'][0]['paid_at']);
    }

    public function test_every_payment_lands_on_the_customers_agenda_as_a_reminder(): void
    {
        $order = $this->place($this->instalmentId);

        $items = AgendaItem::query()->where('user_id', $this->customer->id)->where('kind', AgendaItem::KIND_INSTALLMENT)->orderBy('starts_at')->get();

        $this->assertCount(12, $items);
        $this->assertFalse((bool) $items[0]->blocking, 'a payment reminds; it never blocks a booking');
        $this->assertTrue((bool) $items[0]->remind);
        $this->assertSame($order['installments'][0]['due_on'], $items[0]->starts_at->toDateString());
        $this->assertStringContainsString('14000', $items[0]->title);
        $this->assertStringContainsString('1 من 12', $items[0]->title);
    }

    public function test_cash_puts_nothing_on_the_agenda(): void
    {
        $this->place($this->cashId);

        $this->assertSame(0, AgendaItem::query()->where('user_id', $this->customer->id)->where('kind', AgendaItem::KIND_INSTALLMENT)->count());
    }

    public function test_collecting_a_payment_marks_it_and_finishes_its_agenda_item(): void
    {
        $order = $this->place($this->instalmentId);

        Sanctum::actingAs($this->shop);
        $rows = $this->postJson("/api/v2/business/orders/{$order['id']}/installments/1/collect")->assertOk()->json('data.installments');

        $this->assertNotNull($rows[0]['paid_at']);
        $this->assertNull($rows[1]['paid_at']);
        $this->assertSame(AgendaItem::STATUS_DONE, AgendaItem::query()->where('user_id', $this->customer->id)->where('kind', AgendaItem::KIND_INSTALLMENT)->orderBy('starts_at')->first()->status);

        $this->postJson("/api/v2/business/orders/{$order['id']}/installments/1/collect", ['paid' => false])->assertOk();
        $this->assertNull(OrderInstallment::query()->where('order_id', $order['id'])->where('seq', 1)->first()->paid_at);
    }

    public function test_only_the_orders_own_business_collects(): void
    {
        $order = $this->place($this->instalmentId);

        Sanctum::actingAs($this->customer);
        $this->postJson("/api/v2/business/orders/{$order['id']}/installments/1/collect")->assertForbidden();
    }

    public function test_a_cancelled_order_leaves_the_agenda_and_the_collection(): void
    {
        $order = $this->place($this->instalmentId);

        Order::query()->findOrFail($order['id'])->update(['status' => 'cancelled']);

        $this->assertSame(0, AgendaItem::query()->where('user_id', $this->customer->id)->where('kind', AgendaItem::KIND_INSTALLMENT)->where('status', AgendaItem::STATUS_ACTIVE)->count());

        Sanctum::actingAs($this->shop);
        $this->postJson("/api/v2/business/orders/{$order['id']}/installments/1/collect")->assertUnprocessable();
    }

    public function test_the_reports_page_gets_an_instalment_view_with_what_is_still_to_collect(): void
    {
        $this->place($this->instalmentId);
        $this->place($this->cashId);

        Sanctum::actingAs($this->shop);
        $first = OrderInstallment::query()->join('orders', 'orders.id', '=', 'order_installments.order_id')->where('orders.business_id', $this->shop->id)->orderByDesc('order_installments.order_id')->orderBy('seq')->first(['order_installments.order_id']);
        $this->postJson("/api/v2/business/orders/{$first->order_id}/installments/1/collect")->assertOk();

        $report = $this->getJson('/api/v2/business/orders/reports')->assertOk()->json();

        $this->assertTrue($report['has_installments']);
        $totals = $report['installments']['totals'];
        $this->assertGreaterThanOrEqual(36000.0, $totals['contract_total']);
        $this->assertGreaterThanOrEqual(14000.0, $totals['collected']);
        $this->assertEqualsWithDelta($totals['contract_total'], $totals['collected'] + $totals['remaining'], 0.01);

        $upcoming = collect($report['installments']['upcoming']);
        $this->assertNotEmpty($upcoming);
        $this->assertSame($upcoming->sortBy('due_on')->pluck('due_on')->values()->all(), $upcoming->pluck('due_on')->all(), 'by date');
        $this->assertSame(['order_id', 'seq', 'count', 'due_on', 'amount', 'customer', 'overdue'], array_keys($upcoming->first()));

        // The cash view never counts an instalment order.
        $cashOrders = Order::query()->where('business_id', $this->shop->id)->whereNull('booking_id')->where('status', '!=', 'cart')
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())->whereDoesntHave('installments')->count();
        $this->assertSame($cashOrders, $report['cash']['summary']['total_orders']);
        $this->assertSame($report['summary']['total_orders'] - $cashOrders, Order::query()->where('business_id', $this->shop->id)->whereNull('booking_id')->where('status', '!=', 'cart')->where('created_at', '>=', now()->subDays(29)->startOfDay())->whereHas('installments')->count());
    }

    public function test_a_business_that_sells_nothing_on_instalments_has_no_second_view(): void
    {
        $cashOnly = User::query()->where('type', 'business')
            ->whereNotIn('id', DB::table('menu_items')->join('menu_item_variants', 'menu_item_variants.menu_item_id', '=', 'menu_items.id')->where('menu_item_variants.installment_months', '>', 1)->pluck('menu_items.business_id'))
            ->whereNotIn('id', DB::table('orders')->join('order_installments', 'order_installments.order_id', '=', 'orders.id')->pluck('orders.business_id'))
            ->orderBy('id')->firstOrFail();

        Sanctum::actingAs($cashOnly);
        $report = $this->getJson('/api/v2/business/orders/reports')->assertOk()->json();

        $this->assertFalse($report['has_installments']);
        $this->assertNull($report['installments']);
        $this->assertNull($report['cash']);
        $this->assertArrayHasKey('summary', $report);
    }
}
