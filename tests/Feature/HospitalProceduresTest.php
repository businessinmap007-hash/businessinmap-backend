<?php

namespace Tests\Feature;

use App\Models\ClinicAppointmentSlot;
use App\Models\MedicalProcedure;
use App\Models\ProcedureRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «اضف للمستشفى إجراء طبي مثل العمليات الجراحية» — المالك، 2026-10-10, and the clinic board's «لشخص آخر».
 *
 * Rolls back.
 */
class HospitalProceduresTest extends TestCase
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

    private function hospital(): User
    {
        return $this->user(User::TYPE_BUSINESS, self::HOSPITAL, 'Hospital');
    }

    private function platform(string $name): int
    {
        $id = (int) MedicalProcedure::query()->whereNull('owner_id')->where('name_ar', $name)->value('id');
        $this->assertGreaterThan(0, $id, $name);

        return $id;
    }

    public function test_the_platform_list_has_surgeries_endoscopies_and_procedures(): void
    {
        $kinds = MedicalProcedure::query()->whereNull('owner_id')->distinct()->pluck('kind')->all();

        $this->assertEqualsCanonicalizing(MedicalProcedure::KINDS, $kinds);
    }

    public function test_a_hospital_offers_procedures_with_its_own_price_and_a_patient_reads_them(): void
    {
        $hospital = $this->hospital();
        $appendix = $this->platform('استئصال الزائدة الدودية');
        $gastro = $this->platform('منظار المعدة');

        Sanctum::actingAs($hospital);
        $this->putJson('/api/v2/business/hospital-procedures', ['items' => [
            ['procedure_id' => $appendix, 'offered' => true, 'price' => 12000],
            ['procedure_id' => $gastro, 'offered' => true],
        ]])->assertOk();

        $kinds = collect($this->getJson('/api/v2/business/hospital-procedures')->assertOk()->json('data.kinds'));
        $surgery = collect($kinds->firstWhere('kind', 'surgery')['procedures'])->firstWhere('id', $appendix);
        $this->assertTrue($surgery['offered']);
        $this->assertEquals(12000, $surgery['price']);

        Sanctum::actingAs($this->user(User::TYPE_CLIENT, null, 'Patient'));
        $seen = collect($this->getJson("/api/v2/hospitals/{$hospital->id}/procedures")->assertOk()->json('data.kinds'));

        $this->assertEqualsCanonicalizing(['surgery', 'endoscopy'], $seen->pluck('kind')->all(), 'only what it offers');
        $this->assertNull(collect($seen->firstWhere('kind', 'endoscopy')['procedures'])->first()['price'], 'no price: after assessment');
    }

    public function test_a_hospital_adds_its_own_procedure_and_no_other_hospital_sees_it(): void
    {
        $hospital = $this->hospital();
        $other = $this->hospital();

        Sanctum::actingAs($hospital);
        $this->postJson('/api/v2/business/hospital-procedures/custom', ['kind' => 'surgery', 'name' => 'جراحة نادرة خاصة'])->assertCreated();

        $mine = collect($this->getJson('/api/v2/business/hospital-procedures')->json('data.kinds'))->pluck('procedures')->flatten(1);
        $this->assertTrue($mine->contains(fn ($p) => $p['name'] === 'جراحة نادرة خاصة' && $p['own'] && $p['offered']));

        Sanctum::actingAs($other);
        $theirs = collect($this->getJson('/api/v2/business/hospital-procedures')->json('data.kinds'))->pluck('procedures')->flatten(1);
        $this->assertFalse($theirs->contains(fn ($p) => $p['name'] === 'جراحة نادرة خاصة'));

        $this->postJson('/api/v2/business/hospital-procedures/custom', ['kind' => 'nonsense', 'name' => 'x'])->assertStatus(422);
    }

    public function test_only_a_hospital_has_procedures(): void
    {
        Sanctum::actingAs($this->user(User::TYPE_BUSINESS, self::CLINIC, 'Doc'));
        $this->getJson('/api/v2/business/hospital-procedures')->assertForbidden();

        $clinic = $this->user(User::TYPE_BUSINESS, self::CLINIC, 'Doc2');
        Sanctum::actingAs($this->user(User::TYPE_CLIENT, null, 'Patient'));
        $this->getJson("/api/v2/hospitals/{$clinic->id}/procedures")->assertNotFound();
    }

    public function test_a_patient_requests_a_procedure_and_the_hospital_schedules_it_to_the_end(): void
    {
        $hospital = $this->hospital();
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');
        $kneeless = $this->platform('تغيير مفصل الركبة');

        Sanctum::actingAs($hospital);
        $this->putJson('/api/v2/business/hospital-procedures', ['items' => [['procedure_id' => $kneeless, 'offered' => true]]])->assertOk();
        $offering = collect($this->getJson("/api/v2/hospitals/{$hospital->id}/procedures")->json('data.kinds'))->first()['procedures'][0]['id'];

        Sanctum::actingAs($patient);
        $id = $this->postJson('/api/v2/procedure-requests', [
            'hospital_procedure_id' => $offering, 'preferred_date' => now()->addDays(10)->toDateString(), 'notes' => 'ركبة يمين',
        ])->assertCreated()->assertJsonPath('data.request.status', 'requested')->json('data.request.id');

        // the hospital sees it, accepts with a date and a quote (it had no price)
        Sanctum::actingAs($hospital);
        $this->assertSame([$id], collect($this->getJson('/api/v2/business/procedure-requests?tab=incoming')->json('data.data'))->pluck('id')->all());
        $at = now()->addDays(12)->setHour(9)->setMinute(0)->toIso8601String();
        $this->postJson("/api/v2/business/procedure-requests/{$id}/accept", ['scheduled_at' => $at, 'price' => 45000])
            ->assertOk()->assertJsonPath('data.request.status', 'accepted')->assertJsonPath('data.request.price', 45000);

        // a second answer on the same request is refused
        $this->postJson("/api/v2/business/procedure-requests/{$id}/decline")->assertStatus(422);

        $this->postJson("/api/v2/business/procedure-requests/{$id}/complete")->assertOk()->assertJsonPath('data.request.status', 'completed');

        Sanctum::actingAs($patient);
        $mine = $this->getJson('/api/v2/procedure-requests')->assertOk()->json('data.data');
        $this->assertSame('completed', $mine[0]['status']);
    }

    public function test_a_listed_price_is_not_overwritten_by_a_quote_and_the_name_is_kept(): void
    {
        $hospital = $this->hospital();
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');
        $proc = $this->platform('ولادة قيصرية');

        Sanctum::actingAs($hospital);
        $this->putJson('/api/v2/business/hospital-procedures', ['items' => [['procedure_id' => $proc, 'offered' => true, 'price' => 20000]]])->assertOk();
        $offering = collect($this->getJson("/api/v2/hospitals/{$hospital->id}/procedures")->json('data.kinds'))->first()['procedures'][0]['id'];

        Sanctum::actingAs($patient);
        $id = $this->postJson('/api/v2/procedure-requests', ['hospital_procedure_id' => $offering])->assertCreated()->json('data.request.id');

        // the hospital changes its price afterwards: the request keeps what was asked
        Sanctum::actingAs($hospital);
        $this->putJson('/api/v2/business/hospital-procedures', ['items' => [['procedure_id' => $proc, 'offered' => true, 'price' => 99999]]])->assertOk();
        $this->postJson("/api/v2/business/procedure-requests/{$id}/accept", ['scheduled_at' => now()->addDays(3)->toIso8601String(), 'price' => 1])
            ->assertOk()->assertJsonPath('data.request.price', 20000);
    }

    public function test_the_patient_cancels_and_a_stranger_cannot_touch_a_request(): void
    {
        $hospital = $this->hospital();
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');
        $proc = $this->platform('منظار القولون');

        Sanctum::actingAs($hospital);
        $this->putJson('/api/v2/business/hospital-procedures', ['items' => [['procedure_id' => $proc, 'offered' => true]]])->assertOk();
        $offering = collect($this->getJson("/api/v2/hospitals/{$hospital->id}/procedures")->json('data.kinds'))->first()['procedures'][0]['id'];

        Sanctum::actingAs($patient);
        $id = $this->postJson('/api/v2/procedure-requests', ['hospital_procedure_id' => $offering])->json('data.request.id');

        Sanctum::actingAs($this->user(User::TYPE_CLIENT, null, 'Stranger'));
        $this->postJson("/api/v2/procedure-requests/{$id}/cancel")->assertNotFound();

        Sanctum::actingAs($this->hospital());
        $this->postJson("/api/v2/business/procedure-requests/{$id}/accept", ['scheduled_at' => now()->addDay()->toIso8601String()])->assertNotFound();

        Sanctum::actingAs($patient);
        $this->postJson("/api/v2/procedure-requests/{$id}/cancel")->assertOk()->assertJsonPath('data.request.status', ProcedureRequest::STATUS_CANCELLED);
    }

    public function test_a_clinic_booking_can_be_for_someone_else_and_the_clinic_reads_who(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, self::CLINIC, 'Doc');
        $parent = $this->user(User::TYPE_CLIENT, null, 'Son');
        $slot = ClinicAppointmentSlot::create([
            'clinic_id' => $clinic->id, 'starts_at' => now()->addDays(2)->setHour(11)->setMinute(0), 'duration_minutes' => 30,
        ]);

        Sanctum::actingAs($parent);
        $this->postJson("/api/v2/clinic-slots/{$slot->id}/book", ['reason' => 'ضغط', 'for_name' => 'الحاج محمود', 'for_phone' => '01012345678'])
            ->assertCreated()->assertJsonPath('data.appointment.attendee.name', 'الحاج محمود');

        Sanctum::actingAs($clinic);
        $rows = $this->getJson('/api/v2/business/clinic-appointments')->assertOk()->json('data.data');
        $this->assertSame('الحاج محمود', $rows[0]['attendee']['name']);
        $this->assertSame('01012345678', $rows[0]['attendee']['phone']);
        $this->assertSame((int) $parent->id, $rows[0]['patient']['id'], 'the account that booked stays the patient');
    }

    public function test_a_booking_for_myself_has_no_attendee(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, self::CLINIC, 'Doc');
        $slot = ClinicAppointmentSlot::create([
            'clinic_id' => $clinic->id, 'starts_at' => now()->addDays(2)->setHour(12)->setMinute(0), 'duration_minutes' => 30,
        ]);

        Sanctum::actingAs($this->user(User::TYPE_CLIENT, null, 'Me'));
        $this->postJson("/api/v2/clinic-slots/{$slot->id}/book", ['for_name' => '   '])
            ->assertCreated()->assertJsonPath('data.appointment.attendee', null);
    }
}
