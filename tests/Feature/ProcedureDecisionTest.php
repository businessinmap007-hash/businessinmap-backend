<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\BusinessStaff;
use App\Models\HospitalProcedure;
use App\Models\MedicalProcedure;
use App\Models\ProcedureRequest;
use App\Models\User;
use App\Support\BusinessCapability;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «الطبيب المعالج له صلاحيات أساسية: الروشتة والتحاليل والأشعة وقرار إجراء جراحي» — المالك، 2026-10-19.
 *
 * Marking a staff member a treating physician grants the bundle on its own; the physician then decides a procedure for
 * a patient, who confirms it (the hospital schedules it) or declines.
 *
 * Rolls back.
 */
class ProcedureDecisionTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $type, ?int $child = null, string $tag = 'U'): User
    {
        $u = new User();
        $u->forceFill([
            'name' => $tag . ' ' . Str::random(4), 'type' => $type, 'email' => strtolower($tag) . '-' . uniqid() . '@example.test',
            'phone' => '010' . random_int(10000000, 99999999), 'password' => 'secret-password', 'api_token' => Str::random(60),
            'category_id' => $child ? 20 : null, 'category_child_id' => $child,
        ])->save();

        return $u;
    }

    /** @return array{0:User,1:User,2:User,3:HospitalProcedure} hospital, physician, secretary, an offering */
    private function hospital(): array
    {
        $hospital = $this->user(User::TYPE_BUSINESS, 513, 'Hospital');
        $doctor = $this->user(User::TYPE_CLIENT, null, 'Doctor');
        $secretary = $this->user(User::TYPE_CLIENT, null, 'Secretary');

        Sanctum::actingAs($hospital);
        // the role alone: no capability sent at all besides one the owner ticked
        $this->postJson('/api/v2/business/staff', [
            'user_id' => $doctor->id, 'title' => 'استشاري جراحة عيون', 'capabilities' => [BusinessCapability::WORKING_HOURS], 'is_physician' => true,
        ])->assertCreated();
        $this->postJson('/api/v2/business/staff', [
            'user_id' => $secretary->id, 'title' => 'سكرتيرة', 'capabilities' => [BusinessCapability::CLINIC],
        ])->assertCreated();

        foreach ([$doctor, $secretary] as $member) {
            Sanctum::actingAs($member);
            $this->postJson("/api/v2/staff/invitations/{$hospital->id}/accept")->assertOk();
        }

        $procedure = MedicalProcedure::query()->whereNull('owner_id')->where('kind', 'ophthalmology')->first()
            ?? MedicalProcedure::query()->whereNull('owner_id')->firstOrFail();
        $offering = HospitalProcedure::query()->create(['hospital_id' => $hospital->id, 'procedure_id' => $procedure->id, 'price' => 8000]);

        return [$hospital, $doctor, $secretary, $offering];
    }

    public function test_a_treating_physician_carries_the_basic_permissions_by_the_role_itself(): void
    {
        $hospital = $this->user(User::TYPE_BUSINESS, 513, 'Hospital');
        $doctor = $this->user(User::TYPE_CLIENT, null, 'Doctor');

        Sanctum::actingAs($hospital);
        $caps = collect($this->postJson('/api/v2/business/staff', [
            'user_id' => $doctor->id, 'capabilities' => [BusinessCapability::WORKING_HOURS], 'is_physician' => true,
        ])->assertCreated()->json('data.staff.capabilities'));

        foreach (BusinessCapability::PHYSICIAN_BUNDLE as $capability) {
            $this->assertTrue($caps->contains($capability), $capability);
        }
        $this->assertTrue($caps->contains(BusinessCapability::WORKING_HOURS), 'what the owner ticked stays');

        // saving him again without the bundle never takes it away
        $again = collect($this->patchJson("/api/v2/business/staff/{$doctor->id}", ['capabilities' => [BusinessCapability::WORKING_HOURS]])
            ->assertOk()->json('data.staff.capabilities'));
        $this->assertTrue($again->contains(BusinessCapability::PROCEDURES));

        // a clinic's secretary is not a physician: she keeps only what she was given
        $secretary = $this->user(User::TYPE_CLIENT, null, 'Secretary');
        $this->postJson('/api/v2/business/staff', ['user_id' => $secretary->id, 'capabilities' => [BusinessCapability::CLINIC]])
            ->assertCreated()->assertJsonPath('data.staff.capabilities', [BusinessCapability::CLINIC]);
    }

    public function test_the_physician_decides_a_procedure_and_the_patient_confirms_it(): void
    {
        [$hospital, $doctor, , $offering] = $this->hospital();
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');

        Sanctum::actingAs($doctor);
        $row = $this->withHeader('X-Business-Id', (string) $hospital->id)
            ->postJson('/api/v2/business/procedure-decisions', [
                'patient_id' => $patient->id, 'hospital_procedure_id' => $offering->id, 'notes' => 'بعد الفحص',
            ])->assertCreated()
            ->assertJsonPath('data.request.status', ProcedureRequest::STATUS_ADVISED)
            ->assertJsonPath('data.request.source', 'physician')
            ->assertJsonPath('data.request.physician.id', $doctor->id)
            ->json('data.request');

        // the patient hears it; the hospital does not (yet)
        $this->assertSame(1, AppNotification::query()->where('user_id', $patient->id)->where('title_ar', 'قرار إجراء طبي')->count());
        $this->assertSame(0, AppNotification::query()->where('user_id', $hospital->id)->where('source_id', $row['id'])->count());

        // not in the hospital's incoming queue until he confirms
        $this->withHeader('X-Business-Id', (string) $hospital->id);
        $this->assertSame([], collect($this->getJson('/api/v2/business/procedure-requests?tab=incoming')->json('data.data'))->pluck('id')->all());

        // his own decisions list
        $this->assertSame([$row['id']], collect($this->getJson('/api/v2/business/procedure-decisions')->assertOk()->json('data.data'))->pluck('id')->all());

        Sanctum::actingAs($patient);
        $this->postJson("/api/v2/procedure-requests/{$row['id']}/confirm")->assertOk()->assertJsonPath('data.request.status', ProcedureRequest::STATUS_REQUESTED);

        // now the hospital schedules it like any request, and the physician hears the patient agreed
        $this->assertGreaterThanOrEqual(1, AppNotification::query()->where('user_id', $hospital->id)->where('source_id', $row['id'])->count());
        $this->assertSame(1, AppNotification::query()->where('user_id', $doctor->id)->where('title_ar', 'أكّد المريض قرارك')->count());

        Sanctum::actingAs($hospital);
        $this->assertSame([$row['id']], collect($this->getJson('/api/v2/business/procedure-requests?tab=incoming')->json('data.data'))->pluck('id')->all());
    }

    public function test_the_patient_may_decline_a_decision_and_a_secretary_cannot_issue_one(): void
    {
        [$hospital, $doctor, $secretary, $offering] = $this->hospital();
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');

        // the secretary holds «مواعيد العيادة» but not the decision itself
        Sanctum::actingAs($secretary);
        $this->withHeader('X-Business-Id', (string) $hospital->id)
            ->postJson('/api/v2/business/procedure-decisions', ['patient_id' => $patient->id, 'hospital_procedure_id' => $offering->id])
            ->assertForbidden();

        Sanctum::actingAs($doctor);
        $id = $this->withHeader('X-Business-Id', (string) $hospital->id)
            ->postJson('/api/v2/business/procedure-decisions', ['patient_id' => $patient->id, 'hospital_procedure_id' => $offering->id])
            ->assertCreated()->json('data.request.id');

        Sanctum::actingAs($patient);
        $this->postJson("/api/v2/procedure-requests/{$id}/cancel")->assertOk()->assertJsonPath('data.request.status', ProcedureRequest::STATUS_CANCELLED);
        $this->assertSame(1, AppNotification::query()->where('user_id', $doctor->id)->where('title_ar', 'اعتذر المريض عن قرارك')->count());

        // a procedure the hospital does not offer, and another person's decision
        Sanctum::actingAs($doctor);
        $this->withHeader('X-Business-Id', (string) $hospital->id)
            ->postJson('/api/v2/business/procedure-decisions', ['patient_id' => $patient->id, 'hospital_procedure_id' => 99999999])->assertStatus(422);

        $stranger = $this->user(User::TYPE_CLIENT, null, 'Stranger');
        Sanctum::actingAs($stranger);
        $this->postJson("/api/v2/procedure-requests/{$id}/confirm")->assertNotFound();
    }
}
