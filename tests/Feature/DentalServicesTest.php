<?php

namespace Tests\Feature;

use App\Models\DentalService;
use App\Models\Option;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «أنشئ قائمة خدمات عيادات الأسنان» — المالك، 2026-10-13: the list belongs to a clinic that ticked «أسنان», and to nobody
 * else — a beauty centre, a hospital or a skin clinic never sees it.
 *
 * Rolls back.
 */
class DentalServicesTest extends TestCase
{
    use DatabaseTransactions;

    private const HEALTH = 20;
    private const CLINIC = 514;
    private const HOSPITAL = 513;

    private function user(string $type, ?int $child = null, string $tag = 'U'): User
    {
        $u = new User();
        $u->forceFill([
            'name' => $tag . ' ' . Str::random(4), 'type' => $type, 'email' => strtolower($tag) . '-' . uniqid() . '@example.test',
            'phone' => '010' . random_int(10000000, 99999999), 'password' => 'secret-password', 'api_token' => Str::random(60),
            'category_id' => $child ? self::HEALTH : null, 'category_child_id' => $child,
        ])->save();

        return $u;
    }

    /** A single clinic; with the «أسنان» specialty ticked it is a dental clinic. */
    private function clinic(bool $dental = true, int $child = self::CLINIC): User
    {
        $u = $this->user(User::TYPE_BUSINESS, $child, 'Clinic');

        if ($dental) {
            DB::table('option_user')->insert(['user_id' => $u->id, 'option_id' => $this->specialty('أسنان')]);
        }

        return $u;
    }

    private function specialty(string $name): int
    {
        $id = (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', 'تخصصات طبية')->where('o.name_ar', $name)->value('o.id');
        $this->assertGreaterThan(0, $id, $name);

        return $id;
    }

    private function platform(string $name): int
    {
        $id = (int) DentalService::query()->whereNull('owner_id')->where('name_ar', $name)->value('id');
        $this->assertGreaterThan(0, $id, $name);

        return $id;
    }

    public function test_the_platform_list_covers_every_kind_and_the_common_services(): void
    {
        $kinds = DentalService::query()->whereNull('owner_id')->distinct()->pluck('kind')->all();
        $this->assertEqualsCanonicalizing(DentalService::KINDS, $kinds);

        foreach (['تنظيف الأسنان وإزالة الجير', 'تبييض الأسنان بالعيادة', 'علاج عصب ضرس', 'زراعة سن', 'تقويم معدني', 'خلع سن عادي', 'تاج زيركون'] as $name) {
            $this->platform($name);
        }

        // no half-filled rows: every service carries both languages
        $this->assertSame(0, DentalService::query()->whereNull('owner_id')->where(fn ($q) => $q->whereNull('name_en')->orWhere('name_en', ''))->count());
    }

    public function test_teeth_cleaning_and_whitening_are_a_dentists_alone(): void
    {
        $dental = DentalService::query()->whereNull('owner_id')->pluck('name_ar')->all();
        $this->assertContains('تنظيف الأسنان وإزالة الجير', $dental);

        // …and the beauty centre's own line group (the owner's ruling of 2026-10-12) carries none of them
        $beauty = DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', 'خدمات الكوافير والتجميل')->pluck('o.name_ar')->all();

        foreach ($beauty as $name) {
            $this->assertStringNotContainsString('أسنان', $name);
        }
    }

    public function test_a_dental_clinic_offers_services_with_its_own_price_and_a_patient_reads_them(): void
    {
        $clinic = $this->clinic();
        $cleaning = $this->platform('تنظيف الأسنان وإزالة الجير');
        $implant = $this->platform('زراعة سن');

        Sanctum::actingAs($clinic);
        $this->putJson('/api/v2/business/dental-services', ['items' => [
            ['service_id' => $cleaning, 'offered' => true, 'price' => 400],
            ['service_id' => $implant, 'offered' => true],
        ]])->assertOk();

        $kinds = collect($this->getJson('/api/v2/business/dental-services')->assertOk()->json('data.kinds'));
        $row = collect($kinds->firstWhere('kind', 'preventive')['services'])->firstWhere('id', $cleaning);
        $this->assertTrue($row['offered']);
        $this->assertEquals(400, $row['price']);

        Sanctum::actingAs($this->user(User::TYPE_CLIENT, null, 'Patient'));
        $seen = collect($this->getJson("/api/v2/clinics/{$clinic->id}/dental-services")->assertOk()->json('data.kinds'));

        $this->assertEqualsCanonicalizing(['preventive', 'surgical'], $seen->pluck('kind')->all(), 'only what it offers');
        $this->assertNull(collect($seen->firstWhere('kind', 'surgical')['services'])->first()['price'], 'no price: after the exam');
    }

    public function test_a_clinic_without_the_dental_specialty_has_no_list_and_no_door(): void
    {
        $skin = $this->clinic(false);

        Sanctum::actingAs($skin);
        $this->getJson('/api/v2/business/dental-services')->assertForbidden();
        $this->putJson('/api/v2/business/dental-services', ['items' => [['service_id' => $this->platform('زراعة سن'), 'offered' => true]]])->assertForbidden();

        // ticking another specialty does not make it one
        DB::table('option_user')->insert(['user_id' => $skin->id, 'option_id' => $this->specialty('جلديه وتناسليه')]);
        $this->getJson('/api/v2/business/dental-services')->assertForbidden();

        Sanctum::actingAs($this->user(User::TYPE_CLIENT, null, 'Patient'));
        $this->getJson("/api/v2/clinics/{$skin->id}/dental-services")->assertNotFound();
    }

    public function test_a_hospital_and_a_beauty_centre_are_not_dental_clinics_even_with_the_option(): void
    {
        $hospital = $this->clinic(true, self::HOSPITAL);
        $beauty = $this->user(User::TYPE_BUSINESS, 554, 'Beauty');
        DB::table('option_user')->insert(['user_id' => $beauty->id, 'option_id' => $this->specialty('أسنان')]);

        foreach ([$hospital, $beauty] as $account) {
            Sanctum::actingAs($account);
            $this->getJson('/api/v2/business/dental-services')->assertForbidden();
        }

        Sanctum::actingAs($this->user(User::TYPE_CLIENT, null, 'Patient'));
        $this->getJson("/api/v2/clinics/{$beauty->id}/dental-services")->assertNotFound();
        $this->getJson("/api/v2/clinics/{$hospital->id}/dental-services")->assertNotFound();
    }

    public function test_a_clinic_adds_its_own_service_and_no_other_clinic_sees_it(): void
    {
        $clinic = $this->clinic();
        $other = $this->clinic();

        Sanctum::actingAs($clinic);
        $this->postJson('/api/v2/business/dental-services/custom', ['kind' => 'cosmetic', 'name' => 'جلسة ليزر للثة'])->assertCreated();

        $mine = collect($this->getJson('/api/v2/business/dental-services')->json('data.kinds'))->firstWhere('kind', 'cosmetic')['services'];
        $own = collect($mine)->firstWhere('name', 'جلسة ليزر للثة');
        $this->assertTrue($own['own']);
        $this->assertTrue($own['offered'], 'offered at once');

        // a duplicate is refused
        $this->postJson('/api/v2/business/dental-services/custom', ['kind' => 'cosmetic', 'name' => 'جلسة ليزر للثة'])->assertStatus(422);

        Sanctum::actingAs($other);
        $theirs = collect($this->getJson('/api/v2/business/dental-services')->json('data.kinds'))->firstWhere('kind', 'cosmetic')['services'];
        $this->assertNull(collect($theirs)->firstWhere('name', 'جلسة ليزر للثة'));

        // …and cannot offer or delete it
        $this->putJson('/api/v2/business/dental-services', ['items' => [['service_id' => $own['id'], 'offered' => true]]])->assertStatus(422);
        $this->deleteJson("/api/v2/business/dental-services/custom/{$own['id']}")->assertNotFound();

        Sanctum::actingAs($clinic);
        $this->deleteJson("/api/v2/business/dental-services/custom/{$own['id']}")->assertOk();
        $this->assertSame(0, DentalService::query()->where('name_ar', 'جلسة ليزر للثة')->count());
    }

    public function test_taking_a_service_off_the_list_and_the_price_rules(): void
    {
        $clinic = $this->clinic();
        $braces = $this->platform('تقويم معدني');

        Sanctum::actingAs($clinic);
        $this->putJson('/api/v2/business/dental-services', ['items' => [['service_id' => $braces, 'offered' => true, 'price' => 6500]]])->assertOk();
        $this->putJson('/api/v2/business/dental-services', ['items' => [['service_id' => $braces, 'offered' => true, 'price' => 0]]])->assertOk();

        $row = collect(collect($this->getJson('/api/v2/business/dental-services')->json('data.kinds'))->firstWhere('kind', 'orthodontic')['services'])->firstWhere('id', $braces);
        $this->assertNull($row['price'], 'zero means after the exam, never a free service');

        $this->putJson('/api/v2/business/dental-services', ['items' => [['service_id' => $braces, 'offered' => true, 'price' => -5]]])->assertStatus(422);
        $this->putJson('/api/v2/business/dental-services', ['items' => [['service_id' => $braces, 'offered' => false]]])->assertOk();

        Sanctum::actingAs($this->user(User::TYPE_CLIENT, null, 'Patient'));
        $this->assertSame([], $this->getJson("/api/v2/clinics/{$clinic->id}/dental-services")->assertOk()->json('data.kinds'));
    }

    public function test_the_page_and_the_account_say_whether_the_clinic_has_a_dental_list(): void
    {
        $clinic = $this->clinic();
        $skin = $this->clinic(false);

        Sanctum::actingAs($clinic);
        $this->getJson('/api/v2/auth/me')->assertOk()->assertJsonPath('data.dental_clinic', true);

        // listed nothing yet: no tab
        $page = $this->getJson("/api/v2/businesses/{$clinic->id}")->assertOk();
        $this->assertFalse((bool) $page->json('data.sections.dental'));

        $this->putJson('/api/v2/business/dental-services', ['items' => [['service_id' => $this->platform('حشو تجميلي (كومبوزيت)'), 'offered' => true, 'price' => 300]]])->assertOk();
        $this->assertTrue((bool) $this->getJson("/api/v2/businesses/{$clinic->id}")->json('data.sections.dental'));

        Sanctum::actingAs($skin);
        $this->getJson('/api/v2/auth/me')->assertOk()->assertJsonPath('data.dental_clinic', false);
        $this->assertFalse((bool) $this->getJson("/api/v2/businesses/{$skin->id}")->json('data.sections.dental'));
    }

    public function test_the_seeder_is_add_only_and_idempotent(): void
    {
        $before = DentalService::query()->whereNull('owner_id')->count();

        // a clinic's own entry and a hand-renamed platform row both survive a re-run
        $clinic = $this->clinic();
        DentalService::create(['kind' => 'cosmetic', 'name_ar' => 'خدمة خاصة', 'owner_id' => $clinic->id, 'sort_order' => 999]);
        DentalService::query()->whereNull('owner_id')->where('name_ar', 'زراعة سن')->update(['name_en' => 'Hand-edited']);

        $this->seed(\Database\Seeders\DentalServicesSeeder::class);

        $this->assertSame($before, DentalService::query()->whereNull('owner_id')->count());
        $this->assertSame(1, DentalService::query()->where('name_ar', 'خدمة خاصة')->count());
        $this->assertSame('Hand-edited', DentalService::query()->whereNull('owner_id')->where('name_ar', 'زراعة سن')->value('name_en'));
    }
}
