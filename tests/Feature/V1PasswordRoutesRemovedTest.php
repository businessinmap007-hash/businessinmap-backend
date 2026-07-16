<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * BIM-14.0 — the v1 password routes were an unauthenticated account takeover:
 * `POST /api/v1/password/reset` set a password from an email alone, with no code
 * verified, and `password/forgot` returned the reset code in its own response.
 *
 * They are gone. This test is the guard: it fails if anyone reinstates them, and
 * proves the attack no longer lands. Use /api/v2/auth/password/* instead.
 */
class V1PasswordRoutesRemovedTest extends TestCase
{
    use DatabaseTransactions;

    public static function removedRoutes(): array
    {
        return [
            'reset (was: set any password from an email alone)' => ['api/v1/password/reset'],
            'forgot (was: returned the reset code in the response)' => ['api/v1/password/forgot'],
            'check' => ['api/v1/password/check'],
            'forgot/resend' => ['api/v1/password/forgot/resend'],
        ];
    }

    /** @dataProvider removedRoutes */
    public function test_the_route_is_not_registered(string $uri): void
    {
        $registered = collect(Route::getRoutes())->map(fn ($r) => $r->uri())->all();

        $this->assertNotContains($uri, $registered, "{$uri} is an account-takeover route and must stay removed");
    }

    public function test_posting_an_email_and_password_can_no_longer_seize_an_account(): void
    {
        $victim = User::query()->whereNotNull('email')->orderBy('id')->first();

        if (! $victim) {
            $this->markTestSkipped('Needs a user with an email.');
        }

        $originalHash = $victim->password;

        // The exact request that used to take the account over.
        $res = $this->postJson('/api/v1/password/reset', [
            'email' => $victim->email,
            'password' => 'attacker-chosen-password',
            'password_confirmation' => 'attacker-chosen-password',
        ]);

        $this->assertSame(404, $res->status(), 'the takeover route must be gone');
        $this->assertSame($originalHash, $victim->fresh()->password, 'the password must be untouched');
        $this->assertFalse(Hash::check('attacker-chosen-password', $victim->fresh()->password));
    }

    public function test_the_v2_replacement_is_the_live_flow_and_does_not_leak(): void
    {
        $victim = User::query()->whereNotNull('email')->orderBy('id')->first();

        if (! $victim) {
            $this->markTestSkipped('Needs a user with an email.');
        }

        $res = $this->postJson('/api/v2/auth/password/forgot', ['email' => $victim->email]);

        $res->assertOk();
        $this->assertStringNotContainsString('code', strtolower(json_encode($res->json('data') ?? [])), 'v2 must never return the reset code');
    }
}
