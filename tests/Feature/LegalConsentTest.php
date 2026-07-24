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
            'terms_accepted' => true,
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
            'terms_accepted' => true,
        ])->assertJsonPath('status', 200);

        $user = User::query()->where('email', $email)->firstOrFail();
        $this->assertConsentRecorded((int) $user->id);
    }

    public function test_api_register_without_consent_is_rejected(): void
    {
        $email = 'noconsent-' . uniqid() . '@example.test';

        // No terms_accepted → registration is cancelled, and no user is created.
        $this->postJson('/api/v2/auth/register', [
            'name' => 'No Consent',
            'email' => $email,
            'phone' => '0100' . random_int(1000000, 9999999),
            'password' => 'Str0ng-pass',
            'password_confirmation' => 'Str0ng-pass',
        ])->assertStatus(422)->assertJsonValidationErrors('terms_accepted');

        $this->assertDatabaseMissing('users', ['email' => $email]);
    }

    public function test_web_signup_without_consent_is_rejected(): void
    {
        $email = 'webnoconsent-' . uniqid() . '@example.test';

        // terms_accepted explicitly refused → cancelled, no user.
        $this->postJson('/user/signup', [
            'first_name' => 'Web', 'last_name' => 'NoConsent',
            'email' => $email,
            'phone' => '0100' . random_int(1000000, 9999999),
            'password' => 'A-good-password1',
            'terms_accepted' => false,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('users', ['email' => $email]);
    }

    public function test_features_page_renders_with_the_terms_section(): void
    {
        $this->get('/features')
            ->assertOk()
            ->assertSee('خصائص وخدمات التطبيق')
            ->assertSee('المحفظة والمدفوعات')
            ->assertSee('الشروط والأحكام وسياسة الخصوصية');
    }

    public function test_terms_page_renders_with_standard_sections(): void
    {
        $this->get('/terms-and-conditions')
            ->assertOk()
            ->assertSee('قبول الشروط')
            ->assertSee('المحفظة والمدفوعات')
            ->assertSee('الملكية الفكرية');
    }

    public function test_about_page_explains_the_app_and_its_dealings(): void
    {
        $this->get('/aboutus')
            ->assertOk()
            ->assertSee('كيف تتم التعاملات')
            ->assertSee('خدمات ومميزات التطبيق')
            ->assertSee('المحفظة والرسوم');
    }

    public function test_privacy_page_renders(): void
    {
        $this->get('/privacy-and-policy')
            ->assertOk()
            ->assertSee('سياسة الخصوصية');
    }
}
