<?php

namespace Tests\Feature;

use App\Models\PaymentSetting;
use App\Models\User;
use App\Services\Payments\FawryGateway;
use App\Services\Payments\PaymentGatewayFactory;
use App\Services\Payments\PaymentSettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Runtime, DB-backed Fawry credentials (paste-and-go from AdminV2). Verifies the
 * env→DB override merge, encryption at rest for the security key, that the
 * gateway factory picks up saved values, and the admin screen guards. Rolls back.
 */
class PaymentSettingsAdminTest extends TestCase
{
    use DatabaseTransactions;

    private function service(): PaymentSettingsService
    {
        return app(PaymentSettingsService::class);
    }

    public function test_db_values_override_the_env_baseline(): void
    {
        config(['services.fawry.merchant_code' => 'ENV_CODE']);

        $this->service()->saveFawry(['merchant_code' => 'DB_CODE_123']);

        $this->assertSame('DB_CODE_123', $this->service()->fawryConfig()['merchant_code']);
    }

    public function test_env_is_used_when_no_db_override(): void
    {
        config(['services.fawry.currency' => 'EGP']);

        // No DB row for currency.
        $this->assertSame('EGP', $this->service()->fawryConfig()['currency']);
    }

    public function test_security_key_is_encrypted_at_rest(): void
    {
        $this->service()->saveFawry(['security_key' => 'super-secret-key']);

        $row = PaymentSetting::query()->where('key', 'fawry.security_key')->firstOrFail();

        $this->assertTrue($row->is_encrypted);
        $this->assertNotSame('super-secret-key', $row->value, 'The raw secret must not be stored in plaintext.');
        $this->assertSame('super-secret-key', Crypt::decryptString($row->value));

        // And it round-trips back out through the merged config.
        $this->assertSame('super-secret-key', $this->service()->fawryConfig()['security_key']);
    }

    public function test_blank_security_key_keeps_the_existing_one(): void
    {
        $this->service()->saveFawry(['security_key' => 'first-key']);
        $this->service()->saveFawry(['security_key' => '', 'merchant_code' => 'CODE']);

        $this->assertSame('first-key', $this->service()->fawryConfig()['security_key']);
        $this->assertSame('CODE', $this->service()->fawryConfig()['merchant_code']);
    }

    public function test_form_state_never_exposes_the_secret_value(): void
    {
        $this->service()->saveFawry(['security_key' => 'do-not-leak']);

        $state = $this->service()->fawryFormState();

        $this->assertSame('', $state['security_key']['value'], 'The secret value must not be sent to the form.');
        $this->assertTrue($state['security_key']['is_set']);
        $this->assertSame('db', $state['security_key']['source']);
    }

    public function test_factory_builds_a_fawry_gateway_from_saved_config(): void
    {
        $this->service()->saveFawry([
            'merchant_code' => 'FACTORY_CODE',
            'security_key' => 'factory-secret',
        ]);

        $gateway = app(PaymentGatewayFactory::class)->make('fawry');

        $this->assertInstanceOf(FawryGateway::class, $gateway);
        $this->assertSame('fawry', $gateway->name());
    }

    public function test_admin_can_open_and_save_the_settings_page(): void
    {
        $admin = User::query()->where('type', 'admin')->orderBy('id')->first();
        if (! $admin) {
            $this->markTestSkipped('Needs an admin user.');
        }

        $this->actingAs($admin)
            ->get('/admin/payment-settings')
            ->assertOk()
            ->assertSee('Fawry');

        $this->actingAs($admin)
            ->put('/admin/payment-settings', [
                'merchant_code' => 'PANEL_CODE',
                'security_key' => 'panel-secret',
                'currency' => 'EGP',
            ])
            ->assertRedirect(route('admin.payment-settings.edit'));

        $this->assertSame('PANEL_CODE', $this->service()->fawryConfig()['merchant_code']);
        $this->assertSame('panel-secret', $this->service()->fawryConfig()['security_key']);
    }

    public function test_non_admin_cannot_reach_the_settings_page(): void
    {
        $user = User::query()->where('type', '!=', 'admin')->orderBy('id')->firstOrFail();

        $this->actingAs($user)
            ->get('/admin/payment-settings')
            ->assertStatus(302); // bounced by the admin.v2 guard (not the settings page)
    }
}
