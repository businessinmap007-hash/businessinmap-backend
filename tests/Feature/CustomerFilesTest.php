<?php

namespace Tests\Feature;

use App\Models\BusinessStaff;
use App\Models\Crm\AccountMove;
use App\Models\Crm\Customer;
use App\Models\Inventory\SuiteSubscription;
use App\Models\Order;
use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use App\Support\BusinessCapability;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsMenu;
use Tests\TestCase;

/**
 * «ملفات العملاء ودفتر الآجل» (desktop suite phase 3, owner approved 2026-10-30): a file per customer opened by his first
 * order or typed in; the cashier sells on account within a limit; a payment counts only once both sides confirm it,
 * and a transfer's reference — typed blind by each side — must match; the customer reads his own book only.
 */
class CustomerFilesTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsMenu;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->user(User::TYPE_BUSINESS, ['category_id' => 16, 'category_child_id' => 245]);
        Sanctum::actingAs($this->owner);
        $this->patchJson('/api/v2/business/inventory/settings', ['enabled' => true])->assertOk();
    }

    private function user(string $type, array $extra = []): User
    {
        return User::create([
            'name' => 'Zz Crm ' . uniqid(), 'email' => 'zz-crm-' . uniqid() . '@test.local', 'phone' => '01' . random_int(100000000, 999999999),
            'password' => Hash::make('Passw0rdTest'), 'type' => $type, 'api_token' => 'zz' . uniqid() . bin2hex(random_bytes(8)),
        ] + $extra);
    }

    private function staff(array $caps): User
    {
        $person = $this->user(User::TYPE_CLIENT);
        BusinessStaff::create(['business_id' => $this->owner->id, 'user_id' => $person->id, 'capabilities' => $caps, 'is_active' => 1]);

        return $person;
    }

    private function order(User $client, float $total): Order
    {
        return Order::create(['user_id' => $client->id, 'business_id' => $this->owner->id, 'fulfillment_type' => Order::FULFILLMENT_PICKUP, 'discount' => 0, 'delivery_fee' => 0, 'service_fee' => 0, 'tax' => 0, 'payment_method' => 'cash', 'address' => 'x', 'status' => 'pending', 'total' => $total, 'final_total' => $total]);
    }

    private function offline(array $data = []): array
    {
        Sanctum::actingAs($this->owner);

        return $this->postJson('/api/v2/business/customers', $data + ['name' => 'أم يوسف', 'phone' => '0100 123 4471'])->assertCreated()->json('data.customer');
    }

    private function appCustomer(User $client): Customer
    {
        $this->order($client, 100);

        return Customer::query()->where('business_id', $this->owner->id)->where('user_id', $client->id)->firstOrFail();
    }

    private function balance(int $customerId): float
    {
        return (float) Customer::query()->whereKey($customerId)->value('balance');
    }

    public function test_an_app_customer_file_opens_with_his_first_order_and_counts_his_purchases(): void
    {
        $client = $this->user(User::TYPE_CLIENT);
        $this->order($client, 250);
        $this->order($client, 150);
        $this->order($client, 999)->update(['status' => 'cancelled']);

        $this->assertSame(1, Customer::query()->where('business_id', $this->owner->id)->where('user_id', $client->id)->count());

        Sanctum::actingAs($this->owner);
        $row = collect($this->getJson('/api/v2/business/customers')->assertOk()->json('data.customers'))->firstWhere('on_app', true);
        $this->assertEquals(400, $row['spend']);
        $this->assertSame(2, $row['visits']);

        // another business sees nothing of him
        $other = $this->user(User::TYPE_BUSINESS, ['category_id' => 16, 'category_child_id' => 245]);
        Sanctum::actingAs($other);
        $this->patchJson('/api/v2/business/inventory/settings', ['enabled' => true])->assertOk();
        $this->assertSame([], $this->getJson('/api/v2/business/customers')->json('data.customers'));
        $this->getJson("/api/v2/business/customers/{$row['id']}")->assertNotFound();
    }

    public function test_the_cashier_sells_on_account_within_the_limit_and_a_return_takes_it_off(): void
    {
        $drink = $this->seedMenuItem($this->owner->id, null, 100, 'بن محوّج');
        $c = $this->offline(['credit_limit' => 2000, 'opening_balance' => 1750]);
        $this->assertEquals(1750, $this->balance($c['id']));

        $cashier = $this->staff([BusinessCapability::POS]);
        Sanctum::actingAs($cashier);
        $shift = $this->postJson('/api/v2/business/inventory/pos/shifts', ['opening_cash' => 0])->assertCreated()->json('data.shift');
        $sale = ['shift_id' => $shift['id'], 'customer_id' => $c['id'], 'lines' => [['owner_type' => 'menu_item', 'owner_id' => $drink->id, 'qty' => 4]]];

        // 1,750 + 400 passes the 2,000 limit by 150
        $this->postJson('/api/v2/business/inventory/pos/sales', $sale + ['on_account' => 400, 'paid' => 0])->assertStatus(422)->assertJsonValidationErrors('credit_limit');
        // a cashier without the right cannot wave it through
        $this->postJson('/api/v2/business/inventory/pos/sales', $sale + ['on_account' => 400, 'paid' => 0, 'credit_override' => true])->assertStatus(422)->assertJsonValidationErrors('credit_override');

        // 150 cash + 250 on account fits
        $sold = $this->postJson('/api/v2/business/inventory/pos/sales', $sale + ['on_account' => 250, 'paid' => 150])->assertCreated()->json('data.sale');
        $this->assertEquals(2000, $this->balance($c['id']));
        // only the cash part is in the drawer
        $this->getJson("/api/v2/business/inventory/pos/shifts/{$shift['id']}")->assertOk()->assertJsonPath('data.expected_cash', 150);

        // the owner may pass the limit
        Sanctum::actingAs($this->owner);
        $this->postJson('/api/v2/business/inventory/pos/sales', $sale + ['on_account' => 100, 'paid' => 300, 'credit_override' => true])->assertCreated();
        $this->assertEquals(2100, $this->balance($c['id']));

        $this->postJson("/api/v2/business/inventory/pos/sales/{$sold['id']}/return")->assertOk();
        $this->postJson("/api/v2/business/inventory/pos/sales/{$sold['id']}/return")->assertOk();
        $this->assertEquals(1850, $this->balance($c['id']));
        $this->assertSame(1, AccountMove::query()->where('customer_id', $c['id'])->where('kind', AccountMove::RETURN)->count());
    }

    public function test_an_off_app_customer_payment_stands_on_the_business_word(): void
    {
        $c = $this->offline(['opening_balance' => 700]);
        $p = $this->postJson("/api/v2/business/customers/{$c['id']}/payments", ['amount' => 500, 'method' => 'cash'])->assertCreated()->json('data.payment');

        $this->assertSame('confirmed', $p['status']);
        $this->assertTrue($p['business_only']);
        $this->assertSame(1, $p['number']);
        $this->assertEquals(200, $this->balance($c['id']));
    }

    public function test_a_cash_payment_counts_only_when_the_customer_confirms_it(): void
    {
        $client = $this->user(User::TYPE_CLIENT);
        $c = $this->appCustomer($client);
        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v2/business/customers/{$c->id}/adjust", ['amount' => 1000, 'reason' => 'رصيد قديم'])->assertOk();

        $p = $this->postJson("/api/v2/business/customers/{$c->id}/payments", ['amount' => 600, 'method' => 'cash'])->assertCreated()->json('data.payment');
        $this->assertSame('pending', $p['status']);
        $this->assertEquals(1000, $this->balance($c->id));
        $this->assertTrue(DB::table('app_notifications')->where('user_id', $client->id)->where('source_type', 'crm_payment')->exists());

        // the one who recorded it cannot confirm it
        $this->postJson("/api/v2/business/customer-payments/{$p['id']}/confirm")->assertStatus(422);

        Sanctum::actingAs($client);
        $mine = $this->getJson("/api/v2/customer-accounts/{$c->id}")->assertOk()->json('data');
        $this->assertTrue($mine['payments'][0]['awaiting_me']);
        $this->postJson("/api/v2/customer-accounts/payments/{$p['id']}/confirm")->assertOk()->assertJsonPath('data.payment.status', 'confirmed');
        $this->assertEquals(400, $this->balance($c->id));

        // a second one the customer says he never paid
        Sanctum::actingAs($this->owner);
        $q = $this->postJson("/api/v2/business/customers/{$c->id}/payments", ['amount' => 100, 'method' => 'cash'])->json('data.payment');
        Sanctum::actingAs($client);
        $this->postJson("/api/v2/customer-accounts/payments/{$q['id']}/reject", ['reason' => 'لم أدفع'])->assertOk()->assertJsonPath('data.payment.status', 'rejected');
        $this->assertEquals(400, $this->balance($c->id));
    }

    public function test_a_transfer_matches_blind_references_and_a_reference_counts_once(): void
    {
        $client = $this->user(User::TYPE_CLIENT);
        $c = $this->appCustomer($client);
        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v2/business/customers/{$c->id}/adjust", ['amount' => 3000, 'reason' => 'رصيد قديم'])->assertOk();

        Sanctum::actingAs($client);
        $this->postJson("/api/v2/customer-accounts/{$c->id}/payments", ['amount' => 1500, 'method' => 'instapay'])->assertStatus(422)->assertJsonValidationErrors('reference');
        $p = $this->postJson("/api/v2/customer-accounts/{$c->id}/payments", ['amount' => 1500, 'method' => 'instapay', 'reference' => 'IP 8841 2207 63'])->assertCreated()->json('data.payment');
        $this->assertSame('IP8841220763', $p['reference']);

        Sanctum::actingAs($this->owner);
        $seen = collect($this->getJson('/api/v2/business/customer-payments?status=pending')->assertOk()->json('data.payments'))->firstWhere('id', $p['id']);
        // the business never sees what the customer typed
        $this->assertNull($seen['reference']);
        $this->assertTrue($seen['awaiting_me']);

        $this->postJson("/api/v2/business/customer-payments/{$p['id']}/confirm", ['reference' => 'IP884122076'])->assertStatus(422)->assertJsonValidationErrors('reference');
        $this->assertEquals(3000, $this->balance($c->id));
        // Arabic digits, dashes and case do not matter
        $this->postJson("/api/v2/business/customer-payments/{$p['id']}/confirm", ['reference' => 'ip-٨٨٤١٢٢-٠٧٦٣'])->assertOk()
            ->assertJsonPath('data.payment.status', 'confirmed')->assertJsonPath('data.payment.reference', 'IP8841220763');
        $this->assertEquals(1500, $this->balance($c->id));

        // the same reference cannot pay twice
        $this->postJson("/api/v2/business/customers/{$c->id}/payments", ['amount' => 100, 'method' => 'bank', 'reference' => 'ip8841220763'])->assertStatus(422);

        // three wrong tries close it
        $q = $this->postJson("/api/v2/business/customers/{$c->id}/payments", ['amount' => 200, 'method' => 'wallet', 'reference' => 'VF-1'])->json('data.payment');
        Sanctum::actingAs($client);
        $this->postJson("/api/v2/customer-accounts/payments/{$q['id']}/confirm", ['reference' => 'VF-2'])->assertStatus(422);
        $this->postJson("/api/v2/customer-accounts/payments/{$q['id']}/confirm", ['reference' => 'VF-3'])->assertStatus(422);
        $this->postJson("/api/v2/customer-accounts/payments/{$q['id']}/confirm", ['reference' => 'VF-4'])->assertStatus(422);
        $this->assertSame('mismatch', DB::table('crm_payments')->where('id', $q['id'])->value('status'));
        $this->postJson("/api/v2/customer-accounts/payments/{$q['id']}/confirm", ['reference' => 'VF-1'])->assertStatus(422);
        $this->assertEquals(1500, $this->balance($c->id));
    }

    public function test_the_account_code_links_an_off_app_file_and_folds_it_into_his_app_file(): void
    {
        $client = $this->user(User::TYPE_CLIENT);
        $app = $this->appCustomer($client);
        $off = $this->offline(['opening_balance' => 300, 'notes' => 'يحب البن المحوّج']);

        Sanctum::actingAs($client);
        $code = $this->postJson('/api/v2/customer-accounts/link-code')->assertOk()->json('data.code');

        Sanctum::actingAs($this->owner);
        $this->postJson('/api/v2/business/customers/resolve-code', ['code' => 'WRONG123'])->assertStatus(422);
        $this->postJson('/api/v2/business/customers/resolve-code', ['code' => strtolower($code)])->assertOk()->assertJsonPath('data.customer.id', $app->id);

        $this->postJson("/api/v2/business/customers/{$off['id']}/link", ['code' => $code])->assertOk()->assertJsonPath('data.customer.id', $app->id);
        $this->assertNull(Customer::query()->find($off['id']));
        $this->assertEquals(300, $this->balance($app->id));
        $this->assertSame('يحب البن المحوّج', Customer::query()->find($app->id)->notes);
    }

    public function test_an_order_on_account_ages_and_a_cancel_takes_it_off(): void
    {
        $client = $this->user(User::TYPE_CLIENT);
        $c = $this->appCustomer($client);
        $order = $this->order($client, 410);

        Sanctum::actingAs($this->owner);
        $this->patchJson("/api/v2/business/customers/{$c->id}", ['payment_terms_days' => 30])->assertOk();
        $this->postJson("/api/v2/business/customers/{$c->id}/charge-order", ['order_id' => $order->id])->assertOk();
        $this->postJson("/api/v2/business/customers/{$c->id}/charge-order", ['order_id' => $order->id])->assertStatus(422);
        $this->assertEquals(410, $this->balance($c->id));

        // an old debt and a payment that settles the oldest first
        app(\App\Services\Crm\CreditLedger::class)->post($c->fresh(), AccountMove::OPENING, 500, ['occurred_at' => Carbon::now()->subDays(75)]);
        $this->postJson("/api/v2/business/customers/{$c->id}/adjust", ['amount' => -200, 'reason' => 'خصم متفق عليه'])->assertOk();
        $aging = $this->getJson("/api/v2/business/customers/{$c->id}/statement")->assertOk()->json('data.statement.aging');
        $this->assertEquals(300, $aging['61_90']);
        $this->assertEquals(410, $aging['0_30']);
        $this->assertEquals(300, $aging['overdue']);

        $order->update(['status' => 'cancelled']);
        $this->assertEquals(300, $this->balance($c->id));
    }

    public function test_the_reminder_reaches_an_app_customer_once_a_day_and_he_sees_only_his_book(): void
    {
        $client = $this->user(User::TYPE_CLIENT);
        $c = $this->appCustomer($client);
        Sanctum::actingAs($this->owner);
        $this->patchJson("/api/v2/business/customers/{$c->id}", ['notes' => 'سري', 'credit_limit' => 5000])->assertOk();
        $this->postJson("/api/v2/business/customers/{$c->id}/adjust", ['amount' => 250, 'reason' => 'رصيد'])->assertOk();

        $this->postJson("/api/v2/business/customers/{$c->id}/remind")->assertOk()->assertJsonPath('data.sent', true);
        $this->postJson("/api/v2/business/customers/{$c->id}/remind")->assertOk()->assertJsonPath('data.sent', false);
        $this->assertSame(1, DB::table('app_notifications')->where('user_id', $client->id)->where('source_type', 'crm_customer')->count());

        Sanctum::actingAs($client);
        $accounts = $this->getJson('/api/v2/customer-accounts')->assertOk()->json('data.accounts');
        $this->assertCount(1, $accounts);
        $this->assertEquals(250, $accounts[0]['balance']);
        $book = $this->getJson("/api/v2/customer-accounts/{$c->id}")->assertOk()->json('data');
        $this->assertArrayNotHasKey('notes', $book['account']);

        $stranger = $this->user(User::TYPE_CLIENT);
        Sanctum::actingAs($stranger);
        $this->getJson("/api/v2/customer-accounts/{$c->id}")->assertNotFound();
    }

    public function test_capabilities_forgetting_and_a_lapsed_subscription(): void
    {
        $c = $this->offline(['opening_balance' => 100]);

        // a cashier finds and adds customers but does not open the file
        Sanctum::actingAs($this->staff([BusinessCapability::POS]));
        $this->getJson('/api/v2/business/customers')->assertOk();
        $this->getJson("/api/v2/business/customers/{$c['id']}")->assertForbidden();

        // a customers clerk cannot move limits
        Sanctum::actingAs($this->staff([BusinessCapability::CUSTOMERS]));
        $this->patchJson("/api/v2/business/customers/{$c['id']}", ['credit_limit' => 99999])->assertStatus(422);

        // read-only: no new files, but payments still count
        Sanctum::actingAs($this->owner);
        SuiteSubscription::query()->where('business_id', $this->owner->id)->update(['status' => 'read_only']);
        $this->postJson('/api/v2/business/customers', ['name' => 'جديد'])->assertStatus(402);
        $this->postJson("/api/v2/business/customers/{$c['id']}/payments", ['amount' => 100, 'method' => 'cash'])->assertCreated();
        $this->assertEquals(0, $this->balance($c['id']));

        $this->deleteJson("/api/v2/business/customers/{$c['id']}")->assertOk();
        $gone = Customer::query()->find($c['id']);
        $this->assertSame('عميل محذوف', $gone->name);
        $this->assertNull($gone->phone);
        $this->assertSame(2, AccountMove::query()->where('customer_id', $c['id'])->count());
    }
}
