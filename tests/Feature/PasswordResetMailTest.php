<?php

namespace Tests\Feature;

use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * #5 mail wiring: the forgot flow actually dispatches the password-reset
 * Mailable to the account, and the emailed code matches the stored (hashed)
 * one. Rolls back.
 */
class PasswordResetMailTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->user = User::query()->whereNotNull('email')->orderBy('id')->firstOrFail();
        DB::table('password_reset_codes')->where('email', $this->user->email)->delete();
    }

    public function test_forgot_sends_the_reset_code_mailable_to_the_account(): void
    {
        $this->postJson('/api/v2/auth/password/forgot', ['email' => $this->user->email])->assertOk();

        Mail::assertSent(PasswordResetCodeMail::class, fn (PasswordResetCodeMail $m) => $m->hasTo($this->user->email));
    }

    public function test_emailed_code_matches_the_stored_hash_and_renders(): void
    {
        $this->postJson('/api/v2/auth/password/forgot', ['email' => $this->user->email])->assertOk();

        $captured = null;
        Mail::assertSent(PasswordResetCodeMail::class, function (PasswordResetCodeMail $m) use (&$captured) {
            $captured = $m;
            return true;
        });

        $this->assertNotNull($captured);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $captured->code);

        // The emailed plaintext code verifies against the stored hash.
        $row = DB::table('password_reset_codes')->where('email', $this->user->email)->first();
        $this->assertNotNull($row);
        $this->assertTrue(Hash::check($captured->code, $row->code_hash));

        // And the code is actually rendered into the email body.
        $this->assertStringContainsString($captured->code, $captured->render());
    }

    public function test_forgot_for_unknown_email_sends_nothing(): void
    {
        $this->postJson('/api/v2/auth/password/forgot', ['email' => 'ghost_' . uniqid() . '@example.com'])->assertOk();

        Mail::assertNothingSent();
    }
}
