<?php

namespace Tests\Feature;

use App\Models\MerchantPaymentAccount;
use App\Models\User;
use App\Models\WalletTopup;
use App\Services\Payments\FawryGateway;
use App\Services\Payments\MerchantPaymentAccountService;
use App\Services\Payments\PaymentGatewayFactory;
use App\Services\Payments\PaymentSettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fawry sub-account routing: when the feature is on and a merchant has its own
 * credentials, a charge for that merchant is billed to the MERCHANT's Fawry
 * account (its merchant_code + security_key), not the platform's. Off by default,
 * the platform account is used. The merchant key is encrypted at rest.
 */
class MerchantPaymentAccountTest extends TestCase
{
    use DatabaseTransactions;

    private function service(): MerchantPaymentAccountService
    {
        return app(MerchantPaymentAccountService::class);
    }

    private function factory(): PaymentGatewayFactory
    {
        return app(PaymentGatewayFactory::class);
    }

    private function aBusiness(): User
    {
        return User::query()->where('type', User::TYPE_BUSINESS)->orderBy('id')->firstOrFail();
    }

    /** A throwaway (unsaved) top-up just to exercise createCharge. */
    private function topupFor(User $user): WalletTopup
    {
        $t = new WalletTopup(['user_id' => $user->id, 'amount' => 50]);
        $t->id = 987654;
        $t->merchant_ref = '987654';

        return $t;
    }

    public function test_disabled_feature_routes_to_no_merchant_gateway(): void
    {
        $business = $this->aBusiness();
        // Even a fully configured account is ignored while the feature is off.
        $this->service()->save($business->id, 'MER-CODE', 'mer-secret', true);
        app(PaymentSettingsService::class)->setSubMerchantEnabled(false);

        $this->assertNull($this->service()->configFor($business->id));
        $this->assertNull($this->factory()->makeForMerchant($business->id));
    }

    public function test_configured_merchant_gateway_carries_the_merchant_code_and_key(): void
    {
        $business = $this->aBusiness();
        app(PaymentSettingsService::class)->setSubMerchantEnabled(true);
        $this->service()->save($business->id, 'MER-CODE-42', 'merchant-secret-42', true);

        $config = $this->service()->configFor($business->id);
        $this->assertSame('MER-CODE-42', $config['merchant_code']);
        $this->assertSame('merchant-secret-42', $config['security_key']);

        $gateway = $this->factory()->makeForMerchant($business->id);
        $this->assertInstanceOf(FawryGateway::class, $gateway);

        // The built charge carries the merchant's code, and is signed with the
        // merchant's key — a gateway on a DIFFERENT key produces a different signature.
        $charge = $gateway->createCharge($this->topupFor($business))->chargeRequest;
        $this->assertSame('MER-CODE-42', $charge['merchantCode'] ?? null);

        $otherKeySig = (new FawryGateway(['merchant_code' => 'MER-CODE-42', 'security_key' => 'a-different-key']))
            ->createCharge($this->topupFor($business))->chargeRequest['signature'] ?? null;
        $this->assertNotSame($otherKeySig, $charge['signature'] ?? null, 'the merchant key must drive the signature');
    }

    public function test_half_configured_merchant_falls_back(): void
    {
        $business = $this->aBusiness();
        app(PaymentSettingsService::class)->setSubMerchantEnabled(true);
        // Code but no key → must NOT route (would mis-sign with the platform key).
        $this->service()->save($business->id, 'CODE-ONLY', null, true);

        $this->assertNull($this->service()->configFor($business->id));
    }

    public function test_inactive_account_falls_back(): void
    {
        $business = $this->aBusiness();
        app(PaymentSettingsService::class)->setSubMerchantEnabled(true);
        $this->service()->save($business->id, 'CODE', 'secret', false);

        $this->assertNull($this->service()->configFor($business->id));
    }

    public function test_security_key_is_encrypted_at_rest(): void
    {
        $business = $this->aBusiness();
        $this->service()->save($business->id, 'CODE', 'plain-merchant-secret', true);

        $raw = DB::table('merchant_payment_accounts')
            ->where('business_id', $business->id)->where('gateway', 'fawry')->value('security_key');

        $this->assertNotSame('plain-merchant-secret', $raw, 'the merchant key must not be stored in plaintext');
        $this->assertSame(
            'plain-merchant-secret',
            (string) MerchantPaymentAccount::query()->where('business_id', $business->id)->value('security_key'),
        );
    }

    public function test_blank_key_keeps_the_existing_one(): void
    {
        $business = $this->aBusiness();
        $this->service()->save($business->id, 'CODE-1', 'first-secret', true);
        // Edit the code, leave the key blank.
        $this->service()->save($business->id, 'CODE-2', null, true);

        app(PaymentSettingsService::class)->setSubMerchantEnabled(true);
        $config = $this->service()->configFor($business->id);

        $this->assertSame('CODE-2', $config['merchant_code']);
        $this->assertSame('first-secret', $config['security_key']);
    }

    public function test_admin_can_toggle_and_save_a_merchant_account(): void
    {
        $admin = User::query()->where('type', 'admin')->orderBy('id')->first();
        if (! $admin) {
            $this->markTestSkipped('Needs an admin user.');
        }
        $business = $this->aBusiness();

        $this->actingAs($admin)->get('/admin/merchant-payment-accounts')->assertOk();

        $this->actingAs($admin)
            ->put('/admin/merchant-payment-accounts/toggle', ['enabled' => '1'])
            ->assertRedirect(route('admin.merchant-payment-accounts.index'));
        $this->assertTrue(app(PaymentSettingsService::class)->subMerchantEnabled());

        $this->actingAs($admin)
            ->post('/admin/merchant-payment-accounts', [
                'business_id' => $business->id,
                'merchant_code' => 'PANEL-MER-CODE',
                'security_key' => 'panel-mer-secret',
                'is_active' => '1',
            ])->assertRedirect(route('admin.merchant-payment-accounts.index'));

        $config = $this->service()->configFor($business->id);
        $this->assertSame('PANEL-MER-CODE', $config['merchant_code']);
        $this->assertSame('panel-mer-secret', $config['security_key']);
    }

    public function test_non_business_is_rejected(): void
    {
        $admin = User::query()->where('type', 'admin')->orderBy('id')->first();
        if (! $admin) {
            $this->markTestSkipped('Needs an admin user.');
        }

        $this->actingAs($admin)
            ->post('/admin/merchant-payment-accounts', [
                'business_id' => $admin->id, // an admin, not a business
                'merchant_code' => 'X',
                'is_active' => '1',
            ])->assertSessionHasErrors('business_id');
    }

    public function test_non_admin_cannot_reach_the_page(): void
    {
        $user = User::query()->where('type', '!=', 'admin')->orderBy('id')->firstOrFail();

        $this->actingAs($user)->get('/admin/merchant-payment-accounts')->assertStatus(302);
    }
}
