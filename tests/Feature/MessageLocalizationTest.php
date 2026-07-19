<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Slice C: controller messages are wrapped in __() and translated via the
 * JSON language files (resources/lang/{ar,en}.json). This proves a user-facing
 * message actually comes back in the caller's language, and — critically —
 * that Arabic never leaks English through the fallback locale.
 */
class MessageLocalizationTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(): User
    {
        return User::query()->forceCreate([
            'name' => 'Loc '.uniqid(),
            'phone' => '01'.random_int(100000000, 999999999),
            'email' => 'loc'.uniqid().'@test.local',
            'password' => Hash::make('secret123'),
            'api_token' => Str::random(60),
            'type' => 'client',
        ]);
    }

    /** A wrong-password login returns the message in the requested language. */
    public function test_login_error_is_english_when_asked_in_english(): void
    {
        $user = $this->makeUser();

        $this->withHeaders(['Accept-Language' => 'en'])
            ->postJson('/api/v2/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Email or password is incorrect.']);
    }

    public function test_login_error_is_arabic_when_asked_in_arabic(): void
    {
        $user = $this->makeUser();

        $this->withHeaders(['Accept-Language' => 'ar'])
            ->postJson('/api/v2/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.']);
    }

    /**
     * The fallback-locale trap: fallback_locale is 'en', so without an ar.json
     * entry a missing key would resolve to the English value. ar.json carries an
     * identity entry for every key precisely to stop that. Guard it here.
     */
    public function test_arabic_never_falls_through_to_english(): void
    {
        app()->setLocale('ar');

        $this->assertSame('غير مصرح.', __('غير مصرح.'));
        $this->assertSame('تسجيل الخروج', __('تسجيل الخروج'));
        $this->assertSame('المنشورات', __('المنشورات'));
    }

    public function test_english_is_served_when_selected(): void
    {
        app()->setLocale('en');

        $this->assertSame('Unauthorized.', __('غير مصرح.'));
        $this->assertSame('Posts', __('المنشورات'));
    }

    /** The panel language toggle stores a valid choice in the session. */
    public function test_panel_locale_switch_persists_a_valid_choice(): void
    {
        $this->get('/admin/locale/en')
            ->assertRedirect();

        $this->assertSame('en', session('panel_locale'));
    }

    public function test_panel_locale_switch_ignores_an_unsupported_choice(): void
    {
        $this->withSession(['panel_locale' => 'ar'])
            ->get('/admin/locale/zz')
            ->assertRedirect();

        $this->assertSame('ar', session('panel_locale'), 'an unsupported locale must not overwrite the stored one');
    }
}
