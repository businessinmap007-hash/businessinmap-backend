<?php

namespace Tests\Feature;

use App\Models\BlockedIdentity;
use App\Models\User;
use App\Services\BanService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A ban must hold on every entry point, not just the API. The mobile API
 * (Api\V2\AuthController) already refused a banned login and a blocked
 * re-register, but the legacy web forms (LoginController / RegistrationController,
 * still routed at user/auth/login + user/signup) checked neither — a banned
 * account could sign straight back in, or delete and re-register with the same
 * identity, through the web. These pin the two web guards that close that.
 */
class WebAuthBanTest extends TestCase
{
    use DatabaseTransactions;

    private function throwawayUser(): User
    {
        $u = new User();
        $u->name = 'Web Ban Target';
        $u->email = 'webban-' . uniqid() . '@example.test';
        $u->phone = '0100' . random_int(1000000, 9999999);
        $u->password = 'secret-password';       // hashed by the model mutator
        $u->type = User::TYPE_CLIENT;
        $u->api_token = Str::random(80);
        $u->save();

        return $u;
    }

    private function adminId(): int
    {
        return (int) User::query()->where('type', 'admin')->value('id')
            ?: (int) User::query()->orderBy('id')->value('id');
    }

    public function test_web_login_refuses_a_banned_account(): void
    {
        $user = $this->throwawayUser();
        app(BanService::class)->ban($user, 'احتيال', $this->adminId());

        $res = $this->postJson('/user/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        // Credentials are correct, so the only thing that can stop it is the ban.
        $res->assertJsonPath('status', 400);
        $this->assertGuest();
    }

    public function test_web_signup_refuses_a_blocked_identity(): void
    {
        // Ban records the identity on the hashed block list, then the account is
        // hard-removed so the users-table unique rules pass and the block guard
        // is what has to catch the re-register.
        $user = $this->throwawayUser();
        $email = $user->email;
        $phone = $user->phone;
        app(BanService::class)->ban($user, 'احتيال', $this->adminId());
        $user->forceDelete();

        $this->assertTrue(BlockedIdentity::isBlocked($email, $phone));

        $res = $this->postJson('/user/signup', [
            'first_name' => 'Re',
            'last_name' => 'Register',
            'email' => $email,
            'phone' => $phone,
            'password' => 'another-password',
        ]);

        $res->assertJsonPath('status', 400);
        $this->assertDatabaseMissing('users', ['email' => $email]);
    }

    public function test_web_signup_ignores_injected_privileged_fields(): void
    {
        // The signup form must whitelist its inputs. User::$fillable contains
        // privileged columns (balance, the consent/commercial flags, …); a
        // crafted POST that carries them must NOT be able to self-fund a wallet
        // or self-grant consent — those values have to be dropped on the floor.
        $email = 'inject-' . uniqid() . '@example.test';
        $phone = '0100' . random_int(1000000, 9999999);

        $this->postJson('/user/signup', [
            'first_name' => 'Mass',
            'last_name' => 'Assign',
            'name' => 'Mass Assign',
            'email' => $email,
            'phone' => $phone,
            'password' => 'a-good-password',
            // Injected — every one of these is fillable and must be ignored here.
            'balance' => 999999,
            'guarantee_enabled' => 1,
            'rating_enabled' => 1,
            'commercial_operations_enabled' => 1,
            'type' => 'admin',
        ]);

        $user = User::query()->where('email', $email)->firstOrFail();

        $this->assertSame('0.00', (string) $user->balance, 'balance must not be self-set at signup');
        $this->assertFalse((bool) $user->guarantee_enabled);
        $this->assertFalse((bool) $user->rating_enabled);
        $this->assertFalse((bool) $user->commercial_operations_enabled);
        $this->assertSame(User::TYPE_CLIENT, $user->type, 'type must be server-decided, not injected');
    }
}
