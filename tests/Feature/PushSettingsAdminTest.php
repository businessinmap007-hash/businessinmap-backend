<?php

namespace Tests\Feature;

use App\Models\PushSetting;
use App\Models\User;
use App\Services\Notifications\PushSettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Runtime, DB-backed Firebase credentials (paste-and-go from AdminV2), mirroring
 * the Fawry payment-settings feature. Verifies JSON validation, encryption at
 * rest, that FirebasePushService reads the saved account, and the admin guards.
 * Rolls back.
 */
class PushSettingsAdminTest extends TestCase
{
    use DatabaseTransactions;

    private const VALID_JSON = '{"type":"service_account","project_id":"bim-demo","private_key":"-----BEGIN PRIVATE KEY-----\nAAA\n-----END PRIVATE KEY-----\n","client_email":"fcm@bim-demo.iam.gserviceaccount.com","token_uri":"https://oauth2.googleapis.com/token"}';

    private function service(): PushSettingsService
    {
        return app(PushSettingsService::class);
    }

    public function test_valid_json_is_saved_and_parsed(): void
    {
        $error = $this->service()->saveFirebase(self::VALID_JSON);

        $this->assertNull($error);
        $this->assertSame('bim-demo', $this->service()->firebaseServiceAccount()['project_id']);
    }

    public function test_invalid_json_is_rejected(): void
    {
        $error = $this->service()->saveFirebase('not-json{');

        $this->assertNotNull($error);
        $this->assertDatabaseMissing('push_settings', ['key' => 'firebase.service_account_json']);
    }

    public function test_json_missing_required_key_is_rejected(): void
    {
        // No private_key.
        $error = $this->service()->saveFirebase('{"project_id":"x","client_email":"y","token_uri":"z"}');

        $this->assertNotNull($error);
        $this->assertStringContainsString('private_key', $error);
    }

    public function test_service_account_is_encrypted_at_rest(): void
    {
        $this->service()->saveFirebase(self::VALID_JSON);

        $row = PushSetting::query()->where('key', 'firebase.service_account_json')->firstOrFail();

        $this->assertTrue($row->is_encrypted);
        $this->assertStringNotContainsString('private_key', $row->value, 'The raw JSON must not be stored in plaintext.');
        $this->assertSame(self::VALID_JSON, Crypt::decryptString($row->value));
    }

    public function test_blank_keeps_the_existing_credential(): void
    {
        $this->service()->saveFirebase(self::VALID_JSON);
        $error = $this->service()->saveFirebase('');

        $this->assertNull($error);
        $this->assertSame('bim-demo', $this->service()->firebaseServiceAccount()['project_id']);
    }

    public function test_form_state_never_exposes_the_private_key(): void
    {
        $this->service()->saveFirebase(self::VALID_JSON);

        $state = $this->service()->firebaseFormState();

        $this->assertTrue($state['is_set']);
        $this->assertSame('db', $state['source']);
        $this->assertSame('bim-demo', $state['project_id']);
        $this->assertArrayNotHasKey('private_key', $state);
    }

    public function test_firebase_push_service_reads_the_saved_account(): void
    {
        $this->service()->saveFirebase(self::VALID_JSON);

        // FirebasePushService::serviceAccount() is private; verifyCredentials()
        // exercises it — with a bogus key the token exchange fails, but reaching
        // 'token_exchange_failed' (not 'no_project_id') proves the account loaded.
        $result = app(\App\Services\Notifications\FirebasePushService::class)->verifyCredentials();

        $this->assertSame('bim-demo', $result['project_id']);
        $this->assertFalse($result['ok']);
        $this->assertSame('token_exchange_failed', $result['reason']);
    }

    public function test_admin_can_open_and_save_the_page(): void
    {
        $admin = User::query()->where('type', 'admin')->orderBy('id')->first();
        if (! $admin) {
            $this->markTestSkipped('Needs an admin user.');
        }

        $this->actingAs($admin)
            ->get('/admin/push-settings')
            ->assertOk()
            ->assertSee('Firebase');

        $this->actingAs($admin)
            ->put('/admin/push-settings', ['service_account_json' => self::VALID_JSON])
            ->assertRedirect(route('admin.push-settings.edit'))
            ->assertSessionHas('success');

        $this->assertSame('bim-demo', $this->service()->firebaseServiceAccount()['project_id']);
    }

    public function test_admin_save_of_invalid_json_shows_an_error(): void
    {
        $admin = User::query()->where('type', 'admin')->orderBy('id')->first();
        if (! $admin) {
            $this->markTestSkipped('Needs an admin user.');
        }

        $this->actingAs($admin)
            ->put('/admin/push-settings', ['service_account_json' => 'broken{'])
            ->assertSessionHasErrors('service_account_json');
    }

    public function test_non_admin_cannot_reach_the_page(): void
    {
        $user = User::query()->where('type', '!=', 'admin')->orderBy('id')->firstOrFail();

        $this->actingAs($user)
            ->get('/admin/push-settings')
            ->assertStatus(302);
    }
}
