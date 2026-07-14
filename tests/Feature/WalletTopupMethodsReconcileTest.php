<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTopup;
use App\Services\Payments\WalletTopupService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Fawry phases 3-4: app-selectable payment method threaded into the charge, and
 * the reconciliation settlement path (shared WalletTopupService + the poll
 * command's no-credentials guard). Rolls back.
 */
class WalletTopupMethodsReconcileTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.fawry.merchant_code' => 'TESTMC',
            'services.fawry.security_key' => 'test-key',
            'services.fawry.base_url' => 'https://atfawry.com',
            'services.payments.default_gateway' => 'fawry',
        ]);
        $this->user = User::query()->orderBy('id')->firstOrFail();
        app(WalletService::class)->getOrCreateWallet((int) $this->user->id)
            ->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => 0]);
    }

    public function test_fawry_cash_method_is_forced_into_the_charge(): void
    {
        $res = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v2/wallet/topup', ['amount' => 50, 'payment_method' => 'fawry_cash'])
            ->assertCreated();

        $this->assertSame('PayAtFawry', $res->json('payment.charge_request.paymentMethod'));

        $topup = WalletTopup::findOrFail($res->json('data.id'));
        $this->assertSame('fawry_cash', $topup->meta['requested_method']);
    }

    public function test_card_method_is_left_to_the_hosted_page(): void
    {
        $res = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v2/wallet/topup', ['amount' => 50, 'payment_method' => 'card'])
            ->assertCreated();

        $this->assertArrayNotHasKey('paymentMethod', $res->json('payment.charge_request'));
    }

    public function test_invalid_method_is_rejected(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v2/wallet/topup', ['amount' => 50, 'payment_method' => 'bitcoin'])
            ->assertStatus(422);
    }

    public function test_service_mark_paid_credits_once_and_is_idempotent(): void
    {
        $topup = WalletTopup::create([
            'user_id' => $this->user->id, 'gateway' => 'fawry', 'merchant_ref' => 'MR1',
            'amount' => 80, 'currency' => 'EGP', 'status' => WalletTopup::STATUS_PENDING,
        ]);

        $svc = app(WalletTopupService::class);
        $svc->markPaid($topup, 'FREF-1', 'CARD');
        $svc->markPaid($topup->fresh(), 'FREF-1', 'CARD'); // replay

        $this->assertSame(80.0, (float) Wallet::where('user_id', $this->user->id)->value('balance'));
        $this->assertSame(WalletTopup::STATUS_PAID, $topup->fresh()->status);
        $this->assertSame(1, (int) \App\Models\WalletTransaction::where('idempotency_key', 'wallet_topup:' . $topup->id)->count());
    }

    public function test_admin_oversight_view_renders(): void
    {
        $admin = User::query()->where('type', 'admin')->orderBy('id')->first();
        if (! $admin) {
            $this->markTestSkipped('Needs an admin user.');
        }

        WalletTopup::create([
            'user_id' => $this->user->id, 'gateway' => 'fawry', 'merchant_ref' => 'ADMINVIEW1',
            'amount' => 40, 'currency' => 'EGP', 'status' => WalletTopup::STATUS_PAID, 'paid_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/admin/wallet-topups')
            ->assertOk()
            ->assertSee('ADMINVIEW1');
    }

    public function test_reconcile_command_is_a_noop_without_gateway_credentials(): void
    {
        config(['services.fawry.security_key' => '', 'services.fawry.merchant_code' => '']);

        $topup = WalletTopup::create([
            'user_id' => $this->user->id, 'gateway' => 'fawry', 'merchant_ref' => 'MR2',
            'amount' => 30, 'currency' => 'EGP', 'status' => WalletTopup::STATUS_PENDING,
            'created_at' => now()->subHour(),
        ]);

        $this->artisan('wallet:reconcile-topups', ['--minutes' => 0])->assertSuccessful();

        // fetchStatus returns null (no creds) → nothing settled.
        $this->assertSame(WalletTopup::STATUS_PENDING, $topup->fresh()->status);
        $this->assertSame(0.0, (float) Wallet::where('user_id', $this->user->id)->value('balance'));
    }
}
