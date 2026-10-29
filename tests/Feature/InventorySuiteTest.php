<?php

namespace Tests\Feature;

use App\Models\BusinessStaff;
use App\Models\Inventory\Move;
use App\Models\Inventory\SuiteSubscription;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use App\Support\BusinessCapability;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsMenu;
use Tests\TestCase;

/**
 * «تطبيق الكمبيوتر» phase 1 (owner, 2026-10-29; canvas approved): the stock is a LEDGER with AVCO cost, purchases go through
 * the owner's approval limit and a three-way match, app orders take their recipes on ACCEPT and give them back on cancel,
 * the count is blind, the cashier prices on the server, and an unpaid month turns the suite read-only — never deleted.
 */
class InventorySuiteTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsMenu;

    private const RESTAURANT_ROOT = 16;
    private const RESTAURANT_CHILD = 245;
    private const PHARMACY_ROOT = 20;
    private const PHARMACY_CHILD = 215;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->business(self::RESTAURANT_ROOT, self::RESTAURANT_CHILD);
    }

    private function user(string $type, array $extra = []): User
    {
        return User::create([
            'name' => 'Zz Inv ' . uniqid(), 'email' => 'zz-inv-' . uniqid() . '@test.local', 'phone' => '01' . random_int(100000000, 999999999),
            'password' => Hash::make('Passw0rdTest'), 'type' => $type, 'api_token' => 'zz' . uniqid() . bin2hex(random_bytes(8)),
        ] + $extra);
    }

    private function business(int $root, int $child): User
    {
        return $this->user(User::TYPE_BUSINESS, ['category_id' => $root, 'category_child_id' => $child]);
    }

    private function wallet(User $u, float $balance): void
    {
        app(WalletService::class)->getOrCreateWallet($u->id)->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => $balance, 'locked_balance' => 0]);
    }

    private function price(float $amount): void
    {
        DB::table('platform_event_fees')->where('event_key', 'business_suite_month')->where('role', 'business')
            ->update(['enabled' => $amount > 0, 'calc_type' => 'fixed', 'amount' => $amount]);
    }

    private function enable(?User $b = null, array $features = []): void
    {
        $b ??= $this->owner;
        Sanctum::actingAs($b);
        $this->patchJson('/api/v2/business/inventory/settings', ['enabled' => true] + ($features ? ['features' => $features] : []))
            ->assertOk()->assertJsonPath('data.enabled', true);
    }

    private function item(array $data = []): array
    {
        return $this->postJson('/api/v2/business/inventory/items', $data + ['name_ar' => 'صنف ' . uniqid(), 'base_unit' => 'علبة'])
            ->assertCreated()->json('data.item');
    }

    private function staff(User $employer, array $caps): User
    {
        $person = $this->user(User::TYPE_CLIENT);
        BusinessStaff::create(['business_id' => $employer->id, 'user_id' => $person->id, 'capabilities' => $caps, 'is_active' => 1]);

        return $person;
    }

    private function onHand(int $itemId): float
    {
        return (float) DB::table('inv_levels')->where('item_id', $itemId)->sum('qty');
    }

    // ── switch, features, subscription ───────────────────────────────────────────────────────────────────────────────

    public function test_a_restaurant_switches_it_on_with_its_category_defaults(): void
    {
        Sanctum::actingAs($this->owner);
        $this->getJson('/api/v2/business/inventory/settings')->assertOk()
            ->assertJsonPath('data.available', true)->assertJsonPath('data.enabled', false);
        $this->getJson('/api/v2/business/inventory/items')->assertStatus(403)->assertJsonPath('code', 'inventory_disabled');

        $this->enable();
        $features = collect($this->getJson('/api/v2/business/inventory/settings')->json('data.features'))->keyBy('key');

        $this->assertTrue($features['recipes']['on']);
        $this->assertTrue($features['pos']['on']);
        // optional for a restaurant: allowed, but OFF until it switches them on
        $this->assertFalse($features['lots_expiry']['on']);
        $this->assertTrue($features['lots_expiry']['optional']);
        $this->assertSame('active', $this->getJson('/api/v2/business/inventory/settings')->json('data.subscription.status'));
        $this->assertSame(1, DB::table('inv_warehouses')->where('business_id', $this->owner->id)->count());
    }

    public function test_a_pharmacy_has_no_recipes_and_keeps_lots(): void
    {
        $pharmacy = $this->business(self::PHARMACY_ROOT, self::PHARMACY_CHILD);
        $this->enable($pharmacy);
        $features = collect($this->getJson('/api/v2/business/inventory/settings')->json('data.features'))->keyBy('key');

        $this->assertArrayNotHasKey('recipes', $features->all());
        $this->assertTrue($features['lots_expiry']['on']);
    }

    public function test_a_category_without_the_service_cannot_switch_it_on(): void
    {
        // a health trade that sells nothing — no menu, no shelf — has no inventory service
        $clinic = $this->business(self::PHARMACY_ROOT, 252);
        Sanctum::actingAs($clinic);

        $this->getJson('/api/v2/business/inventory/settings')->assertOk()->assertJsonPath('data.available', false);
        $this->patchJson('/api/v2/business/inventory/settings', ['enabled' => true])->assertStatus(422);
    }

    public function test_the_month_is_taken_from_the_wallet_and_refused_without_balance(): void
    {
        $this->price(300);
        $this->wallet($this->owner, 100);
        Sanctum::actingAs($this->owner);

        $this->patchJson('/api/v2/business/inventory/settings', ['enabled' => true])->assertStatus(422)->assertJsonValidationErrors('balance');

        $this->wallet($this->owner, 350);
        $this->enable();

        $this->assertEqualsWithDelta(50.0, (float) Wallet::query()->where('user_id', $this->owner->id)->value('balance'), 0.001);
        $this->assertSame(1, (int) SuiteSubscription::query()->where('business_id', $this->owner->id)->value('months_paid'));
    }

    public function test_an_unpaid_month_gives_grace_then_read_only_and_sales_still_count(): void
    {
        $this->price(300);
        $this->wallet($this->owner, 300);
        $this->enable();
        $item = $this->item(['opening_qty' => 50, 'opening_cost' => 2]);
        $this->wallet($this->owner, 0);

        $sub = SuiteSubscription::query()->where('business_id', $this->owner->id)->first();
        $renewal = $sub->paid_until->copy();

        $this->artisan('suite:renew-subscriptions')->assertSuccessful();
        app(\App\Services\Inventory\SuiteSubscriptionService::class)->runDaily($renewal->copy()->addHour());
        $this->assertSame('grace', $sub->fresh()->status);

        // grace: everything still works
        Sanctum::actingAs($this->owner);
        $this->postJson('/api/v2/business/inventory/moves', ['kind' => 'waste', 'item_id' => $item['id'], 'qty' => 1, 'reason' => 'كسر'])->assertCreated();

        app(\App\Services\Inventory\SuiteSubscriptionService::class)->runDaily($renewal->copy()->addDays(8));
        $this->assertSame('read_only', $sub->fresh()->status);

        $this->postJson('/api/v2/business/inventory/moves', ['kind' => 'waste', 'item_id' => $item['id'], 'qty' => 1])
            ->assertStatus(402)->assertJsonPath('code', 'suite_read_only');
        $this->getJson('/api/v2/business/inventory/items')->assertOk();
        $this->assertSame(1, DB::table('inv_items')->where('business_id', $this->owner->id)->count());

        // a top-up and a renewal lift it at once
        $this->wallet($this->owner, 300);
        $this->postJson('/api/v2/business/inventory/subscription/renew')->assertOk()->assertJsonPath('data.subscription.status', 'active');
    }

    // ── items, units, ledger, AVCO ───────────────────────────────────────────────────────────────────────────────────

    public function test_units_break_the_balance_down_and_the_ledger_is_idempotent(): void
    {
        $this->enable();
        $cola = $this->item([
            'name_ar' => 'في كولا ٣٣٠ مل', 'base_unit' => 'علبة',
            'units' => [['name' => 'كرتونة', 'factor' => 24], ['name' => 'بالتة', 'factor' => 1920]],
            'opening_qty' => 730, 'opening_cost' => 8,
        ]);

        $uuid = (string) Str::uuid();
        $this->postJson('/api/v2/business/inventory/moves', ['kind' => 'waste', 'uuid' => $uuid, 'item_id' => $cola['id'], 'qty' => 10])->assertCreated();
        $this->postJson('/api/v2/business/inventory/moves', ['kind' => 'waste', 'uuid' => $uuid, 'item_id' => $cola['id'], 'qty' => 10])->assertCreated();

        $this->assertSame(1, Move::query()->where('uuid', $uuid)->count());
        $this->assertEqualsWithDelta(720.0, $this->onHand($cola['id']), 0.0001);
        $this->getJson('/api/v2/business/inventory/items/' . $cola['id'])->assertOk()
            ->assertJsonPath('data.item.on_hand_display', '30 كرتونة');
    }

    public function test_purchases_flow_with_approval_partial_receipt_avco_and_three_way_match(): void
    {
        $this->enable();
        $this->patchJson('/api/v2/business/inventory/settings', ['po_approval_threshold' => 5000])->assertOk();

        $supplierBiz = $this->business(self::RESTAURANT_ROOT, self::RESTAURANT_CHILD);
        $butter = $this->item(['name_ar' => 'زبدة', 'base_unit' => 'كجم', 'unit_kind' => 'weight', 'units' => [['name' => 'عبوة ٢ كجم', 'factor' => 2]], 'opening_qty' => 2, 'opening_cost' => 290]);
        $oil = $this->item(['name_ar' => 'زيت قلي', 'base_unit' => 'لتر', 'unit_kind' => 'volume', 'units' => [['name' => 'جركن', 'factor' => 18]]]);
        $supplier = $this->postJson('/api/v2/business/inventory/suppliers', ['name' => 'شركة الألبان', 'linked_business_id' => $supplierBiz->id])->assertCreated()->json('data.supplier');

        // a buyer who may not approve: above the limit it waits for the owner
        $buyer = $this->staff($this->owner, [BusinessCapability::PURCHASES]);
        Sanctum::actingAs($buyer);
        $po = $this->postJson('/api/v2/business/inventory/purchase-orders', [
            'supplier_id' => $supplier['id'], 'submit' => true,
            'lines' => [
                ['item_id' => $butter['id'], 'unit_name' => 'عبوة ٢ كجم', 'unit_factor' => 2, 'qty' => 4, 'unit_price' => 605],
                ['item_id' => $oil['id'], 'unit_name' => 'جركن', 'unit_factor' => 18, 'qty' => 4, 'unit_price' => 1530],
            ],
        ])->assertCreated()->assertJsonPath('data.purchase_order.status', 'pending_approval')->json('data.purchase_order');
        $this->assertEquals(8540, $po['total']);
        $this->postJson("/api/v2/business/inventory/purchase-orders/{$po['id']}/approve")->assertStatus(403);
        $this->assertTrue(DB::table('app_notifications')->where('user_id', $this->owner->id)->where('source_type', 'inventory_purchase_order')->exists());

        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v2/business/inventory/purchase-orders/{$po['id']}/approve")->assertOk()->assertJsonPath('data.purchase_order.status', 'approved');

        // the supplier on BIM sees it and confirms
        Sanctum::actingAs($supplierBiz);
        $this->getJson('/api/v2/business/incoming-purchase-orders')->assertOk()->assertJsonPath('data.purchase_orders.0.id', $po['id']);
        $this->postJson("/api/v2/business/incoming-purchase-orders/{$po['id']}/confirm")->assertOk();

        // half the butter arrives
        Sanctum::actingAs($this->owner);
        $butterLine = collect($po['lines'])->firstWhere('item_id', $butter['id']);
        $this->postJson('/api/v2/business/inventory/receipts', [
            'purchase_order_id' => $po['id'],
            'lines' => [['po_line_id' => $butterLine['id'], 'qty' => 3, 'damaged_qty' => 1]],
        ])->assertCreated();

        $this->assertEqualsWithDelta(6.0, $this->onHand($butter['id']), 0.0001);
        // (2 × 290 + 4 × 302.5) ÷ 6
        $this->assertEqualsWithDelta(298.3333, (float) DB::table('inv_items')->where('id', $butter['id'])->value('avg_cost'), 0.001);
        $this->getJson("/api/v2/business/inventory/purchase-orders/{$po['id']}")->assertOk()
            ->assertJsonPath('data.purchase_order.status', 'partially_received');

        // the bill asks for all four packs: the match names it
        $match = $this->postJson('/api/v2/business/inventory/supplier-bills', [
            'supplier_id' => $supplier['id'], 'purchase_order_id' => $po['id'], 'number' => '7721',
            'lines' => [['po_line_id' => $butterLine['id'], 'qty' => 4]],
        ])->assertCreated()->json('data.match');
        $this->assertFalse($match['matched']);
        $this->assertContains('billed_more_than_received', collect($match['lines'])->firstWhere('po_line_id', $butterLine['id'])['issues']);

        $this->postJson('/api/v2/business/inventory/supplier-payments', ['supplier_id' => $supplier['id'], 'amount' => 2000, 'method' => 'cash'])
            ->assertCreated()->assertJsonPath('data.balance', 420);
    }

    // ── app orders take their recipe on accept, give it back on cancel ───────────────────────────────────────────────

    public function test_an_accepted_order_takes_its_recipe_and_a_cancelled_one_gives_it_back(): void
    {
        $this->enable();
        $bread = $this->item(['name_ar' => 'خبز برجر', 'base_unit' => 'رغيف', 'opening_qty' => 40, 'opening_cost' => 2.5]);
        $meat = $this->item(['name_ar' => 'لحم مفروم', 'base_unit' => 'جم', 'unit_kind' => 'weight', 'opening_qty' => 6500, 'opening_cost' => 0.38]);
        $cheese = $this->item(['name_ar' => 'شيدر', 'base_unit' => 'شريحة', 'opening_qty' => 72, 'opening_cost' => 3.96]);

        $burger = $this->seedMenuItem($this->owner->id, null, 260, 'وجبة برجر دبل');
        $large = $this->seedVariant($burger, 'كبير', null, 30);
        $extraCheese = $this->seedExtra($burger, 'إضافة جبنة', 10);

        $this->putJson('/api/v2/business/inventory/recipes', ['owner_type' => 'menu_item', 'owner_id' => $burger->id, 'lines' => [
            ['item_id' => $bread['id'], 'qty' => 1], ['item_id' => $meat['id'], 'qty' => 180, 'waste_pct' => 8], ['item_id' => $cheese['id'], 'qty' => 2],
        ]])->assertOk();
        $this->putJson('/api/v2/business/inventory/recipes', ['owner_type' => 'menu_variant', 'owner_id' => $large->id, 'lines' => [['item_id' => $meat['id'], 'qty' => 60]]])->assertOk();
        $this->putJson('/api/v2/business/inventory/recipes', ['owner_type' => 'menu_extra', 'owner_id' => $extraCheese->id, 'lines' => [['item_id' => $cheese['id'], 'qty' => 1]]])->assertOk();

        // the menu now shows what the stock can make: 40 loaves, 6500 ÷ 195.65 = 33 burgers, 72 ÷ 2 = 36
        $this->assertSame(33, (int) MenuItem::query()->whereKey($burger->id)->value('available_quantity'));

        $client = $this->user(User::TYPE_CLIENT);
        $order = Order::create(['user_id' => $client->id, 'business_id' => $this->owner->id, 'fulfillment_type' => Order::FULFILLMENT_PICKUP, 'discount' => 0, 'delivery_fee' => 0, 'service_fee' => 0, 'tax' => 0, 'payment_method' => 'cash', 'address' => 'x', 'status' => 'pending', 'total' => 600, 'final_total' => 600]);
        OrderItem::create(['order_id' => $order->id, 'offering_type' => MenuItem::class, 'offering_id' => $burger->id, 'qty' => 2, 'price' => 300, 'total_price' => 600,
            'size_id' => $large->id, 'addons' => [['id' => $extraCheese->id, 'name' => 'إضافة جبنة', 'price' => 10, 'qty' => 1]]]);

        $order->update(['prep_status' => Order::PREP_ACCEPTED]);
        $order->update(['prep_status' => Order::PREP_ACCEPTED, 'notes' => 'again']);

        $this->assertEqualsWithDelta(38.0, $this->onHand($bread['id']), 0.0001);
        // (180 ÷ 0.92 + 60) × 2
        $this->assertEqualsWithDelta(6500 - (180 / 0.92 + 60) * 2, $this->onHand($meat['id']), 0.01);
        $this->assertEqualsWithDelta(72 - (2 + 1) * 2, $this->onHand($cheese['id']), 0.0001);
        $this->assertSame(3, Move::query()->where('reference_type', 'order')->where('reference_id', $order->id)->count());

        $order->update(['status' => 'cancelled']);
        $this->assertEqualsWithDelta(40.0, $this->onHand($bread['id']), 0.0001);
        $this->assertEqualsWithDelta(6500.0, $this->onHand($meat['id']), 0.01);
    }

    public function test_a_business_without_the_suite_is_untouched_by_orders(): void
    {
        $plain = $this->business(self::RESTAURANT_ROOT, self::RESTAURANT_CHILD);
        $burger = $this->seedMenuItem($plain->id);
        $order = Order::create(['user_id' => $this->user(User::TYPE_CLIENT)->id, 'business_id' => $plain->id, 'fulfillment_type' => Order::FULFILLMENT_PICKUP, 'discount' => 0, 'delivery_fee' => 0, 'service_fee' => 0, 'tax' => 0, 'payment_method' => 'cash', 'address' => 'x', 'status' => 'pending', 'total' => 50, 'final_total' => 50]);
        OrderItem::create(['order_id' => $order->id, 'offering_type' => MenuItem::class, 'offering_id' => $burger->id, 'qty' => 1, 'price' => 50, 'total_price' => 50]);

        $order->update(['prep_status' => Order::PREP_ACCEPTED]);

        $this->assertSame(0, Move::query()->where('business_id', $plain->id)->count());
    }

    // ── stocktake ────────────────────────────────────────────────────────────────────────────────────────────────────

    public function test_a_blind_count_hides_the_figures_and_books_only_the_difference(): void
    {
        $this->enable();
        $bread = $this->item(['name_ar' => 'خبز', 'base_unit' => 'رغيف', 'opening_qty' => 52, 'opening_cost' => 2.5]);

        $count = $this->postJson('/api/v2/business/inventory/stocktakes', ['item_ids' => [$bread['id']]])->assertCreated()->json('data.stocktake');
        $this->assertNull($count['lines'][0]['expected_qty']);

        // sold during the count: a move of its own, not part of the difference
        $this->postJson('/api/v2/business/inventory/moves', ['kind' => 'waste', 'item_id' => $bread['id'], 'qty' => 2])->assertCreated();

        $this->postJson("/api/v2/business/inventory/stocktakes/{$count['id']}/count", ['lines' => [['item_id' => $bread['id'], 'counted_qty' => 40, 'reason' => 'تالف']]])->assertOk();
        $review = $this->postJson("/api/v2/business/inventory/stocktakes/{$count['id']}/finish")->assertOk()->json('data.stocktake');
        $this->assertEquals(52, $review['lines'][0]['expected_qty']);
        $this->assertEquals(-12, $review['lines'][0]['difference']);
        $this->assertEquals(-30, $review['net_difference_value']);

        $this->postJson("/api/v2/business/inventory/stocktakes/{$count['id']}/apply")->assertOk()->assertJsonPath('data.stocktake.status', 'applied');
        $this->assertEqualsWithDelta(38.0, $this->onHand($bread['id']), 0.0001);
    }

    // ── cashier ──────────────────────────────────────────────────────────────────────────────────────────────────────

    public function test_the_cashier_prices_on_the_server_takes_the_recipe_and_closes_the_drawer(): void
    {
        $this->enable();
        $cola = $this->item(['name_ar' => 'كولا', 'base_unit' => 'علبة', 'barcode' => '6221234500017', 'opening_qty' => 100, 'opening_cost' => 8.69]);
        $drink = $this->seedMenuItem($this->owner->id, null, 25, 'في كولا علبة');
        $this->putJson('/api/v2/business/inventory/recipes', ['owner_type' => 'menu_item', 'owner_id' => $drink->id, 'lines' => [['item_id' => $cola['id'], 'qty' => 1]]])->assertOk();

        $cashier = $this->staff($this->owner, [BusinessCapability::POS]);
        Sanctum::actingAs($cashier);
        $catalog = collect($this->getJson('/api/v2/business/inventory/pos/catalog')->assertOk()->json('data.products'))->firstWhere('owner_id', $drink->id);
        $this->assertSame(['6221234500017'], $catalog['barcodes']);
        $this->getJson('/api/v2/business/inventory/suppliers')->assertStatus(403);

        $shift = $this->postJson('/api/v2/business/inventory/pos/shifts', ['opening_cash' => 200])->assertCreated()->json('data.shift');
        $line = ['owner_type' => 'menu_item', 'owner_id' => $drink->id, 'qty' => 3];

        $this->postJson('/api/v2/business/inventory/pos/sales', ['shift_id' => $shift['id'], 'paid' => 50, 'lines' => [$line]])->assertStatus(422);
        $sale = $this->postJson('/api/v2/business/inventory/pos/sales', ['shift_id' => $shift['id'], 'paid' => 100, 'lines' => [$line + ['unit_price' => 1]]])
            ->assertCreated()->assertJsonPath('data.change', 25)->json('data.sale');
        $this->assertEquals(75, $sale['total']);
        $this->assertEqualsWithDelta(97.0, $this->onHand($cola['id']), 0.0001);

        $this->postJson("/api/v2/business/inventory/pos/shifts/{$shift['id']}/close", ['counted_cash' => 270])->assertOk()
            ->assertJsonPath('data.expected_cash', 275)->assertJsonPath('data.difference', -5);

        $this->postJson("/api/v2/business/inventory/pos/sales/{$sale['id']}/return")->assertOk()->assertJsonPath('data.sale.status', 'returned');
        $this->assertEqualsWithDelta(100.0, $this->onHand($cola['id']), 0.0001);
    }

    // ── «يكفي مبيعات اليوم؟» ──────────────────────────────────────────────────────────────────────────────────────────

    public function test_coverage_reads_the_same_weekday_and_says_no_when_short(): void
    {
        $this->enable();
        $bread = $this->item(['name_ar' => 'خبز', 'base_unit' => 'رغيف', 'opening_qty' => 40, 'opening_cost' => 2.5, 'reorder_level' => 120]);
        $ledger = app(\App\Services\Inventory\StockLedger::class);

        for ($w = 1; $w <= 4; $w++) {
            $ledger->post($this->owner->id, ['item_id' => $bread['id'], 'qty' => 120, 'unit_cost' => 2.5, 'type' => Move::RECEIPT, 'occurred_at' => now()->subWeeks($w)->setTime(8, 0)]);
            $ledger->post($this->owner->id, ['item_id' => $bread['id'], 'qty' => -120, 'type' => Move::SALE, 'occurred_at' => now()->subWeeks($w)->setTime(13, 0)]);
        }

        $row = collect($this->getJson('/api/v2/business/inventory/coverage')->assertOk()->json('data.items'))->firstWhere('item_id', $bread['id']);

        $this->assertEquals(120, $row['weekday_average']);
        $this->assertSame('no', $row['verdict']);
        $this->assertTrue($row['below_reorder']);
    }

    // ── who may ──────────────────────────────────────────────────────────────────────────────────────────────────────

    public function test_staff_capabilities_and_strangers(): void
    {
        $this->enable();
        $item = $this->item();

        $counter = $this->staff($this->owner, [BusinessCapability::INVENTORY]);
        Sanctum::actingAs($counter);
        $this->getJson('/api/v2/business/inventory/items')->assertOk();
        $this->getJson('/api/v2/business/inventory/suppliers')->assertStatus(403);
        $this->patchJson('/api/v2/business/inventory/settings', ['enabled' => false])->assertStatus(403);
        $this->postJson('/api/v2/business/inventory/devices', ['uuid' => (string) Str::uuid(), 'name' => 'x', 'role' => 'warehouse'])->assertStatus(403);

        $stranger = $this->business(self::RESTAURANT_ROOT, self::RESTAURANT_CHILD);
        $this->enable($stranger);
        $this->getJson('/api/v2/business/inventory/items/' . $item['id'])->assertNotFound();
    }

    public function test_devices_one_main_computer_and_the_sync_feed(): void
    {
        $this->enable();
        $main = (string) Str::uuid();
        $store = (string) Str::uuid();

        $this->postJson('/api/v2/business/inventory/devices', ['uuid' => $main, 'name' => 'جهاز المدير', 'role' => 'main', 'is_hub' => true])->assertCreated();
        $this->postJson('/api/v2/business/inventory/devices', ['uuid' => $store, 'name' => 'جهاز المخزن', 'role' => 'warehouse', 'screens' => ['receive', 'stocktake'], 'is_hub' => true])->assertCreated();
        $this->assertSame(1, DB::table('inv_devices')->where('business_id', $this->owner->id)->where('is_hub', 1)->count());

        $item = $this->item(['opening_qty' => 5, 'opening_cost' => 1]);
        $feed = $this->getJson('/api/v2/business/inventory/changes?after_move=0')->assertOk()->json('data');
        $this->assertSame($item['id'], $feed['moves'][0]['item_id']);
        $this->assertGreaterThan(0, $feed['last_move_id']);
        $this->assertEmpty($this->getJson('/api/v2/business/inventory/changes?after_move=' . $feed['last_move_id'])->json('data.moves'));

        $id = DB::table('inv_devices')->where('uuid', $store)->value('id');
        $this->deleteJson("/api/v2/business/inventory/devices/{$id}")->assertOk();
        $this->postJson("/api/v2/business/inventory/devices/{$store}/seen")->assertStatus(403)->assertJsonPath('code', 'device_revoked');
    }

    public function test_customers_never_see_the_inventory_service(): void
    {
        $keys = collect($this->getJson('/api/v2/discovery/services?child_id=' . self::RESTAURANT_CHILD)->assertOk()->json('data'))->flatten()->all();

        $this->assertNotContains('inventory', $keys);
    }
}
