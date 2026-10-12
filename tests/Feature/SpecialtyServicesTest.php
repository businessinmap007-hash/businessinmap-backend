<?php

namespace Tests\Feature;

use App\Models\ClinicAppointment;
use App\Models\ClinicAppointmentSlot;
use App\Models\SpecialtyService;
use App\Models\User;
use Database\Seeders\SpecialtyServicesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «خدمات التخصص» — المالك، 2026-10-13 (الأسنان) و2026-10-14 (الجلدية والتجميل): a clinic that ticked a specialty has that
 * specialty's list, and nobody else does — a beauty centre, a hospital or a clinic of another specialty never sees it.
 *
 * Rolls back.
 */
class SpecialtyServicesTest extends TestCase
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

    /** A single clinic (or another child) with the given specialties ticked. */
    private function clinic(array $specialties = ['أسنان'], int $child = self::CLINIC): User
    {
        $u = $this->user(User::TYPE_BUSINESS, $child, 'Clinic');

        foreach ($specialties as $name) {
            DB::table('option_user')->insert(['user_id' => $u->id, 'option_id' => $this->specialty($name)]);
        }

        return $u;
    }

    private function dentist(): User
    {
        return $this->clinic(['أسنان']);
    }

    private function skin(): User
    {
        return $this->clinic(['جلديه وتناسليه']);
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
        $id = (int) SpecialtyService::query()->whereNull('owner_id')->where('name_ar', $name)->value('id');
        $this->assertGreaterThan(0, $id, $name);

        return $id;
    }

    private function patient(): User
    {
        return $this->user(User::TYPE_CLIENT, null, 'Patient');
    }

    private function kindsOf(string $url): array
    {
        return collect($this->getJson($url)->assertOk()->json('data.kinds'))->pluck('kind')->all();
    }

    // ───────────────────────── the lists ─────────────────────────

    public function test_the_platform_lists_cover_every_kind_of_both_specialties(): void
    {
        foreach (SpecialtyService::KINDS as $specialty => $kinds) {
            $found = SpecialtyService::query()->whereNull('owner_id')->where('specialty', $specialty)->distinct()->pluck('kind')->all();
            $this->assertEqualsCanonicalizing($kinds, $found, $specialty);
        }

        foreach (['تنظيف الأسنان وإزالة الجير', 'تبييض الأسنان بالعيادة', 'علاج عصب ضرس', 'زراعة سن', 'تقويم معدني', 'تاج زيركون'] as $name) {
            $this->assertSame('dental', SpecialtyService::query()->find($this->platform($name))->specialty, $name);
        }

        foreach (['فيلر الشفاه', 'بوتوكس الجبهة وخطوط العين', 'ليزر إزالة الشعر', 'علاج الندبات', 'خيوط شد الوجه', 'تجميل الجروح وخياطتها'] as $name) {
            $this->assertSame('skin', SpecialtyService::query()->find($this->platform($name))->specialty, $name);
        }

        // no half-filled rows: every service carries both languages
        $this->assertSame(0, SpecialtyService::query()->whereNull('owner_id')->where(fn ($q) => $q->whereNull('name_en')->orWhere('name_en', ''))->count());

        // a kind belongs to one specialty only
        $this->assertSame(count(SpecialtyService::allKinds()), count(array_unique(SpecialtyService::allKinds())));
    }

    public function test_medical_services_never_reach_the_beauty_centres_own_list(): void
    {
        $beauty = DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', 'خدمات الكوافير والتجميل')->pluck('o.name_ar')->all();

        $this->assertNotEmpty($beauty);

        foreach ($beauty as $name) {
            $this->assertStringNotContainsString('أسنان', $name);
            $this->assertStringNotContainsString('فيلر', $name);
            $this->assertStringNotContainsString('بوتوكس', $name);
        }
    }

    // ───────────────────────── whose list ─────────────────────────

    public function test_a_dental_clinic_offers_services_with_its_own_price_and_a_patient_reads_them(): void
    {
        $clinic = $this->dentist();
        $cleaning = $this->platform('تنظيف الأسنان وإزالة الجير');
        $implant = $this->platform('زراعة سن');

        Sanctum::actingAs($clinic);
        $this->putJson('/api/v2/business/specialty-services', ['items' => [
            ['service_id' => $cleaning, 'offered' => true, 'price' => 400],
            ['service_id' => $implant, 'offered' => true],
        ]])->assertOk();

        $kinds = collect($this->getJson('/api/v2/business/specialty-services')->assertOk()->json('data.kinds'));
        $this->assertEqualsCanonicalizing(SpecialtyService::KINDS['dental'], $kinds->pluck('kind')->all(), 'its own specialty only');

        $row = collect($kinds->firstWhere('kind', 'preventive')['services'])->firstWhere('id', $cleaning);
        $this->assertTrue($row['offered']);
        $this->assertEquals(400, $row['price']);

        Sanctum::actingAs($this->patient());
        $seen = collect($this->getJson("/api/v2/clinics/{$clinic->id}/specialty-services")->assertOk()->json('data.kinds'));

        $this->assertEqualsCanonicalizing(['preventive', 'surgical'], $seen->pluck('kind')->all(), 'only what it offers');
        $this->assertNull(collect($seen->firstWhere('kind', 'surgical')['services'])->first()['price'], 'no price: after the exam');
    }

    public function test_a_skin_clinic_has_the_skin_list_and_never_the_dental_one(): void
    {
        $clinic = $this->skin();
        $filler = $this->platform('فيلر الشفاه');
        $laser = $this->platform('ليزر إزالة الشعر');
        $cleaning = $this->platform('تنظيف الأسنان وإزالة الجير');

        Sanctum::actingAs($clinic);
        $this->assertEqualsCanonicalizing(SpecialtyService::KINDS['skin'], $this->kindsOf('/api/v2/business/specialty-services'));

        $this->putJson('/api/v2/business/specialty-services', ['items' => [
            ['service_id' => $filler, 'offered' => true, 'price' => 3500],
            ['service_id' => $laser, 'offered' => true, 'price' => 600],
        ]])->assertOk();

        // a dentist's service is not on its list
        $this->putJson('/api/v2/business/specialty-services', ['items' => [['service_id' => $cleaning, 'offered' => true]]])->assertStatus(422);

        Sanctum::actingAs($this->patient());
        $seen = collect($this->getJson("/api/v2/clinics/{$clinic->id}/specialty-services")->assertOk()->json('data.kinds'));
        $this->assertEqualsCanonicalizing(['injectables', 'laser'], $seen->pluck('kind')->all());
        $this->assertEquals(3500, collect($seen->firstWhere('kind', 'injectables')['services'])->first()['price']);
    }

    public function test_the_cosmetic_surgery_specialty_gets_the_skin_list_too(): void
    {
        $clinic = $this->clinic(['جراحة تجميل']);

        Sanctum::actingAs($clinic);
        $this->assertEqualsCanonicalizing(SpecialtyService::KINDS['skin'], $this->kindsOf('/api/v2/business/specialty-services'));
    }

    public function test_a_clinic_with_both_specialties_has_both_lists_in_one(): void
    {
        $clinic = $this->clinic(['أسنان', 'جلديه وتناسليه']);

        Sanctum::actingAs($clinic);
        $kinds = $this->kindsOf('/api/v2/business/specialty-services');

        $this->assertSame(array_merge(SpecialtyService::KINDS['dental'], SpecialtyService::KINDS['skin']), $kinds, 'dental first, then skin');
    }

    public function test_a_clinic_of_another_specialty_has_no_list_and_no_door(): void
    {
        $internal = $this->clinic(['باطنه']);

        Sanctum::actingAs($internal);
        $this->getJson('/api/v2/business/specialty-services')->assertForbidden();
        $this->putJson('/api/v2/business/specialty-services', ['items' => [['service_id' => $this->platform('زراعة سن'), 'offered' => true]]])->assertForbidden();

        Sanctum::actingAs($this->patient());
        $this->getJson("/api/v2/clinics/{$internal->id}/specialty-services")->assertNotFound();
    }

    public function test_a_hospital_and_a_beauty_centre_are_refused_even_with_the_option(): void
    {
        $hospital = $this->clinic(['أسنان', 'جلديه وتناسليه'], self::HOSPITAL);
        $beauty = $this->clinic(['أسنان', 'جراحة تجميل'], 554);

        foreach ([$hospital, $beauty] as $account) {
            Sanctum::actingAs($account);
            $this->getJson('/api/v2/business/specialty-services')->assertForbidden();
        }

        Sanctum::actingAs($this->patient());
        $this->getJson("/api/v2/clinics/{$beauty->id}/specialty-services")->assertNotFound();
        $this->getJson("/api/v2/clinics/{$hospital->id}/specialty-services")->assertNotFound();
    }

    public function test_a_clinic_adds_its_own_service_under_its_own_kinds_and_no_other_clinic_sees_it(): void
    {
        $clinic = $this->skin();
        $other = $this->skin();

        Sanctum::actingAs($clinic);
        $this->postJson('/api/v2/business/specialty-services/custom', ['kind' => 'laser', 'name' => 'ليزر خاص بالعيادة'])->assertCreated();

        $own = SpecialtyService::query()->where('name_ar', 'ليزر خاص بالعيادة')->first();
        $this->assertSame('skin', $own->specialty);
        $this->assertSame((int) $clinic->id, (int) $own->owner_id);

        // a duplicate, an unknown kind and a kind of another specialty are all refused
        $this->postJson('/api/v2/business/specialty-services/custom', ['kind' => 'laser', 'name' => 'ليزر خاص بالعيادة'])->assertStatus(422);
        $this->postJson('/api/v2/business/specialty-services/custom', ['kind' => 'astrology', 'name' => 'x'])->assertStatus(422);
        $this->postJson('/api/v2/business/specialty-services/custom', ['kind' => 'preventive', 'name' => 'تنظيف خاص'])->assertStatus(422);

        Sanctum::actingAs($other);
        $theirs = collect(collect($this->getJson('/api/v2/business/specialty-services')->json('data.kinds'))->firstWhere('kind', 'laser')['services']);
        $this->assertNull($theirs->firstWhere('name', 'ليزر خاص بالعيادة'));

        // …and cannot offer or delete it
        $this->putJson('/api/v2/business/specialty-services', ['items' => [['service_id' => $own->id, 'offered' => true]]])->assertStatus(422);
        $this->deleteJson("/api/v2/business/specialty-services/custom/{$own->id}")->assertNotFound();

        Sanctum::actingAs($clinic);
        $this->deleteJson("/api/v2/business/specialty-services/custom/{$own->id}")->assertOk();
        $this->assertSame(0, SpecialtyService::query()->where('name_ar', 'ليزر خاص بالعيادة')->count());
    }

    public function test_taking_a_service_off_the_list_and_the_price_rules(): void
    {
        $clinic = $this->dentist();
        $braces = $this->platform('تقويم معدني');

        Sanctum::actingAs($clinic);
        $this->putJson('/api/v2/business/specialty-services', ['items' => [['service_id' => $braces, 'offered' => true, 'price' => 6500]]])->assertOk();
        $this->putJson('/api/v2/business/specialty-services', ['items' => [['service_id' => $braces, 'offered' => true, 'price' => 0]]])->assertOk();

        $row = collect(collect($this->getJson('/api/v2/business/specialty-services')->json('data.kinds'))->firstWhere('kind', 'orthodontic')['services'])->firstWhere('id', $braces);
        $this->assertNull($row['price'], 'zero means after the exam, never a free service');

        $this->putJson('/api/v2/business/specialty-services', ['items' => [['service_id' => $braces, 'offered' => true, 'price' => -5]]])->assertStatus(422);
        $this->putJson('/api/v2/business/specialty-services', ['items' => [['service_id' => $braces, 'offered' => false]]])->assertOk();

        Sanctum::actingAs($this->patient());
        $this->assertSame([], $this->getJson("/api/v2/clinics/{$clinic->id}/specialty-services")->assertOk()->json('data.kinds'));
    }

    public function test_the_page_and_the_account_say_whether_the_clinic_has_a_list(): void
    {
        $clinic = $this->skin();
        $internal = $this->clinic(['باطنه']);

        Sanctum::actingAs($clinic);
        $this->getJson('/api/v2/auth/me')->assertOk()->assertJsonPath('data.specialty_services', true);

        // listed nothing yet: the booking board has nothing to offer
        $this->assertFalse((bool) $this->getJson("/api/v2/businesses/{$clinic->id}")->assertOk()->json('data.sections.specialty_services'));

        $this->putJson('/api/v2/business/specialty-services', ['items' => [['service_id' => $this->platform('فيلر الشفاه'), 'offered' => true, 'price' => 3500]]])->assertOk();
        $this->assertTrue((bool) $this->getJson("/api/v2/businesses/{$clinic->id}")->json('data.sections.specialty_services'));

        Sanctum::actingAs($internal);
        $this->getJson('/api/v2/auth/me')->assertOk()->assertJsonPath('data.specialty_services', false);
        $this->assertFalse((bool) $this->getJson("/api/v2/businesses/{$internal->id}")->json('data.sections.specialty_services'));
    }

    // ───────────────────────── booking ─────────────────────────

    private function slot(User $clinic, int $hour = 11): ClinicAppointmentSlot
    {
        return ClinicAppointmentSlot::create([
            'clinic_id' => $clinic->id, 'starts_at' => now()->addDays(2)->setHour($hour)->setMinute(0)->setSecond(0), 'duration_minutes' => 30,
        ]);
    }

    private function offer(User $clinic, string $name, ?float $price): int
    {
        $id = $this->platform($name);
        Sanctum::actingAs($clinic);
        $this->putJson('/api/v2/business/specialty-services', ['items' => [['service_id' => $id, 'offered' => true, 'price' => $price]]])->assertOk();

        return $id;
    }

    public function test_a_patient_names_the_service_when_booking_a_slot_and_both_sides_read_it(): void
    {
        $clinic = $this->skin();
        $filler = $this->offer($clinic, 'فيلر الشفاه', 3500);
        $slot = $this->slot($clinic);

        Sanctum::actingAs($this->patient());
        $this->postJson("/api/v2/clinic-slots/{$slot->id}/book", ['specialty_service_id' => $filler])
            ->assertCreated()
            ->assertJsonPath('data.appointment.specialty_service.id', $filler)
            ->assertJsonPath('data.appointment.specialty_service.name', SpecialtyService::query()->find($filler)->label());

        $mine = $this->getJson('/api/v2/clinic-appointments')->assertOk()->json('data.data');
        $this->assertSame(SpecialtyService::query()->find($filler)->label(), $mine[0]['specialty_service']['name']);

        Sanctum::actingAs($clinic);
        $rows = $this->getJson('/api/v2/business/clinic-appointments')->assertOk()->json('data.data');
        $this->assertSame(SpecialtyService::query()->find($filler)->label(), $rows[0]['specialty_service']['name']);
        $this->assertEquals(3500, $rows[0]['specialty_service']['price']);
    }

    public function test_the_booked_service_keeps_its_name_and_price_when_the_clinic_reprices_or_drops_it(): void
    {
        $clinic = $this->dentist();
        $implant = $this->offer($clinic, 'زراعة سن', 9000);
        $slot = $this->slot($clinic);

        Sanctum::actingAs($this->patient());
        $id = $this->postJson("/api/v2/clinic-slots/{$slot->id}/book", ['specialty_service_id' => $implant])->assertCreated()->json('data.appointment.id');

        Sanctum::actingAs($clinic);
        $this->putJson('/api/v2/business/specialty-services', ['items' => [['service_id' => $implant, 'offered' => true, 'price' => 12000]]])->assertOk();
        $this->assertEquals(9000, ClinicAppointment::query()->find($id)->service_price, 'kept as asked');

        $this->putJson('/api/v2/business/specialty-services', ['items' => [['service_id' => $implant, 'offered' => false]]])->assertOk();
        $this->assertSame('زراعة سن', ClinicAppointment::query()->find($id)->service_name);
    }

    public function test_a_service_the_clinic_does_not_offer_is_refused_and_the_slot_stays_open(): void
    {
        $clinic = $this->dentist();
        $other = $this->dentist();
        $skinClinic = $this->skin();
        $this->offer($clinic, 'تقويم معدني', 6000);
        $theirs = $this->offer($other, 'زراعة سن', 9000);
        $filler = $this->offer($skinClinic, 'فيلر الشفاه', 3500);
        $slot = $this->slot($clinic);

        Sanctum::actingAs($this->patient());
        // another clinic's offer, and another specialty's — neither is this clinic's
        $this->postJson("/api/v2/clinic-slots/{$slot->id}/book", ['specialty_service_id' => $theirs])
            ->assertStatus(422)->assertJsonValidationErrors('specialty_service_id');
        $this->postJson("/api/v2/clinic-slots/{$slot->id}/book", ['specialty_service_id' => $filler])->assertStatus(422);

        $this->assertNull($slot->fresh()->appointment_id, 'the slot was not taken');
    }

    public function test_a_clinic_with_no_list_takes_no_service_but_books_as_before(): void
    {
        $internal = $this->clinic(['باطنه']);
        $dentist = $this->dentist();
        $service = $this->offer($dentist, 'تنظيف الأسنان وإزالة الجير', 400);
        $slot = $this->slot($internal);

        Sanctum::actingAs($this->patient());
        $this->postJson("/api/v2/clinic-slots/{$slot->id}/book", ['specialty_service_id' => $service])->assertStatus(422);

        $this->postJson("/api/v2/clinic-slots/{$slot->id}/book", ['reason' => 'ضغط'])
            ->assertCreated()->assertJsonPath('data.appointment.specialty_service', null);
    }

    public function test_unticking_the_specialty_hides_the_list_and_refuses_a_booking_with_it(): void
    {
        $clinic = $this->dentist();
        $cleaning = $this->offer($clinic, 'تنظيف الأسنان وإزالة الجير', 400);
        $slot = $this->slot($clinic);

        DB::table('option_user')->where('user_id', $clinic->id)->delete();

        Sanctum::actingAs($this->patient());
        $this->getJson("/api/v2/clinics/{$clinic->id}/specialty-services")->assertNotFound();
        $this->postJson("/api/v2/clinic-slots/{$slot->id}/book", ['specialty_service_id' => $cleaning])->assertStatus(422);
    }

    public function test_a_requested_appointment_can_name_the_service_too(): void
    {
        $clinic = $this->skin();
        $laser = $this->offer($clinic, 'ليزر إزالة الشعر', 600);

        Sanctum::actingAs($this->patient());
        $this->postJson('/api/v2/clinic-appointments', [
            'clinic_id' => $clinic->id, 'scheduled_at' => now()->addDays(3)->toDateTimeString(), 'specialty_service_id' => $laser,
        ])->assertCreated()->assertJsonPath('data.appointment.specialty_service.name', SpecialtyService::query()->find($laser)->label());
    }

    // ───────────────────────── the seeder ─────────────────────────

    public function test_the_seeder_is_add_only_idempotent_and_refiles_by_name(): void
    {
        $before = SpecialtyService::query()->whereNull('owner_id')->count();
        $clinic = $this->dentist();

        // a clinic's own entry and a hand-renamed English name survive a re-run
        SpecialtyService::create(['specialty' => 'dental', 'kind' => 'cosmetic', 'name_ar' => 'خدمة خاصة', 'owner_id' => $clinic->id, 'sort_order' => 999]);
        SpecialtyService::query()->whereNull('owner_id')->where('name_ar', 'زراعة سن')->update(['name_en' => 'Hand-edited']);

        // a row as an older run could have left it: filed under the wrong kind
        $laser = $this->platform('ليزر إزالة الشعر');
        SpecialtyService::query()->whereKey($laser)->update(['kind' => 'peels']);

        $this->seed(SpecialtyServicesSeeder::class);

        $this->assertSame($before, SpecialtyService::query()->whereNull('owner_id')->count());
        $this->assertSame(1, SpecialtyService::query()->where('name_ar', 'خدمة خاصة')->count());
        $this->assertSame('Hand-edited', SpecialtyService::query()->whereNull('owner_id')->where('name_ar', 'زراعة سن')->value('name_en'));
        $this->assertSame('laser', SpecialtyService::query()->find($laser)->kind, 're-filed, same id');
    }
}
