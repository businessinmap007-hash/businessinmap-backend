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
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'terms_accepted' => true,
        ])->assertCreated();

        $this->assertNotEmpty($res->json('token'));
        $this->assertSame("t_{$suffix}@example.com", $res->json('data.email'));
        $this->assertDatabaseHas('users', ['email' => "t_{$suffix}@example.com"]);
    }

    public function test_business_register_requires_a_category_child(): void
    {
        $suffix = Str::random(8);

        // The business path must pick its category_child (the service catalog
        // key). Omitting it is a 422, not a half-built merchant account.
        $this->postJson('/api/v2/auth/register', [
            'name' => 'Biz No Child',
            'email' => "b_{$suffix}@example.com",
            'phone' => '019' . random_int(10_000_000, 99_999_999),
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'terms_accepted' => true,
            'type' => User::TYPE_BUSINESS,
        ])->assertStatus(422)->assertJsonValidationErrors('category_child_id');
    }

    public function test_business_register_succeeds_with_a_valid_category_child(): void
    {
        $childId = (int) \App\Models\CategoryChild::query()->orderBy('id')->value('id');
        $suffix = Str::random(8);

        $res = $this->postJson('/api/v2/auth/register', [
            'name' => 'Biz With Child',
            'email' => "b_{$suffix}@example.com",
            'phone' => '019' . random_int(10_000_000, 99_999_999),
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'terms_accepted' => true,
            'type' => User::TYPE_BUSINESS,
            'category_child_id' => $childId,
        ])->assertCreated();

        $this->assertSame(User::TYPE_BUSINESS, $res->json('data.type'));
        $this->assertDatabaseHas('users', [
            'email' => "b_{$suffix}@example.com",
            'type' => User::TYPE_BUSINESS,
            'category_child_id' => $childId,
        ]);
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
