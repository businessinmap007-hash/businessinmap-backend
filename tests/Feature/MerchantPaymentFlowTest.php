<?php

namespace Tests\Feature;

use App\Models\MerchantPayment;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Payments\MerchantPaymentAccountService;
use App\Services\Payments\PaymentSettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Customer→merchant payment: the authed start endpoint routes the charge to the
 * merchant's Fawry sub-account when configured (else the platform account), and
 * the PUBLIC callback settles it — verified with the SAME account's key, and
 * crediting NO platform wallet. Covers routing, the security guards, and scoping.
 */
class MerchantPaymentFlowTest extends TestCase
{
    use DatabaseTransactions;

    private const PLATFORM_SEC = 'platform-sec-key';
    private const MERCHANT_SEC = 'merchant-sec-key';

    private User $customer;
    private User $business;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.fawry.merchant_code' => 'PLATFORM_MC',
            'services.fawry.security_key' => self::PLATFORM_SEC,
            'services.fawry.return_url' => 'https://example.com/return',
            'services.fawry.base_url' => 'https://atfawry.com',
            'services.fawry.currency' => 'EGP',
            'services.payments.default_gateway' => 'fawry',
        ]);

        $this->customer = User::query()->where('type', User::TYPE_CLIENT)->orderBy('id')->first()
            ?? User::query()->orderBy('id')->firstOrFail();
        $this->business = User::query()->where('type', User::TYPE_BUSINESS)
            ->where('id', '!=', $this->customer->id)->orderBy('id')->firstOrFail();
    }

    private function accounts(): MerchantPaymentAccountService
    {
        return app(MerchantPaymentAccountService::class);
    }

    private function enableMerchantRouting(): void
    {
        app(PaymentSettingsService::class)->setSubMerchantEnabled(true);
        $this->accounts()->save($this->business->id, 'MERCH-CODE', self::MERCHANT_SEC, true);
    }

    /** A Fawry callback payload signed with the given security key. */
    private function callbackPayload(string $ref, string $status, float $amount, string $sec): array
    {
        $fawryRef = 'FWREF-' . $ref;
        $amt = number_format($amount, 2, '.', '');
        $p = [
            'merchantRefNumber' => $ref,
            'fawryRefNumber' => $fawryRef,
            'paymentAmount' => $amt,
            'orderAmount' => $amt,
            'orderStatus' => $status,
            'paymentMethod' => 'CARD',
            'paymentReferenceNumber' => '',
        ];
        $p['messageSignature'] = hash('sha256', $fawryRef . $ref . $amt . $amt . $status . 'CARD' . '' . $sec);

        return $p;
    }

    private function start(float $amount): MerchantPayment
    {
        $res = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v2/merchant-payments', ['business_id' => $this->business->id, 'amount' => $amount])
            ->assertCreated()
            ->assertJsonPath('data.status', MerchantPayment::STATUS_PENDING);

        return MerchantPayment::findOrFail($res->json('data.id'));
    }

    public function test_payment_routes_to_the_merchant_and_settles_without_a_wallet_credit(): void
    {
        $this->enableMerchantRouting();
        $payment = $this->start(50);

        $this->assertSame(MerchantPayment::ROUTED_MERCHANT, $payment->routed_to);
        $this->assertSame('MERCH-CODE', $payment->meta['charge_request']['merchantCode'] ?? null);

        $before = WalletTransaction::where('wallet_id', function ($q) {
            $q->select('id')->from('wallets')->where('user_id', $this->customer->id)->limit(1);
        })->count();

        // Callback MUST be signed with the MERCHANT's key.
        $this->postJson('/api/v2/merchant-payments/callback', $this->callbackPayload((string) $payment->id, 'PAID', 50, self::MERCHANT_SEC))
            ->assertOk();

        $this->assertSame(MerchantPayment::STATUS_PAID, $payment->fresh()->status);

        $after = WalletTransaction::where('wallet_id', function ($q) {
            $q->select('id')->from('wallets')->where('user_id', $this->customer->id)->limit(1);
        })->count();
        $this->assertSame($before, $after, 'a merchant payment must NOT touch the platform wallet');

        // Replay → still paid once, no change.
        $this->postJson('/api/v2/merchant-payments/callback', $this->callbackPayload((string) $payment->id, 'PAID', 50, self::MERCHANT_SEC))
            ->assertOk();
        $this->assertSame(MerchantPayment::STATUS_PAID, $payment->fresh()->status);
    }

    public function test_falls_back_to_the_platform_account_when_merchant_not_configured(): void
    {
        // Feature off by default → routed to platform, signed with the platform key.
        $payment = $this->start(30);

        $this->assertSame(MerchantPayment::ROUTED_PLATFORM, $payment->routed_to);
        $this->assertSame('PLATFORM_MC', $payment->meta['charge_request']['merchantCode'] ?? null);

        $this->postJson('/api/v2/merchant-payments/callback', $this->callbackPayload((string) $payment->id, 'PAID', 30, self::PLATFORM_SEC))
            ->assertOk();
        $this->assertSame(MerchantPayment::STATUS_PAID, $payment->fresh()->status);
    }

    public function test_the_merchant_key_is_required_a_platform_signed_callback_is_rejected(): void
    {
        $this->enableMerchantRouting();
        $payment = $this->start(50);

        // Signed with the PLATFORM key, but the charge was on the MERCHANT account → reject.
        $this->postJson('/api/v2/merchant-payments/callback', $this->callbackPayload((string) $payment->id, 'PAID', 50, self::PLATFORM_SEC))
            ->assertStatus(400);
        $this->assertSame(MerchantPayment::STATUS_PENDING, $payment->fresh()->status);
    }

    public function test_amount_mismatch_is_rejected(): void
    {
        $this->enableMerchantRouting();
        $payment = $this->start(50);

        $this->postJson('/api/v2/merchant-payments/callback', $this->callbackPayload((string) $payment->id, 'PAID', 999, self::MERCHANT_SEC))
            ->assertStatus(422);
        $this->assertSame(MerchantPayment::STATUS_PENDING, $payment->fresh()->status);
    }

    public function test_failed_callback_marks_failed(): void
    {
        $this->enableMerchantRouting();
        $payment = $this->start(50);

        $this->postJson('/api/v2/merchant-payments/callback', $this->callbackPayload((string) $payment->id, 'FAILED', 50, self::MERCHANT_SEC))
            ->assertOk();
        $this->assertSame(MerchantPayment::STATUS_FAILED, $payment->fresh()->status);
    }

    public function test_cannot_pay_a_non_business(): void
    {
        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v2/merchant-payments', ['business_id' => $this->customer->id, 'amount' => 20])
            ->assertStatus(422)->assertJsonValidationErrors('business_id');
    }

    public function test_cannot_pay_yourself(): void
    {
        $this->actingAs($this->business, 'sanctum')
            ->postJson('/api/v2/merchant-payments', ['business_id' => $this->business->id, 'amount' => 20])
            ->assertStatus(422)->assertJsonValidationErrors('business_id');
    }

    public function test_admin_oversight_view_renders(): void
    {
        $admin = User::query()->where('type', 'admin')->orderBy('id')->first();
        if (! $admin) {
            $this->markTestSkipped('Needs an admin user.');
        }

        $this->enableMerchantRouting();
        $this->start(50);

        $this->actingAs($admin)->get('/admin/merchant-payments')->assertOk();
    }

    public function test_show_is_scoped_to_owner(): void
    {
        $payment = $this->start(50);
        $other = User::query()->where('id', '!=', $this->customer->id)->where('id', '!=', $this->business->id)->firstOrFail();

        $this->actingAs($this->customer, 'sanctum')->getJson("/api/v2/merchant-payments/{$payment->id}")->assertOk();
        $this->actingAs($other, 'sanctum')->getJson("/api/v2/merchant-payments/{$payment->id}")->assertNotFound();
    }
}
