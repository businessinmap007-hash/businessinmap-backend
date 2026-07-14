<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * v2 password reset (hashed code flow): no enumeration on forgot, code
 * verify/consume, expiry, and token revocation on reset. The code is never
 * returned, so verify/reset tests seed a known hashed code directly. Rolls back.
 */
class PasswordResetApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->user = User::query()->orderBy('id')->firstOrFail();
        DB::table('password_reset_codes')->where('email', $this->user->email)->delete();
    }

    private function seedCode(string $plain = '123456', int $ttlMinutes = 15, int $attempts = 0): void
    {
        DB::table('password_reset_codes')->updateOrInsert(
            ['email' => $this->user->email],
            [
                'code_hash' => Hash::make($plain),
                'attempts' => $attempts,
                'expires_at' => Carbon::now()->addMinutes($ttlMinutes),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]
        );
    }

    public function test_forgot_issues_a_code_for_a_real_account(): void
    {
        $this->postJson('/api/v2/auth/password/forgot', ['email' => $this->user->email])->assertOk();

        $this->assertDatabaseHas('password_reset_codes', ['email' => $this->user->email]);
    }

    public function test_forgot_does_not_enumerate_unknown_emails(): void
    {
        $ghost = 'no_such_' . uniqid() . '@example.com';

        $this->postJson('/api/v2/auth/password/forgot', ['email' => $ghost])->assertOk();

        $this->assertDatabaseMissing('password_reset_codes', ['email' => $ghost]);
    }

    public function test_verify_accepts_correct_code_and_rejects_wrong(): void
    {
        $this->seedCode('123456');

        $this->postJson('/api/v2/auth/password/verify', ['email' => $this->user->email, 'code' => '123456'])
            ->assertOk()->assertJsonPath('data.valid', true);

        $this->postJson('/api/v2/auth/password/verify', ['email' => $this->user->email, 'code' => '000000'])
            ->assertStatus(422);
    }

    public function test_reset_changes_password_and_consumes_code(): void
    {
        $this->seedCode('654321');
        $this->user->createToken('old-session');

        $this->postJson('/api/v2/auth/password/reset', [
            'email' => $this->user->email,
            'code' => '654321',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertOk();

        $this->assertTrue(Hash::check('brand-new-pass', $this->user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_codes', ['email' => $this->user->email]);
        $this->assertSame(0, (int) $this->user->tokens()->count());
    }

    public function test_expired_code_is_rejected(): void
    {
        $this->seedCode('111111', ttlMinutes: -1);

        $this->postJson('/api/v2/auth/password/reset', [
            'email' => $this->user->email,
            'code' => '111111',
            'password' => 'whatever-pass',
            'password_confirmation' => 'whatever-pass',
        ])->assertStatus(422);
    }
}
