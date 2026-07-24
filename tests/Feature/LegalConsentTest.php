<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Every new account records its acceptance of the current terms + privacy
 * versions at signup (both the API register and the legacy web form), and the
 * public features/terms pages render. This is the consent audit trail.
 */
class LegalConsentTest extends TestCase
{
    use DatabaseTransactions;

    private function assertConsentRecorded(int $userId): void
    {
        $this->assertDatabaseHas('user_consents', [
            'user_id' => $userId,
            'document' => UserConsent::DOCUMENT_TERMS,
            'version' => (string) config('legal.terms_version'),
        ]);
        $this->assertDatabaseHas('user_consents', [
            'user_id' => $userId,
            'document' => UserConsent::DOCUMENT_PRIVACY,
            'version' => (string) config('legal.privacy_version'),
        ]);
    }

    public function test_api_register_records_terms_and_privacy_consent(): void
    {
        $email = 'consent-' . uniqid() . '@example.test';

        $this->postJson('/api/v2/auth/register', [
            'name' => 'Consent User',
            'email' => $email,
            'phone' => '0100' . random_int(1000000, 9999999),
            'password' => 'Str0ng-pass',
            'password_confirmation' => 'Str0ng-pass',
        ])->assertCreated();

        $user = User::query()->where('email', $email)->firstOrFail();
        $this->assertConsentRecorded((int) $user->id);
    }

    public function test_web_signup_records_consent(): void
    {
        $email = 'webconsent-' . uniqid() . '@example.test';

        $this->postJson('/user/signup', [
            'first_name' => 'Web', 'last_name' => 'Consent',
            'email' => $email,
            'phone' => '0100' . random_int(1000000, 9999999),
            'password' => 'A-good-password1',
        ])->assertJsonPath('status', 200);

        $user = User::query()->where('email', $email)->firstOrFail();
        $this->assertConsentRecorded((int) $user->id);
    }

    public function test_features_page_renders_with_the_terms_section(): void
    {
        $this->get('/features')
            ->assertOk()
            ->assertSee('خصائص وخدمات التطبيق')
            ->assertSee('المحفظة والمدفوعات')
            ->assertSee('الشروط والأحكام وسياسة الخصوصية');
    }

    public function test_terms_page_renders(): void
    {
        $this->get('/terms-and-conditions')->assertOk();
    }
}
