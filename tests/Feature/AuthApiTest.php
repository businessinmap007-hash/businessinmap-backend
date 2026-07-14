<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * v2 auth: register / login / me / logout over Sanctum bearer tokens. Rolls back.
 */
class AuthApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_register_issues_a_token(): void
    {
        $suffix = Str::random(8);

        $res = $this->postJson('/api/v2/auth/register', [
            'name' => 'Test User',
            'email' => "t_{$suffix}@example.com",
            'phone' => '019' . random_int(10_000_000, 99_999_999),
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertCreated();

        $this->assertNotEmpty($res->json('token'));
        $this->assertSame("t_{$suffix}@example.com", $res->json('data.email'));
        $this->assertDatabaseHas('users', ['email' => "t_{$suffix}@example.com"]);
    }

    public function test_login_succeeds_with_correct_password_and_fails_otherwise(): void
    {
        $user = User::query()->orderBy('id')->firstOrFail();
        $user->forceFill(['password' => Hash::make('right-pass')])->save();

        $this->postJson('/api/v2/auth/login', ['email' => $user->email, 'password' => 'right-pass'])
            ->assertOk()
            ->assertJsonPath('data.id', (int) $user->id)
            ->assertJsonStructure(['token']);

        $this->postJson('/api/v2/auth/login', ['email' => $user->email, 'password' => 'wrong-pass'])
            ->assertStatus(422);
    }

    public function test_me_requires_a_token(): void
    {
        $user = User::query()->orderBy('id')->firstOrFail();

        $this->getJson('/api/v2/auth/me')->assertUnauthorized();

        $this->actingAs($user, 'sanctum')->getJson('/api/v2/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', (int) $user->id);
    }

    public function test_logout_all_revokes_tokens(): void
    {
        $user = User::query()->orderBy('id')->firstOrFail();
        $user->createToken('a');
        $user->createToken('b');

        $this->actingAs($user, 'sanctum')->postJson('/api/v2/auth/logout-all')->assertOk();

        $this->assertSame(0, (int) $user->tokens()->count());
    }
}
