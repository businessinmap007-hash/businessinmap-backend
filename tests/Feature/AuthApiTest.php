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
            'governorate_id' => (int) \Illuminate\Support\Facades\DB::table('cities')->orderBy('id')->value('governorate_id'),
            'city_id' => (int) \Illuminate\Support\Facades\DB::table('cities')->orderBy('id')->value('id'),
            'address_line' => 'شارع الاختبار 1',
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
            'governorate_id' => (int) \Illuminate\Support\Facades\DB::table('cities')->orderBy('id')->value('governorate_id'),
            'city_id' => (int) \Illuminate\Support\Facades\DB::table('cities')->orderBy('id')->value('id'),
            'address_line' => 'شارع الاختبار 1',
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
            'governorate_id' => (int) \Illuminate\Support\Facades\DB::table('cities')->orderBy('id')->value('governorate_id'),
            'city_id' => (int) \Illuminate\Support\Facades\DB::table('cities')->orderBy('id')->value('id'),
            'address_line' => 'شارع الاختبار 1',
            // Both names since 2026-08-12: a shop is searched for, and 35% of
            // them carried a Latin-only name no Arabic search could reach.
            'name' => 'نشاط له تخصص',
            'name_en' => 'Biz With Child',
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

    /** One of the 45 real, health-root-linked «تخصصات طبية» options. */
    private function aSpecialtyOptionId(): int
    {
        return (int) \App\Models\CategoryChild::query()
            ->find(User::DOCTOR_OWN_CLINIC_CHILD_ID)
            ->activeOptionsForParent(null)
            ->where('options.group_id', User::MEDICAL_SPECIALTY_GROUP_ID)
            ->value('options.id');
    }

    public function test_a_doctors_own_clinic_can_register_with_a_title_and_specialty(): void
    {
        $suffix = Str::random(8);
        $specialtyId = $this->aSpecialtyOptionId();

        $res = $this->postJson('/api/v2/auth/register', [
            'governorate_id' => (int) \Illuminate\Support\Facades\DB::table('cities')->orderBy('id')->value('governorate_id'),
            'city_id' => (int) \Illuminate\Support\Facades\DB::table('cities')->orderBy('id')->value('id'),
            'address_line' => 'شارع الاختبار 1',
            'name' => 'عيادة د. تجريبي',
            'name_en' => 'Dr Test Clinic',
            'email' => "doc_{$suffix}@example.com",
            'phone' => '019' . random_int(10_000_000, 99_999_999),
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'terms_accepted' => true,
            'type' => User::TYPE_BUSINESS,
            'category_child_id' => User::DOCTOR_OWN_CLINIC_CHILD_ID,
            'medical_title' => User::MEDICAL_TITLE_CONSULTANT,
            'specialty_option_ids' => [$specialtyId],
        ])->assertCreated();

        $this->assertSame(User::MEDICAL_TITLE_CONSULTANT, $res->json('data.medical_title'));
        $this->assertStringStartsWith(User::MEDICAL_TITLE_CONSULTANT . ' ', $res->json('data.display_name'));

        $userId = (int) $res->json('data.id');
        $this->assertDatabaseHas('option_user', ['user_id' => $userId, 'option_id' => $specialtyId]);
    }

    public function test_a_title_is_refused_for_a_non_clinic_business(): void
    {
        $childId = (int) \App\Models\CategoryChild::query()
            ->where('id', '!=', User::DOCTOR_OWN_CLINIC_CHILD_ID)->orderBy('id')->value('id');
        $suffix = Str::random(8);

        $this->postJson('/api/v2/auth/register', [
            'governorate_id' => (int) \Illuminate\Support\Facades\DB::table('cities')->orderBy('id')->value('governorate_id'),
            'city_id' => (int) \Illuminate\Support\Facades\DB::table('cities')->orderBy('id')->value('id'),
            'address_line' => 'شارع الاختبار 1',
            'name' => 'محل تجريبي',
            'name_en' => 'Test Shop',
            'email' => "shop_{$suffix}@example.com",
            'phone' => '019' . random_int(10_000_000, 99_999_999),
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'terms_accepted' => true,
            'type' => User::TYPE_BUSINESS,
            'category_child_id' => $childId,
            'medical_title' => User::MEDICAL_TITLE_DOCTOR,
        ])->assertStatus(422)->assertJsonValidationErrors('medical_title');
    }

    public function test_a_fabricated_specialty_option_id_is_rejected(): void
    {
        $suffix = Str::random(8);

        $this->postJson('/api/v2/auth/register', [
            'governorate_id' => (int) \Illuminate\Support\Facades\DB::table('cities')->orderBy('id')->value('governorate_id'),
            'city_id' => (int) \Illuminate\Support\Facades\DB::table('cities')->orderBy('id')->value('id'),
            'address_line' => 'شارع الاختبار 1',
            'name' => 'عيادة د. تجريبي 2',
            'name_en' => 'Dr Test Clinic 2',
            'email' => "doc2_{$suffix}@example.com",
            'phone' => '019' . random_int(10_000_000, 99_999_999),
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'terms_accepted' => true,
            'type' => User::TYPE_BUSINESS,
            'category_child_id' => User::DOCTOR_OWN_CLINIC_CHILD_ID,
            'medical_title' => User::MEDICAL_TITLE_DOCTOR,
            'specialty_option_ids' => [999999999],
        ])->assertStatus(422);
    }
}
