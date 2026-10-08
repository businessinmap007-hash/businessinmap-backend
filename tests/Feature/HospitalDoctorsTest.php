<?php

namespace Tests\Feature;

use App\Models\BusinessWorkingHour;
use App\Models\ClinicAppointmentSlot;
use App\Models\HospitalDoctor;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «ابنِ نموذج الأطباء تحت الأقسام» — المالك، 2026-10-09. The departments are the specialties a hospital ticked on itself;
 * a doctor with an account joins by accepting and a patient reaches that doctor's page from under the department.
 *
 * Rolls back.
 */
class HospitalDoctorsTest extends TestCase
{
    use DatabaseTransactions;

    private const HEALTH = 20;
    private const HOSPITAL = 513;
    private const CLINIC = 514;

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

    private function specialty(string $name): int
    {
        $id = (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', HospitalDoctor::DEPARTMENT_GROUP)->where('o.name_ar', $name)->value('o.id');
        $this->assertGreaterThan(0, $id, $name);

        return $id;
    }

    /** @return array{0:User,1:int,2:int} a hospital with two departments ticked, and their option ids */
    private function hospital(): array
    {
        $h = $this->user(User::TYPE_BUSINESS, self::HOSPITAL, 'Hospital');
        $heart = $this->specialty('باطنه');
        $kids = $this->specialty('اطفال وحديثي الولادة');
        DB::table('option_user')->insert([['user_id' => $h->id, 'option_id' => $heart], ['user_id' => $h->id, 'option_id' => $kids]]);

        return [$h, $heart, $kids];
    }

    public function test_the_departments_are_the_specialties_the_hospital_ticked(): void
    {
        [$hospital, $heart, $kids] = $this->hospital();
        Sanctum::actingAs($this->user(User::TYPE_CLIENT, null, 'Patient'));

        $departments = collect($this->getJson("/api/v2/hospitals/{$hospital->id}/departments")->assertOk()->json('data.departments'));

        $this->assertEqualsCanonicalizing([$heart, $kids], $departments->pluck('option_id')->all());
        $this->assertSame([], $departments->first()['doctors']);
    }

    public function test_only_a_hospital_has_departments(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, self::CLINIC, 'Doctor');
        Sanctum::actingAs($this->user(User::TYPE_CLIENT, null, 'Patient'));
        $this->getJson("/api/v2/hospitals/{$clinic->id}/departments")->assertNotFound();

        Sanctum::actingAs($clinic);
        $this->postJson('/api/v2/business/hospital-doctors', ['option_id' => 1, 'name' => 'x'])->assertForbidden();
    }

    public function test_a_doctor_with_no_account_is_listed_as_text_at_once(): void
    {
        [$hospital, $heart] = $this->hospital();
        Sanctum::actingAs($hospital);

        $this->postJson('/api/v2/business/hospital-doctors', ['option_id' => $heart, 'name' => 'أحمد سمير', 'title' => 'استشاري'])
            ->assertCreated()->assertJsonPath('data.doctor.business_id', null)->assertJsonPath('data.doctor.status', 'active');

        Sanctum::actingAs($this->user(User::TYPE_CLIENT, null, 'Patient'));
        $first = $this->getJson("/api/v2/hospitals/{$hospital->id}/departments")->json('data.departments.0');
        $dept = collect($this->getJson("/api/v2/hospitals/{$hospital->id}/departments")->json('data.departments'))->firstWhere('option_id', $heart);

        $this->assertSame('استشاري أحمد سمير', $dept['doctors'][0]['name']);
        $this->assertNull($dept['doctors'][0]['business_id'], 'no account: nothing to visit');
        $this->assertNotNull($first);
    }

    public function test_a_doctor_with_an_account_appears_to_patients_only_after_accepting(): void
    {
        [$hospital, $heart, $kids] = $this->hospital();
        $doctor = $this->user(User::TYPE_BUSINESS, self::CLINIC, 'Doc');

        Sanctum::actingAs($hospital);
        $row = $this->postJson('/api/v2/business/hospital-doctors', ['option_id' => $heart, 'user_id' => $doctor->id])
            ->assertCreated()->assertJsonPath('data.doctor.status', 'pending')->json('data.doctor.id');
        // the same doctor under a second department waits on the same agreement
        $this->postJson('/api/v2/business/hospital-doctors', ['option_id' => $kids, 'user_id' => $doctor->id])->assertCreated();

        // the hospital sees the pending row, a patient does not
        $own = collect($this->getJson('/api/v2/business/hospital-doctors')->assertOk()->json('data.departments'))->firstWhere('option_id', $heart);
        $this->assertSame('pending', $own['doctors'][0]['status']);

        Sanctum::actingAs($this->user(User::TYPE_CLIENT, null, 'Patient'));
        $seen = collect($this->getJson("/api/v2/hospitals/{$hospital->id}/departments")->json('data.departments'))->pluck('doctors')->flatten(1);
        $this->assertCount(0, $seen);

        // the doctor sees the invitation and accepts it: both departments open at once
        Sanctum::actingAs($doctor);
        $this->assertCount(2, $this->getJson('/api/v2/business/hospital-invitations')->assertOk()->json('data.pending'));
        $this->postJson("/api/v2/business/hospital-invitations/{$row}/accept")->assertOk()->assertJsonCount(0, 'data.pending')->assertJsonCount(2, 'data.active');

        Sanctum::actingAs($this->user(User::TYPE_CLIENT, null, 'Patient'));
        $dept = collect($this->getJson("/api/v2/hospitals/{$hospital->id}/departments")->json('data.departments'))->firstWhere('option_id', $heart);
        $this->assertSame((int) $doctor->id, $dept['doctors'][0]['business_id'], 'the page a patient can visit');
    }

    public function test_a_department_the_hospital_did_not_tick_and_a_non_doctor_are_refused(): void
    {
        [$hospital, $heart] = $this->hospital();
        Sanctum::actingAs($hospital);

        $this->postJson('/api/v2/business/hospital-doctors', ['option_id' => $this->specialty('اورام'), 'name' => 'x'])->assertStatus(422);
        $this->postJson('/api/v2/business/hospital-doctors', ['option_id' => $heart, 'user_id' => $this->user(User::TYPE_BUSINESS, 163, 'Lab')->id])->assertStatus(422);
        $this->postJson('/api/v2/business/hospital-doctors', ['option_id' => $heart])->assertStatus(422);
    }

    public function test_either_side_can_end_it_and_nobody_else(): void
    {
        [$hospital, $heart] = $this->hospital();
        $doctor = $this->user(User::TYPE_BUSINESS, self::CLINIC, 'Doc');

        Sanctum::actingAs($hospital);
        $row = $this->postJson('/api/v2/business/hospital-doctors', ['option_id' => $heart, 'user_id' => $doctor->id])->json('data.doctor.id');

        // a stranger's hospital cannot delete another hospital's row
        [$other] = $this->hospital();
        Sanctum::actingAs($other);
        $this->deleteJson("/api/v2/business/hospital-doctors/{$row}")->assertNotFound();

        // the doctor declines the invitation
        Sanctum::actingAs($doctor);
        $this->deleteJson("/api/v2/business/hospital-invitations/{$row}")->assertOk();
        $this->assertDatabaseMissing('hospital_doctors', ['id' => $row]);
    }

    public function test_finding_doctors_by_name_returns_individual_clinics_only(): void
    {
        [$hospital] = $this->hospital();
        $needle = 'Zq' . Str::random(5);
        $doc = $this->user(User::TYPE_BUSINESS, self::CLINIC, $needle);
        $this->user(User::TYPE_BUSINESS, 163, $needle . 'lab');

        Sanctum::actingAs($hospital);
        $found = $this->getJson('/api/v2/business/hospital-doctors/find-doctors?q=' . $needle)->assertOk()->json('data.doctors');

        $this->assertSame([(int) $doc->id], array_column($found, 'id'));
    }

    public function test_a_clinic_slot_outside_the_working_hours_is_not_offered(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, self::CLINIC, 'Doc');
        $tomorrow = now()->addDay()->startOfDay();
        $mk = fn (int $hour) => ClinicAppointmentSlot::create([
            'clinic_id' => $clinic->id, 'starts_at' => $tomorrow->copy()->setHour($hour), 'duration_minutes' => 30,
        ]);
        $inside = $mk(10);
        $outside = $mk(22);
        BusinessWorkingHour::create(['business_id' => $clinic->id, 'day_of_week' => $tomorrow->dayOfWeek, 'is_closed' => false, 'open_time' => '09:00:00', 'close_time' => '17:00:00']);

        Sanctum::actingAs($this->user(User::TYPE_CLIENT, null, 'Patient'));
        $ids = collect($this->getJson("/api/v2/clinics/{$clinic->id}/slots")->assertOk()->json('data.data'))->pluck('id')->all();

        $this->assertContains((int) $inside->id, $ids);
        $this->assertNotContains((int) $outside->id, $ids);
    }
}
