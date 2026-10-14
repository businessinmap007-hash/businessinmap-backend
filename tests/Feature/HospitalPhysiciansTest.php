<?php

namespace Tests\Feature;

use App\Models\BusinessStaff;
use App\Models\ClinicAppointment;
use App\Models\ClinicAppointmentSlot;
use App\Models\InvestigationOrder;
use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\User;
use App\Support\BusinessCapability;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «الطبيب فى المستشفى موظف … يكتب روشتة ويطلب أشعة وتحاليل وله اكتمال الزيارة» — المالك، 2026-10-14.
 *
 * A physician is a staff member of the hospital's account the owner marked `is_physician`. He publishes his own times,
 * sees only his own appointments, and is NAMED on the visits, prescriptions and investigation orders he handles —
 * where before the hospital stood as the only name.
 *
 * Rolls back.
 */
class HospitalPhysiciansTest extends TestCase
{
    use DatabaseTransactions;

    private const HEALTH = 20;
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

    /** @return array{0:User,1:User,2:User,3:User} hospital, physician A, physician B, a secretary */
    private function hospitalWithStaff(): array
    {
        $hospital = $this->user(User::TYPE_BUSINESS, self::HOSPITAL, 'Hospital');
        $a = $this->user(User::TYPE_CLIENT, null, 'DoctorA');
        $b = $this->user(User::TYPE_CLIENT, null, 'DoctorB');
        $secretary = $this->user(User::TYPE_CLIENT, null, 'Secretary');

        $caps = [BusinessCapability::CLINIC, BusinessCapability::PRESCRIPTIONS, BusinessCapability::INVESTIGATIONS];

        Sanctum::actingAs($hospital);
        foreach ([[$a, 'استشاري باطنة'], [$b, 'أخصائي أطفال']] as [$doctor, $title]) {
            $this->postJson('/api/v2/business/staff', [
                'user_id' => $doctor->id, 'title' => $title, 'capabilities' => $caps, 'is_physician' => true,
            ])->assertCreated()->assertJsonPath('data.staff.is_physician', true);
        }
        $this->postJson('/api/v2/business/staff', [
            'user_id' => $secretary->id, 'title' => 'سكرتيرة', 'capabilities' => [BusinessCapability::CLINIC],
        ])->assertCreated()->assertJsonPath('data.staff.is_physician', false);

        foreach ([$a, $b, $secretary] as $member) {
            Sanctum::actingAs($member);
            $this->postJson("/api/v2/staff/invitations/{$hospital->id}/accept")->assertOk();
        }

        return [$hospital, $a, $b, $secretary];
    }

    private function at(string $time, int $days = 3): string
    {
        return Carbon::today()->addDays($days)->setTimeFromTimeString($time)->format('Y-m-d H:i:s');
    }

    public function test_only_a_treating_account_may_mark_a_physician(): void
    {
        $shop = $this->user(User::TYPE_BUSINESS, 51, 'Shop');
        $person = $this->user(User::TYPE_CLIENT, null, 'Person');

        Sanctum::actingAs($shop);
        $this->postJson('/api/v2/business/staff', [
            'user_id' => $person->id, 'capabilities' => [BusinessCapability::WORKING_HOURS], 'is_physician' => true,
        ])->assertStatus(422);
    }

    public function test_each_physician_publishes_his_own_times_and_the_patient_picks_one(): void
    {
        [$hospital, $a, $b] = $this->hospitalWithStaff();
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');
        $time = $this->at('10:00');

        // both sit at 10:00 — two physicians, one minute
        Sanctum::actingAs($a);
        $this->postJson('/api/v2/business/clinic-slots', ['starts_at' => $time, 'duration_minutes' => 30])->assertCreated()->assertJsonPath('data.created', 1);
        Sanctum::actingAs($b);
        $this->postJson('/api/v2/business/clinic-slots', ['starts_at' => $time, 'duration_minutes' => 30])->assertCreated()->assertJsonPath('data.created', 1);

        $this->assertSame(2, ClinicAppointmentSlot::query()->where('clinic_id', $hospital->id)->count());
        $this->assertSame((int) $a->id, (int) ClinicAppointmentSlot::query()->where('clinic_id', $hospital->id)->orderBy('id')->value('physician_id'));

        // the patient sees both physicians, with their titles, and each one's own board
        Sanctum::actingAs($patient);
        $list = collect($this->getJson("/api/v2/clinics/{$hospital->id}/physicians")->assertOk()->json('data.physicians'));
        $this->assertCount(2, $list);
        $this->assertSame('استشاري باطنة', $list->firstWhere('id', $a->id)['title']);
        $this->assertSame(1, $list->firstWhere('id', $a->id)['open_slots']);

        $mine = $this->getJson("/api/v2/clinics/{$hospital->id}/slots?physician_id={$a->id}")->assertOk()->json('data');
        $this->assertCount(1, $mine['data']);
        $this->assertSame((int) $a->id, $mine['data'][0]['physician_id']);
        $this->assertSame(1, collect($mine['days'])->sum('slots'));

        // the hospital's own (unnamed) board has none
        $this->assertCount(0, $this->getJson("/api/v2/clinics/{$hospital->id}/slots")->assertOk()->json('data.data'));
    }

    public function test_a_physician_sees_only_his_appointments_and_is_named_on_the_visit(): void
    {
        [$hospital, $a, $b] = $this->hospitalWithStaff();
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');

        Sanctum::actingAs($a);
        $this->postJson('/api/v2/business/clinic-slots', ['starts_at' => $this->at('11:00'), 'duration_minutes' => 30])->assertCreated();
        $slotId = (int) ClinicAppointmentSlot::query()->where('clinic_id', $hospital->id)->value('id');

        Sanctum::actingAs($patient);
        $appointmentId = (int) $this->postJson("/api/v2/clinic-slots/{$slotId}/book")
            ->assertCreated()
            ->assertJsonPath('data.appointment.physician.id', (int) $a->id)
            ->assertJsonPath('data.appointment.physician.title', 'استشاري باطنة')
            ->json('data.appointment.id');

        $this->assertSame((int) $a->id, (int) ClinicAppointment::query()->whereKey($appointmentId)->value('physician_id'));

        // «مواعيدي»: A sees it, B does not, the owner sees everything
        Sanctum::actingAs($a);
        $this->assertCount(1, $this->getJson('/api/v2/business/clinic-appointments')->assertOk()->json('data.data'));
        Sanctum::actingAs($b);
        $this->assertCount(0, $this->getJson('/api/v2/business/clinic-appointments')->assertOk()->json('data.data'));
        Sanctum::actingAs($hospital);
        $this->assertCount(1, $this->getJson('/api/v2/business/clinic-appointments')->assertOk()->json('data.data'));

        // …and B cannot complete a colleague's visit; A can
        Sanctum::actingAs($b);
        $this->postJson("/api/v2/business/clinic-appointments/{$appointmentId}/complete")->assertNotFound();
        Sanctum::actingAs($a);
        $this->postJson("/api/v2/business/clinic-appointments/{$appointmentId}/complete")->assertOk()->assertJsonPath('data.appointment.status', 'completed');
    }

    public function test_the_prescription_and_the_investigation_order_name_the_physician(): void
    {
        [$hospital, $a, $b] = $this->hospitalWithStaff();
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');

        Sanctum::actingAs($a);
        $this->postJson('/api/v2/business/clinic-slots', ['starts_at' => $this->at('12:00'), 'duration_minutes' => 30])->assertCreated();
        $slotId = (int) ClinicAppointmentSlot::query()->where('clinic_id', $hospital->id)->value('id');
        Sanctum::actingAs($patient);
        $appointmentId = (int) $this->postJson("/api/v2/clinic-slots/{$slotId}/book")->assertCreated()->json('data.appointment.id');

        $medicine = Medicine::create(['name' => 'Panadol ' . Str::random(4)]);
        $payload = [
            'patient_id' => $patient->id, 'appointment_id' => $appointmentId, 'diagnosis' => 'flu',
            'items' => [['medicine_id' => $medicine->id, 'dosage' => '500mg', 'quantity' => '1 box']],
        ];

        // a colleague cannot write for this visit
        Sanctum::actingAs($b);
        $this->postJson('/api/v2/prescriptions', $payload)->assertStatus(403);

        // A can: the hospital is the doctor of record AND the physician is named
        Sanctum::actingAs($a);
        $rx = $this->postJson('/api/v2/prescriptions', $payload)->assertCreated()
            ->assertJsonPath('data.prescription.physician.id', (int) $a->id)
            ->assertJsonPath('data.prescription.doctor.id', (int) $hospital->id)
            ->json('data.prescription.id');
        $this->assertSame((int) $a->id, (int) Prescription::query()->whereKey($rx)->value('physician_id'));

        // the patient reads the physician's name on it
        Sanctum::actingAs($patient);
        $this->getJson("/api/v2/prescriptions/{$rx}")->assertOk()->assertJsonPath('data.prescription.physician.name', $a->name);

        // tests ordered by him
        $option = (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', 'التحاليل الطبية')->value('o.id');
        $this->assertGreaterThan(0, $option);

        Sanctum::actingAs($a);
        $order = $this->postJson('/api/v2/investigation-orders', ['patient_id' => $patient->id, 'option_ids' => [$option]])
            ->assertCreated()
            ->assertJsonPath('data.order.physician.id', (int) $a->id)
            ->json('data.order.id');
        $this->assertSame((int) $a->id, (int) InvestigationOrder::query()->whereKey($order)->value('physician_id'));
    }

    public function test_the_owner_names_a_physician_when_booking_directly_and_only_his_own(): void
    {
        [$hospital, $a] = $this->hospitalWithStaff();
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');
        $stranger = $this->user(User::TYPE_CLIENT, null, 'Stranger');

        Sanctum::actingAs($hospital);
        $this->postJson('/api/v2/business/clinic-appointments', [
            'patient_id' => $patient->id, 'scheduled_at' => $this->at('13:00'), 'duration_minutes' => 30, 'physician_id' => $a->id,
        ])->assertCreated()->assertJsonPath('data.appointment.physician.id', (int) $a->id);

        // not one of the hospital's physicians
        $this->postJson('/api/v2/business/clinic-appointments', [
            'patient_id' => $patient->id, 'scheduled_at' => $this->at('14:00'), 'duration_minutes' => 30, 'physician_id' => $stranger->id,
        ])->assertStatus(422);
    }

    public function test_two_visits_with_one_physician_cannot_overlap_but_two_physicians_can(): void
    {
        [$hospital, $a, $b] = $this->hospitalWithStaff();
        $p1 = $this->user(User::TYPE_CLIENT, null, 'P1');
        $p2 = $this->user(User::TYPE_CLIENT, null, 'P2');
        $time = $this->at('15:00');

        Sanctum::actingAs($hospital);
        $this->postJson('/api/v2/business/clinic-appointments', ['patient_id' => $p1->id, 'scheduled_at' => $time, 'duration_minutes' => 30, 'physician_id' => $a->id])->assertCreated();
        // another physician, same minute: fine
        $this->postJson('/api/v2/business/clinic-appointments', ['patient_id' => $p2->id, 'scheduled_at' => $time, 'duration_minutes' => 30, 'physician_id' => $b->id])->assertCreated();
        // the same physician again: refused
        $this->postJson('/api/v2/business/clinic-appointments', ['patient_id' => $p2->id, 'scheduled_at' => $time, 'duration_minutes' => 30, 'physician_id' => $a->id])->assertStatus(422);
    }
}
