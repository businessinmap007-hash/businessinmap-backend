<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * v2 profile: read, edit basic fields, and change password (with current-password
 * check + token revocation). Rolls back.
 */
class ProfileApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::query()->orderBy('id')->firstOrFail();
    }

    public function test_show_returns_own_account(): void
    {
        $this->actingAs($this->user, 'sanctum')->getJson('/api/v2/profile')
            ->assertOk()
            ->assertJsonPath('data.id', (int) $this->user->id);
    }

    public function test_update_persists_basic_fields(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/v2/profile', ['name' => 'Renamed Person', 'about' => 'hello world'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed Person');

        $this->assertSame('Renamed Person', (string) $this->user->fresh()->name);
    }

    public function test_password_change_requires_correct_current_password(): void
    {
        $this->user->forceFill(['password' => Hash::make('old-pass')])->save();

        // Wrong current password → rejected.
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v2/profile/password', [
                'current_password' => 'nope',
                'password' => 'New-pass-12',
                'password_confirmation' => 'New-pass-12',
            ])->assertStatus(422);

        // Correct current password → changed.
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v2/profile/password', [
                'current_password' => 'old-pass',
                'password' => 'New-pass-12',
                'password_confirmation' => 'New-pass-12',
            ])->assertOk();

        $this->assertTrue(Hash::check('New-pass-12', $this->user->fresh()->password));
    }

    public function test_profile_requires_auth(): void
    {
        $this->getJson('/api/v2/profile')->assertUnauthorized();
    }
}
