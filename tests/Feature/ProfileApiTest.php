<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
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

    public function test_update_persists_administrative_location_independent_of_gps(): void
    {
        $this->user->forceFill(['country_id' => null, 'governorate_id' => null, 'city_id' => null])->save();

        $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/v2/profile', ['country_id' => 1, 'governorate_id' => 1, 'city_id' => 1])
            ->assertOk()
            ->assertJsonPath('data.country_id', 1)
            ->assertJsonPath('data.governorate_id', 1)
            ->assertJsonPath('data.city_id', 1);

        $fresh = $this->user->fresh();
        $this->assertSame(1, (int) $fresh->country_id);
        $this->assertSame(1, (int) $fresh->governorate_id);
        $this->assertSame(1, (int) $fresh->city_id);
    }

    public function test_locations_nearest_resolves_gps_to_city_governorate_country(): void
    {
        $this->getJson('/api/v2/locations/nearest?lat=30.0443879&lng=31.2357257')
            ->assertOk()
            ->assertJsonPath('data.match.city.id', 1)
            ->assertJsonPath('data.match.governorate.id', 1)
            ->assertJsonPath('data.match.country_id', 1);
    }

    public function test_client_can_self_upgrade_to_business_with_a_specialty(): void
    {
        $this->user->forceFill(['type' => 'client', 'category_child_id' => null])->save();

        $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/v2/profile', ['type' => 'business', 'category_id' => 24, 'category_child_id' => 116])
            ->assertOk()
            ->assertJsonPath('data.type', 'business')
            ->assertJsonPath('data.is_business', true);

        $fresh = $this->user->fresh();
        $this->assertSame('business', (string) $fresh->type);
        $this->assertSame(116, (int) $fresh->category_child_id);
    }

    public function test_upgrading_to_business_without_a_specialty_is_rejected(): void
    {
        $this->user->forceFill(['type' => 'client', 'category_child_id' => null])->save();

        $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/v2/profile', ['type' => 'business'])
            ->assertStatus(422);

        $this->assertSame('client', (string) $this->user->fresh()->type);
    }

    public function test_downgrading_a_business_account_is_not_offered_by_this_endpoint(): void
    {
        $this->user->forceFill(['type' => 'business'])->save();

        $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/v2/profile', ['type' => 'client'])
            ->assertStatus(422);

        $this->assertSame('business', (string) $this->user->fresh()->type);
    }

    private const A_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    public function test_image_upload_stores_a_new_photo_and_deletes_the_old_one(): void
    {
        $this->user->forceFill(['image' => 'files/uploads/does-not-exist-old.png'])->save();

        $file = UploadedFile::fake()->createWithContent('avatar.png', base64_decode(self::A_PNG));

        $response = $this->actingAs($this->user, 'sanctum')
            ->post('/api/v2/profile/image', ['image' => $file])
            ->assertOk();

        $newPath = $response->json('data.image');
        $this->assertNotNull($newPath);
        $this->assertNotSame('files/uploads/does-not-exist-old.png', $newPath);
        $this->assertSame($newPath, (string) $this->user->fresh()->image);

        // Clean up the file this test actually wrote (DB rolls back, disk doesn't).
        @unlink(public_path($newPath));
    }

    public function test_image_remove_clears_the_photo(): void
    {
        $this->user->forceFill(['image' => 'files/uploads/does-not-exist.png'])->save();

        $this->actingAs($this->user, 'sanctum')
            ->post('/api/v2/profile/image', ['remove' => true])
            ->assertOk()
            ->assertJsonPath('data.image', null);

        $this->assertNull($this->user->fresh()->image);
    }

    public function test_image_upload_requires_either_a_file_or_remove(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v2/profile/image', [])
            ->assertStatus(422);
    }
}
