<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BusinessCatalogListing;
use App\Models\BusinessFinancialLedger;
use App\Models\CatalogProduct;
use App\Models\CategoryChildServiceFee;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Wallet;
use App\Services\FinancialLedgerService;
use App\Services\OrderFeeSettlementService;
use App\Services\OrderHandoverService;
use App\Services\WalletFeeService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * كشف الوارد والصادر والمكسب: كل نقاط الكتابة الأربع (تسليم طلب المنيو/التجزئة،
 * تسوية رسم الطلب، إتمام الحجز، رسم المنصة على الحجز) تضيف مباشرة على الصف
 * القائم — لا تجميع لاحق. راجع FinancialLedgerService لسبب هذا التصميم.
 */
class FinancialLedgerTest extends TestCase
{
    use DatabaseTransactions;

    private User $biz;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->biz = User::query()->where('type', 'business')->firstOrFail();
        $this->customer = User::query()->where('type', '!=', 'business')->where('id', '!=', $this->biz->id)->firstOrFail();
    }

    private function ledgerRow(int $businessId, string $source): ?BusinessFinancialLedger
    {
        return BusinessFinancialLedger::query()
            ->where('business_id', $businessId)
            ->where('source', $source)
            ->first();
    }

    private function fundWallet(int $userId): void
    {
        $wallet = app(WalletService::class)->getOrCreateWallet($userId);
        $wallet->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => 1000, 'locked_balance' => 0]);
    }

    // ---- profitTotal -------------------------------------------------------

    public function test_profit_total_subtracts_cost_and_fees_from_revenue(): void
    {
        $row = new BusinessFinancialLedger([
            'revenue_total' => 100,
            'cost_of_goods_total' => 40,
            'platform_fees_total' => 5,
        ]);

        $this->assertSame(55.0, $row->profitTotal());
    }

    // ---- menu order handover ------------------------------------------------

    private function pendingOrder(float $finalTotal, float $serviceFee = 0): Order
    {
        return Order::create([
            'user_id' => $this->customer->id,
            'business_id' => $this->biz->id,
            'fulfillment_type' => 'pickup',
            'status' => 'pending',
            'total' => $finalTotal, 'discount' => 0, 'delivery_fee' => 0,
            'service_fee' => $serviceFee, 'final_total' => $finalTotal,
            'payment_method' => 'cash', 'address' => '',
        ]);
    }

    private function handOver(Order $order): void
    {
        $service = app(OrderHandoverService::class);
        $token = $service->issueFor($order, (int) $this->biz->id);
        $service->confirm($token, (int) $this->customer->id);
    }

    public function test_handing_over_a_menu_order_records_revenue_and_cost_of_goods(): void
    {
        $menuItem = MenuItem::create([
            'business_id' => $this->biz->id,
            'name_ar' => 'صنف اختبار الكشف المالي',
            'base_price' => 50,
            'supply_price' => 30,
            'is_active' => 1,
            'sort_order' => 0,
            'is_featured' => 0,
        ]);

        $order = $this->pendingOrder(100);
        OrderItem::create([
            'order_id' => $order->id,
            'offering_type' => MenuItem::class,
            'offering_id' => $menuItem->id,
            'qty' => 2,
            'price' => 50,
            'total_price' => 100,
        ]);

        $this->handOver($order);

        $row = $this->ledgerRow((int) $this->biz->id, BusinessFinancialLedger::SOURCE_MENU);
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(100.0, (float) $row->revenue_total, 0.001);
        $this->assertEqualsWithDelta(60.0, (float) $row->cost_of_goods_total, 0.001, '2 x supply_price 30');
        $this->assertEqualsWithDelta(40.0, $row->profitTotal(), 0.001);

        $total = $this->ledgerRow((int) $this->biz->id, BusinessFinancialLedger::SOURCE_TOTAL);
        $this->assertEqualsWithDelta(100.0, (float) $total->revenue_total, 0.001);
    }

    public function test_ledger_accumulates_directly_onto_the_previous_row(): void
    {
        $itemA = MenuItem::create([
            'business_id' => $this->biz->id, 'name_ar' => 'صنف أ',
            'base_price' => 50, 'supply_price' => 30,
            'is_active' => 1, 'sort_order' => 0, 'is_featured' => 0,
        ]);
        $itemB = MenuItem::create([
            'business_id' => $this->biz->id, 'name_ar' => 'صنف ب',
            'base_price' => 20, 'supply_price' => 5,
            'is_active' => 1, 'sort_order' => 0, 'is_featured' => 0,
        ]);

        $orderA = $this->pendingOrder(50);
        OrderItem::create(['order_id' => $orderA->id, 'offering_type' => MenuItem::class, 'offering_id' => $itemA->id, 'qty' => 1, 'price' => 50, 'total_price' => 50]);
        $this->handOver($orderA);

        $afterFirst = $this->ledgerRow((int) $this->biz->id, BusinessFinancialLedger::SOURCE_MENU);
        $this->assertEqualsWithDelta(50.0, (float) $afterFirst->revenue_total, 0.001);

        $orderB = $this->pendingOrder(20);
        OrderItem::create(['order_id' => $orderB->id, 'offering_type' => MenuItem::class, 'offering_id' => $itemB->id, 'qty' => 1, 'price' => 20, 'total_price' => 20]);
        $this->handOver($orderB);

        $afterSecond = $this->ledgerRow((int) $this->biz->id, BusinessFinancialLedger::SOURCE_MENU);
        $this->assertEqualsWithDelta(70.0, (float) $afterSecond->revenue_total, 0.001, 'second sale adds onto the first, not overwrite');
        $this->assertEqualsWithDelta(35.0, (float) $afterSecond->cost_of_goods_total, 0.001);
        $this->assertSame(2, (int) $afterSecond->operations_count);
    }

    // ---- retail order handover ----------------------------------------------

    public function test_handing_over_a_retail_order_records_revenue_and_cost_from_the_listing(): void
    {
        $product = CatalogProduct::query()->first();
        if (! $product) {
            $this->markTestSkipped('Needs a catalog product.');
        }

        $listing = BusinessCatalogListing::create([
            'business_id' => $this->biz->id,
            'catalog_product_id' => $product->id,
            'price' => 80,
            'cost_price' => 50,
            'currency' => 'EGP',
            'stock' => 10,
            'is_active' => 1,
            'visibility' => 'public',
        ]);

        $order = $this->pendingOrder(80);
        OrderItem::create([
            'order_id' => $order->id,
            'offering_type' => BusinessCatalogListing::class,
            'offering_id' => $listing->id,
            'qty' => 1,
            'price' => 80,
            'total_price' => 80,
        ]);

        $this->handOver($order);

        $row = $this->ledgerRow((int) $this->biz->id, BusinessFinancialLedger::SOURCE_RETAIL);
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(80.0, (float) $row->revenue_total, 0.001);
        $this->assertEqualsWithDelta(50.0, (float) $row->cost_of_goods_total, 0.001);
        $this->assertEqualsWithDelta(30.0, $row->profitTotal(), 0.001);
    }

    // ---- order platform-fee settlement --------------------------------------

    public function test_order_fee_settlement_records_the_fee_as_a_business_cost(): void
    {
        $this->fundWallet((int) $this->biz->id);

        $order = $this->pendingOrder(100, 5);

        app(OrderFeeSettlementService::class)->settleForOrder($order->fresh());

        $total = $this->ledgerRow((int) $this->biz->id, BusinessFinancialLedger::SOURCE_TOTAL);
        $this->assertNotNull($total);
        $this->assertEqualsWithDelta(5.0, (float) $total->platform_fees_total, 0.001);
    }

    // ---- booking completion --------------------------------------------------

    public function test_completing_a_booking_records_its_revenue_with_zero_cost_of_goods(): void
    {
        $serviceId = (int) (Booking::query()->value('service_id') ?: 1);

        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'business_id' => $this->biz->id,
            'service_id' => $serviceId,
            'status' => Booking::STATUS_PENDING,
            'price' => 250,
            'quantity' => 1,
            'date' => now()->toDateString(),
            'time' => '12:00',
            'starts_at' => now()->addDay(),
            'meta' => ['source' => 'financial_ledger_test'],
        ]);

        $this->actingAs($this->biz, 'sanctum')
            ->postJson("/api/v2/bookings/{$booking->id}/complete")
            ->assertOk();

        $row = $this->ledgerRow((int) $this->biz->id, BusinessFinancialLedger::SOURCE_BOOKING);
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(250.0, (float) $row->revenue_total, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $row->cost_of_goods_total, 0.001);
    }

    // ---- booking platform fee: only a business-paid fee is the business's cost --

    public function test_booking_fee_is_recorded_only_when_the_business_is_the_payer(): void
    {
        $serviceId = (int) (Booking::query()->value('service_id') ?: 1);

        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'business_id' => $this->biz->id,
            'service_id' => $serviceId,
            'status' => Booking::STATUS_PENDING,
            'price' => 100,
            'quantity' => 1,
            'date' => now()->toDateString(),
            'time' => '12:00',
            'starts_at' => now()->addDay(),
            'meta' => ['source' => 'financial_ledger_test'],
        ]);

        $businessId = (int) $booking->business_id;
        $clientId = (int) $booking->user_id;

        $this->fundWallet($businessId);
        $this->fundWallet($clientId);

        foreach ([$businessId, $clientId] as $uid) {
            DB::table('user_service_fee_consents')->updateOrInsert(
                ['user_id' => $uid],
                ['fee_auto_charge_enabled' => 1, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        $feeCode = 'ledger_test_' . uniqid();
        $before = $this->ledgerRow($businessId, BusinessFinancialLedger::SOURCE_BOOKING);
        $beforeFees = $before ? (float) $before->platform_fees_total : 0.0;

        $m = new ReflectionMethod(WalletFeeService::class, 'createWalletFeeTransaction');
        $m->setAccessible(true);
        $fees = app(WalletFeeService::class);

        // The client pays this fee line — not a cost the business bears.
        $m->invoke($fees, $booking, $feeCode, CategoryChildServiceFee::PAYER_CLIENT, $clientId, 3.0, ['amount' => 3.0]);
        $afterClientPaid = $this->ledgerRow($businessId, BusinessFinancialLedger::SOURCE_BOOKING);
        $this->assertEqualsWithDelta(
            $beforeFees,
            $afterClientPaid ? (float) $afterClientPaid->platform_fees_total : 0.0,
            0.001,
            'a fee paid by the client must not appear as the business cost'
        );

        // The business pays this fee line — a real cost.
        $m->invoke($fees, $booking, $feeCode, CategoryChildServiceFee::PAYER_BUSINESS, $businessId, 7.0, ['amount' => 7.0]);
        $afterBusinessPaid = $this->ledgerRow($businessId, BusinessFinancialLedger::SOURCE_BOOKING);
        $this->assertEqualsWithDelta($beforeFees + 7.0, (float) $afterBusinessPaid->platform_fees_total, 0.001);
    }

    // ---- the business-panel screen --------------------------------------------

    public function test_financial_statement_screen_shows_the_accumulated_totals(): void
    {
        app(FinancialLedgerService::class)->recordSale((int) $this->biz->id, BusinessFinancialLedger::SOURCE_MENU, 100, 40);

        $this->actingAs($this->biz)
            ->get(route('business.financial-statement.index', [], false))
            ->assertOk()
            ->assertSee('100.00')
            ->assertSee('60.00');
    }
}
