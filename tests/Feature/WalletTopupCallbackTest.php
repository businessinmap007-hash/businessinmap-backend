<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTopup;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Wallet top-up (Fawry money-in): the authed start endpoint and the PUBLIC
 * server-to-server callback that actually credits the points wallet. Covers the
 * security guards — signature, idempotency, amount tamper. Rolls back.
 */
class WalletTopupCallbackTest extends TestCase
{
    use DatabaseTransactions;

    private const SEC = 'test-security-key-abc';

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.fawry.merchant_code' => 'TESTMC',
            'services.fawry.security_key' => self::SEC,
            'services.fawry.return_url' => 'https://example.com/return',
            'services.fawry.base_url' => 'https://atfawry.com',
            'services.payments.default_gateway' => 'fawry',
        ]);

        $this->customer = User::query()->orderBy('id')->firstOrFail();
        app(WalletService::class)->getOrCreateWallet((int) $this->customer->id)
            ->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => 0]);
    }

    /** Build a Fawry callback payload with a valid (or, if $amount differs, still valid) signature. */
    private function callbackPayload(string $merchantRef, string $status, float $paymentAmount): array
    {
        $fawryRef = 'FWREF-' . $merchantRef;
        $amt = number_format($paymentAmount, 2, '.', '');
        $p = [
            'merchantRefNumber' => $merchantRef,
            'fawryRefNumber' => $fawryRef,
            'paymentAmount' => $amt,
            'orderAmount' => $amt,
            'orderStatus' => $status,
            'paymentMethod' => 'CARD',
            'paymentReferenceNumber' => '',
        ];
        $p['messageSignature'] = hash('sha256', $fawryRef . $merchantRef . $amt . $amt . $status . 'CARD' . '' . self::SEC);

        return $p;
    }

    private function startTopup(float $amount): WalletTopup
    {
        $res = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v2/wallet/topup', ['amount' => $amount])
            ->assertCreated()
            ->assertJsonPath('data.status', WalletTopup::STATUS_PENDING);

        return WalletTopup::findOrFail($res->json('data.id'));
    }

    public function test_start_returns_signed_charge_payload(): void
    {
        $res = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v2/wallet/topup', ['amount' => 75])
            ->assertCreated();

        $charge = $res->json('payment.charge_request');
        $this->assertNotEmpty($charge['signature']);
        $this->assertSame('https://atfawry.com/fawrypay-api/api/payments/init', $res->json('payment.init_url'));
    }

    public function test_paid_callback_credits_wallet_and_is_idempotent(): void
    {
        $topup = $this->startTopup(50);
        $payload = $this->callbackPayload((string) $topup->id, 'PAID', 50);

        $this->postJson('/api/v2/wallet/topup/callback', $payload)->assertOk();

        $this->assertSame(50.0, (float) Wallet::where('user_id', $this->customer->id)->value('balance'));
        $this->assertSame(WalletTopup::STATUS_PAID, $topup->fresh()->status);
        $this->assertDatabaseHas('wallet_transactions', ['idempotency_key' => 'wallet_topup:' . $topup->id]);

        // Replay → no double credit.
        $this->postJson('/api/v2/wallet/topup/callback', $payload)->assertOk();
        $this->assertSame(50.0, (float) Wallet::where('user_id', $this->customer->id)->value('balance'));
        $this->assertSame(1, (int) \App\Models\WalletTransaction::where('idempotency_key', 'wallet_topup:' . $topup->id)->count());
    }

    public function test_bad_signature_is_rejected_without_credit(): void
    {
        $topup = $this->startTopup(50);
        $payload = $this->callbackPayload((string) $topup->id, 'PAID', 50);
        $payload['messageSignature'] = 'deadbeef';

        $this->postJson('/api/v2/wallet/topup/callback', $payload)->assertStatus(400);

        $this->assertSame(0.0, (float) Wallet::where('user_id', $this->customer->id)->value('balance'));
        $this->assertSame(WalletTopup::STATUS_PENDING, $topup->fresh()->status);
    }

    public function test_amount_mismatch_is_rejected(): void
    {
        $topup = $this->startTopup(50);
        // Correctly-signed but for a different amount than the intent.
        $payload = $this->callbackPayload((string) $topup->id, 'PAID', 999);

        $this->postJson('/api/v2/wallet/topup/callback', $payload)->assertStatus(422);

        $this->assertSame(0.0, (float) Wallet::where('user_id', $this->customer->id)->value('balance'));
        $this->assertSame(WalletTopup::STATUS_PENDING, $topup->fresh()->status);
    }

    public function test_failed_callback_marks_failed_and_does_not_credit(): void
    {
        $topup = $this->startTopup(50);
        $payload = $this->callbackPayload((string) $topup->id, 'FAILED', 50);

        $this->postJson('/api/v2/wallet/topup/callback', $payload)->assertOk();

        $this->assertSame(0.0, (float) Wallet::where('user_id', $this->customer->id)->value('balance'));
        $this->assertSame(WalletTopup::STATUS_FAILED, $topup->fresh()->status);
    }

    public function test_show_is_scoped_to_owner(): void
    {
        $topup = $this->startTopup(50);
        $other = User::query()->where('id', '!=', $this->customer->id)->firstOrFail();

        $this->actingAs($this->customer, 'sanctum')->getJson("/api/v2/wallet/topup/{$topup->id}")->assertOk();
        $this->actingAs($other, 'sanctum')->getJson("/api/v2/wallet/topup/{$topup->id}")->assertNotFound();
    }
}
