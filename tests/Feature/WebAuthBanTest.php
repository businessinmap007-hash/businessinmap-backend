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

    public function test_default_scaffold_login_also_refuses_a_banned_account(): void
    {
        // The framework's Auth::routes() /login must honour the ban too, not just
        // the custom /user/auth/login.
        $user = $this->throwawayUser();
        app(BanService::class)->ban($user, 'احتيال', $this->adminId());

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password']);

        $this->assertGuest();
    }

    public function test_default_register_route_is_disabled(): void
    {
        // Auth::routes(['register' => false]) — the scaffold register bypassed the
        // ban check + business category logic, so it must not exist.
        $this->post('/register', [
            'name' => 'Scaffold', 'email' => 'scaffold-' . uniqid() . '@example.test',
            'password' => 'x', 'password_confirmation' => 'x',
        ])->assertNotFound();
    }

    public function test_insecure_web_password_reset_routes_are_gone(): void
    {
        // The takeover-prone legacy web reset was removed; the secure flow is the API.
        $this->post('/forgot/password', ['phone' => '0100000000'])->assertNotFound();
        $this->post('/check/reset/code', ['activation_code' => '1234'])->assertNotFound();
        $this->post('/reset/password', ['password' => 'x'])->assertNotFound();
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
            'password' => 'Another-password1',
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
            'password' => 'A-good-password1',
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

    public function test_register_screen_renders_both_paths(): void
    {
        $this->get('/user/register')
            ->assertOk()
            ->assertSee('حساب مستخدم')
            ->assertSee('حساب بزنس')
            ->assertSee('اختر القطاع');
    }

    public function test_login_screen_renders(): void
    {
        // The login page shares the (now-hardened) legacy chrome and hosts the
        // forgot-password modal; it must render without a missing-route fatal.
        $this->get('/user/login')->assertOk();
    }

    public function test_login_screen_wires_the_forgot_password_modal(): void
    {
        // The "forgot password" link must open the modal that drives the secure
        // Api\V2\PasswordResetController flow (email → code → new password), not
        // the retired takeover-prone web reset.
        $this->get('/user/login')
            ->assertOk()
            ->assertSee('forgotPasswordModal')
            ->assertSee('data-target="#forgotPasswordModal"', false)
            ->assertSee('/api/v2/auth/password', false);
    }

    public function test_web_business_signup_stores_business_type_and_child(): void
    {
        $childId = (int) \App\Models\CategoryChild::query()->orderBy('id')->value('id');
        $email = 'bizweb-' . uniqid() . '@example.test';

        $this->postJson('/user/signup', [
            'first_name' => 'Biz', 'last_name' => 'Web',
            'email' => $email, 'phone' => '0100' . random_int(1000000, 9999999),
            'password' => 'A-good-password1',
            'auth' => 'business',
            'category_child_id' => $childId,
        ])->assertJsonPath('status', 200);

        $user = User::query()->where('email', $email)->firstOrFail();
        $this->assertTrue($user->isBusiness(), 'the account must count as a business (type=business)');
        $this->assertSame($childId, (int) $user->category_child_id);
    }

    public function test_web_business_signup_requires_a_category_child(): void
    {
        $res = $this->postJson('/user/signup', [
            'first_name' => 'Biz', 'last_name' => 'NoChild',
            'email' => 'nochild-' . uniqid() . '@example.test',
            'phone' => '0100' . random_int(1000000, 9999999),
            'password' => 'A-good-password1',
            'auth' => 'business',
        ]);

        $res->assertStatus(422);
    }
}
