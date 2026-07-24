<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The account-password policy (App\Support\PasswordPolicy): 8–20 chars, with an
 * upper-case letter, a lower-case letter and a digit — enforced identically on
 * every user-facing write. These pin the two public write points (web signup +
 * API register); reset and profile-change share the same rules() call.
 */
class PasswordPolicyTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array<string, array{0: string}> */
    public static function weakPasswords(): array
    {
        return [
            'too short'        => ['Ab1cdef'],        // 7 chars
            'no digit'         => ['Abcdefgh'],
            'no upper'         => ['abcdefg1'],
            'no lower'         => ['ABCDEFG1'],
            'too long'         => ['Abcdefg1' . str_repeat('x', 20)],
        ];
    }

    /** @dataProvider weakPasswords */
    public function test_api_register_rejects_a_weak_password(string $password): void
    {
        $this->postJson('/api/v2/auth/register', [
            'name' => 'Weak Pass',
            'email' => 'weak-' . uniqid() . '@example.test',
            'phone' => '0100' . random_int(1000000, 9999999),
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_api_register_accepts_a_compliant_password(): void
    {
        $this->postJson('/api/v2/auth/register', [
            'name' => 'Strong Pass',
            'email' => 'strong-' . uniqid() . '@example.test',
            'phone' => '0100' . random_int(1000000, 9999999),
            'password' => 'Str0ng-pass',
            'password_confirmation' => 'Str0ng-pass',
            'terms_accepted' => true,
        ])->assertCreated();
    }

    public function test_web_signup_rejects_a_weak_password(): void
    {
        // The web form has no minimum before this policy; a 5-char lower-only
        // password must now be refused (422).
        $this->postJson('/user/signup', [
            'first_name' => 'Weak', 'last_name' => 'Web',
            'email' => 'weakweb-' . uniqid() . '@example.test',
            'phone' => '0100' . random_int(1000000, 9999999),
            'password' => 'weak',
        ])->assertStatus(422);
    }

    public function test_web_signup_accepts_a_compliant_password(): void
    {
        $this->postJson('/user/signup', [
            'first_name' => 'Strong', 'last_name' => 'Web',
            'email' => 'strongweb-' . uniqid() . '@example.test',
            'phone' => '0100' . random_int(1000000, 9999999),
            'password' => 'Str0ng-pass',
            'terms_accepted' => true,
        ])->assertJsonPath('status', 200);
    }
}
