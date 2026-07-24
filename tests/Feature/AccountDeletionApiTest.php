<?php

namespace Tests\Feature;

use App\Models\BlockedIdentity;
use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The v2 delete-my-account endpoints. Rolls back.
 */
class AccountDeletionApiTest extends TestCase
{
    use DatabaseTransactions;

    private const PASSWORD = 'secret-password';

    private function makeUser(float $balance = 0, float $locked = 0): User
    {
        $user = new User();
        $user->name = 'API Deletion Test';
        $user->email = 'api-del-' . uniqid() . '@example.test';
        $user->phone = '0122' . random_int(1000000, 9999999);
        $user->password = self::PASSWORD;
        $user->type = User::TYPE_CLIENT;
        $user->api_token = Str::random(80);
        $user->save();

        app(WalletService::class)->getOrCreateWallet((int) $user->id)->update([
            'status' => Wallet::STATUS_ACTIVE,
            'balance' => $balance,
            'locked_balance' => $locked,
        ]);

        return $user->fresh();
    }

    public function test_eligibility_tells_the_app_what_to_finish_first(): void
    {
        $user = $this->makeUser(50, 300); // escrow held
        Sanctum::actingAs($user);

        $this->getJson('/api/v2/account/deletion')
            ->assertOk()
            ->assertJsonPath('data.can_delete', false)
            ->assertJsonPath('data.blockers.0.code', 'locked_balance')
            ->assertJsonPath('data.grace_days', (int) config('bim.account_deletion.grace_days'));
    }

    public function test_a_clean_account_is_told_it_can_delete(): void
    {
        Sanctum::actingAs($this->makeUser(10));

        $this->getJson('/api/v2/account/deletion')
            ->assertOk()
            ->assertJsonPath('data.can_delete', true)
            ->assertJsonPath('data.blockers', []);
    }

    public function test_deleting_requires_the_password(): void
    {
        $user = $this->makeUser(10);
        Sanctum::actingAs($user);

        // An unlocked borrowed phone must not be enough to end an account.
        $this->postJson('/api/v2/account/deletion', ['password' => 'wrong-password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->assertNotSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_requesting_deletion_soft_deletes_and_reports_the_restore_deadline(): void
    {
        $user = $this->makeUser(10);
        Sanctum::actingAs($user);

        $this->postJson('/api/v2/account/deletion', ['password' => self::PASSWORD, 'reason' => 'لم أعد أستخدمه'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['deletion_requested_at', 'restorable_until']]);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_a_blocked_account_gets_the_blockers_back_not_just_an_error(): void
    {
        $user = $this->makeUser(10, 200);
        Sanctum::actingAs($user);

        $this->postJson('/api/v2/account/deletion', ['password' => self::PASSWORD])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('blockers.0.code', 'locked_balance');
    }

    public function test_cancelling_restores_the_account_and_returns_a_working_token(): void
    {
        $user = $this->makeUser(75);
        $email = $user->email;
        Sanctum::actingAs($user);
        $this->postJson('/api/v2/account/deletion', ['password' => self::PASSWORD])->assertOk();

        // The tokens are gone, so this cannot be authenticated — it takes
        // credentials, like the login it effectively is.
        $response = $this->postJson('/api/v2/account/deletion/cancel', [
            'email' => $email,
            'password' => self::PASSWORD,
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertNotNull(User::query()->find($user->id), 'the account is back');
        $this->assertSame(75.0, round((float) Wallet::query()->where('user_id', $user->id)->value('balance'), 2));

        $token = $response->json('token');
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v2/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_cancelling_with_a_wrong_password_says_nothing_useful(): void
    {
        $user = $this->makeUser(10);
        $email = $user->email;
        Sanctum::actingAs($user);
        $this->postJson('/api/v2/account/deletion', ['password' => self::PASSWORD])->assertOk();

        $wrongPassword = $this->postJson('/api/v2/account/deletion/cancel', [
            'email' => $email,
            'password' => 'nope',
        ])->assertStatus(422);

        $noSuchAccount = $this->postJson('/api/v2/account/deletion/cancel', [
            'email' => 'nobody-' . uniqid() . '@example.test',
            'password' => 'nope',
        ])->assertStatus(422);

        // Identical answers: otherwise this endpoint tells a stranger which
        // addresses have accounts.
        $this->assertSame(
            $wrongPassword->json('errors.email'),
            $noSuchAccount->json('errors.email')
        );

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_a_deleted_account_cannot_log_in_during_the_grace_window(): void
    {
        $user = $this->makeUser(10);
        $email = $user->email;
        Sanctum::actingAs($user);
        $this->postJson('/api/v2/account/deletion', ['password' => self::PASSWORD])->assertOk();

        $this->postJson('/api/v2/auth/login', ['email' => $email, 'password' => self::PASSWORD])
            ->assertStatus(422);
    }

    public function test_a_banned_account_cannot_log_in(): void
    {
        $user = $this->makeUser(10);
        $user->banned_at = now();
        $user->save();

        $this->postJson('/api/v2/auth/login', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    /**
     * The attack the ban list exists for, end to end.
     *
     * While the banned row is still there, `unique:users,phone` is what turns
     * re-registration away — the ban never gets a say. The hole opens only after
     * anonymization frees the email and the phone, which is precisely when
     * users.email and users.phone can no longer answer "was this one banned?".
     */
    public function test_a_banned_identity_cannot_register_again_even_after_the_account_is_scrubbed(): void
    {
        $banned = $this->makeUser();
        $email = $banned->email;
        $phone = $banned->phone;

        $banned->banned_at = now();
        $banned->ban_reason = 'fake operations';
        $banned->deletion_requested_at = now();
        $banned->deletion_scheduled_at = now()->subDay();
        $banned->save();
        $banned->delete();

        app(\App\Services\AccountDeletionService::class)->finalize($banned);

        // The identity is now free as far as the users table is concerned...
        $this->assertSame(0, User::withTrashed()->where('phone', $phone)->count());

        // ...and still refused.
        $this->postJson('/api/v2/auth/register', [
            'name' => 'Back Again',
            'email' => $email,
            'phone' => $phone,
            'password' => 'Another-password1',
            'password_confirmation' => 'Another-password1',
            'terms_accepted' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        // Including in the Arabic-digit spelling of the same number.
        $this->postJson('/api/v2/auth/register', [
            'name' => 'Back Again',
            'email' => 'totally-new-' . uniqid() . '@example.test',
            'phone' => strtr($phone, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']),
            'password' => 'Another-password1',
            'password_confirmation' => 'Another-password1',
            'terms_accepted' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }
}
