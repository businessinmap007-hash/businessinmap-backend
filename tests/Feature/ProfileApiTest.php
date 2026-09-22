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

    public function test_update_persists_social_links(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/v2/profile', ['facebook' => 'fb.com/bim', 'instagram' => 'instagram.com/bim'])
            ->assertOk()
            ->assertJsonPath('data.social.facebook', 'fb.com/bim')
            ->assertJsonPath('data.social.instagram', 'instagram.com/bim')
            ->assertJsonPath('data.social.twitter', null);

        $this->assertSame('fb.com/bim', $this->user->fresh()->social->facebook);
    }

    public function test_a_blank_social_link_clears_only_that_one(): void
    {
        $this->user->social()->create(['facebook' => 'fb.com/bim', 'instagram' => 'instagram.com/bim']);

        $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/v2/profile', ['facebook' => ''])
            ->assertOk()
            ->assertJsonPath('data.social.facebook', null)
            ->assertJsonPath('data.social.instagram', 'instagram.com/bim');
    }

    public function test_social_is_null_when_never_set(): void
    {
        $this->user->social()->delete();

        $this->actingAs($this->user, 'sanctum')->getJson('/api/v2/profile')
            ->assertOk()
            ->assertJsonPath('data.social', null);
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

    public function test_a_non_egypt_country_id_is_refused(): void
    {
        $foreignCountryId = (int) \App\Models\Country::query()->where('iso2', '!=', 'EG')->value('id');
        $this->assertGreaterThan(0, $foreignCountryId, 'the seed data must carry at least one non-Egypt country for this test to mean anything');

        $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/v2/profile', ['country_id' => $foreignCountryId])
            ->assertStatus(422)
            ->assertJsonValidationErrors('country_id');
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
        $this->user->forceFill(['type' => 'client', 'image' => 'files/uploads/does-not-exist-old.png'])->save();

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
        $this->user->forceFill(['type' => 'client', 'image' => 'files/uploads/does-not-exist.png'])->save();

        $this->actingAs($this->user, 'sanctum')
            ->post('/api/v2/profile/image', ['remove' => true])
            ->assertOk()
            ->assertJsonPath('data.image', null);

        $this->assertNull($this->user->fresh()->image);
    }

    /**
     * A business has no self-service `logo` upload of its own on the mobile
     * side — this endpoint IS that upload for a business, so it has to land
     * on `logo` (what every other resource in the app reads as "this
     * account's photo"), not the separate `image` column a client uses.
     */
    public function test_image_upload_targets_logo_for_a_business_account(): void
    {
        $this->user->forceFill([
            'type' => 'business',
            'logo' => 'files/uploads/does-not-exist-old-logo.png',
            'image' => 'files/uploads/unrelated-image-slot.png',
        ])->save();

        $file = UploadedFile::fake()->createWithContent('logo.png', base64_decode(self::A_PNG));

        $response = $this->actingAs($this->user, 'sanctum')
            ->post('/api/v2/profile/image', ['image' => $file])
            ->assertOk();

        $newPath = $response->json('data.logo');
        $this->assertNotNull($newPath);
        $this->assertNotSame('files/uploads/does-not-exist-old-logo.png', $newPath);
        $this->assertSame($newPath, (string) $this->user->fresh()->logo);
        // The unrelated `image` slot is left untouched.
        $this->assertSame('files/uploads/unrelated-image-slot.png', (string) $this->user->fresh()->image);

        @unlink(public_path($newPath));
    }

    public function test_image_remove_clears_the_logo_for_a_business_account(): void
    {
        $this->user->forceFill(['type' => 'business', 'logo' => 'files/uploads/does-not-exist.png'])->save();

        $this->actingAs($this->user, 'sanctum')
            ->post('/api/v2/profile/image', ['remove' => true])
            ->assertOk()
            ->assertJsonPath('data.logo', null);

        $this->assertNull($this->user->fresh()->logo);
    }

    public function test_image_upload_requires_either_a_file_or_remove(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v2/profile/image', [])
            ->assertStatus(422);
    }

    public function test_a_clinic_owner_can_set_their_own_medical_title(): void
    {
        $this->user->forceFill(['type' => 'business', 'category_child_id' => User::DOCTOR_OWN_CLINIC_CHILD_ID])->save();

        $res = $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/v2/profile', ['medical_title' => User::MEDICAL_TITLE_PROFESSOR])
            ->assertOk()
            ->assertJsonPath('data.medical_title', User::MEDICAL_TITLE_PROFESSOR);

        $this->assertStringStartsWith(User::MEDICAL_TITLE_PROFESSOR . ' ', (string) $res->json('data.display_name'));
        $this->assertSame(User::MEDICAL_TITLE_PROFESSOR, (string) $this->user->fresh()->medical_title);
    }

    public function test_a_medical_title_is_refused_off_a_non_clinic_account(): void
    {
        $this->user->forceFill(['type' => 'business', 'category_child_id' => 116])->save();

        $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/v2/profile', ['medical_title' => User::MEDICAL_TITLE_DOCTOR])
            ->assertStatus(422)
            ->assertJsonValidationErrors('medical_title');

        $this->assertNull($this->user->fresh()->medical_title);
    }

    /** One of the real, health-root-linked «تخصصات طبية» options. */
    private function aSpecialtyOptionId(): int
    {
        return (int) \App\Models\CategoryChild::query()
            ->find(User::DOCTOR_OWN_CLINIC_CHILD_ID)
            ->activeOptionsForParent(null)
            ->where('options.group_id', User::MEDICAL_SPECIALTY_GROUP_ID)
            ->value('options.id');
    }

    public function test_a_clinic_owner_can_set_and_read_back_their_specialty(): void
    {
        $this->user->forceFill(['type' => 'business', 'category_child_id' => User::DOCTOR_OWN_CLINIC_CHILD_ID])->save();
        $specialtyId = $this->aSpecialtyOptionId();

        $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/v2/profile/specialties', ['specialty_option_ids' => [$specialtyId]])
            ->assertOk()
            ->assertJsonPath('data.selected_ids.0', $specialtyId);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v2/profile/specialties')
            ->assertOk()
            ->assertJsonPath('data.selected_ids.0', $specialtyId);

        $this->assertDatabaseHas('option_user', ['user_id' => $this->user->id, 'option_id' => $specialtyId]);
    }

    public function test_specialties_are_refused_off_a_non_clinic_account(): void
    {
        $this->user->forceFill(['type' => 'business', 'category_child_id' => 116])->save();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v2/profile/specialties')
            ->assertStatus(403);
    }

    public function test_a_fabricated_specialty_id_is_rejected_on_update(): void
    {
        $this->user->forceFill(['type' => 'business', 'category_child_id' => User::DOCTOR_OWN_CLINIC_CHILD_ID])->save();

        $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/v2/profile/specialties', ['specialty_option_ids' => [999999999]])
            ->assertStatus(422);
    }

    public function test_updating_specialties_does_not_disturb_the_businesss_other_attribute_picks(): void
    {
        $this->user->forceFill(['type' => 'business', 'category_child_id' => User::DOCTOR_OWN_CLINIC_CHILD_ID])->save();
        $specialtyId = $this->aSpecialtyOptionId();

        $otherOptionId = (int) \App\Models\CategoryChild::query()
            ->find(User::DOCTOR_OWN_CLINIC_CHILD_ID)
            ->activeOptionsForParent(null)
            ->where('options.group_id', '!=', User::MEDICAL_SPECIALTY_GROUP_ID)
            ->join('option_groups as og', 'og.id', '=', 'options.group_id')
            ->where('og.price_role', '!=', \App\Models\OptionGroup::ROLE_LINE)
            ->value('options.id');
        $this->assertNotNull($otherOptionId, 'the clinic child must carry at least one non-specialty attribute for this test to mean anything');

        \Illuminate\Support\Facades\DB::table('option_user')->insert(['user_id' => $this->user->id, 'option_id' => $otherOptionId]);

        $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/v2/profile/specialties', ['specialty_option_ids' => [$specialtyId]])
            ->assertOk();

        $this->assertDatabaseHas('option_user', ['user_id' => $this->user->id, 'option_id' => $otherOptionId]);
    }
}
