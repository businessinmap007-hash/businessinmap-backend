<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\BusinessStaff;
use App\Models\ClinicAppointment;
use App\Models\ClinicAppointmentSlot;
use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\User;
use App\Services\Clinics\ClinicAppointmentService;
use App\Support\BusinessCapability;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Clinic appointments: a patient requests a time, the clinic confirms it
 * (never double-booking), completes it or marks a no-show; and it stays private
 * to the two parties. Delegable to a `clinic` staff member (a secretary).
 */
class ClinicAppointmentFlowTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $type, string $tag): User
    {
        $u = new User();
        $u->name = $tag . ' ' . Str::random(4);
        $u->email = strtolower($tag) . '-' . uniqid() . '@example.test';
        $u->phone = '01' . random_int(100000000, 999999999);
        $u->password = 'secret-password';
        $u->type = $type;
        $u->api_token = Str::random(80);

        // Prescription::isDoctorBusiness() gates issuing to a physician's
        // practice (عيادة) — this file issues a prescription as 'Clinic'.
        if ($tag === 'Clinic') {
            $u->category_child_id = 514;
        }

        $u->save();

        return $u;
    }

    private function soon(string $at = '+2 days 10:00'): string
    {
        return Carbon::parse($at)->format('Y-m-d H:i:s');
    }

    private function servicePrice(User $clinic, string $itemType): \App\Models\BusinessServicePrice
    {
        return \App\Models\BusinessServicePrice::create([
            'business_id' => $clinic->id,
            'child_id' => $clinic->category_child_id,
            'service_id' => 1, // booking
            'bookable_item_type' => $itemType,
            'price' => 50,
            'charge_mode' => 'standard',
            'is_active' => 1,
        ]);
    }

    public function test_patient_requests_and_clinic_confirms_then_completes(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');

        Sanctum::actingAs($patient);
        $id = $this->postJson('/api/v2/clinic-appointments', [
            'clinic_id' => $clinic->id,
            'scheduled_at' => $this->soon(),
            'reason' => 'Checkup',
        ])->assertCreated()->assertJsonPath('data.appointment.status', 'requested')->json('data.appointment.id');

        // The clinic was notified of the request.
        $this->assertTrue(AppNotification::query()->where('user_id', $clinic->id)
            ->where('notifiable_type', ClinicAppointment::class)->where('notifiable_id', $id)->exists());

        // The clinic confirms → the patient is notified.
        Sanctum::actingAs($clinic);
        $this->getJson('/api/v2/business/clinic-appointments')->assertOk()->assertJsonPath('data.data.0.id', $id);
        $this->postJson("/api/v2/business/clinic-appointments/{$id}/confirm")
            ->assertOk()->assertJsonPath('data.appointment.status', 'confirmed');
        $this->assertTrue(AppNotification::query()->where('user_id', $patient->id)
            ->where('title_ar', 'تأكيد الموعد')->exists());

        // …then completes it.
        $this->postJson("/api/v2/business/clinic-appointments/{$id}/complete")
            ->assertOk()->assertJsonPath('data.appointment.status', 'completed');
    }

    /** The patient reads the clinic's title-prefixed name, not the bare name. */
    public function test_the_clinic_is_shown_with_the_doctors_title(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $clinic->forceFill(['medical_title' => User::MEDICAL_TITLE_DOCTOR])->save();
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');

        Sanctum::actingAs($patient);
        $this->postJson('/api/v2/clinic-appointments', [
            'clinic_id' => $clinic->id,
            'scheduled_at' => $this->soon(),
        ])->assertCreated()
            ->assertJsonPath('data.appointment.clinic.name', User::MEDICAL_TITLE_DOCTOR . ' ' . $clinic->name);
    }

    public function test_the_clinic_cannot_double_book_a_confirmed_slot(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $p1 = $this->user(User::TYPE_CLIENT, 'P1');
        $p2 = $this->user(User::TYPE_CLIENT, 'P2');
        $at = $this->soon('+3 days 09:00');

        // Clinic books P1 directly (confirmed).
        Sanctum::actingAs($clinic);
        $this->postJson('/api/v2/business/clinic-appointments', [
            'patient_id' => $p1->id, 'scheduled_at' => $at, 'duration_minutes' => 30,
        ])->assertCreated();

        // P2 requests an overlapping time; confirming it must be refused.
        Sanctum::actingAs($p2);
        $id2 = $this->postJson('/api/v2/clinic-appointments', [
            'clinic_id' => $clinic->id, 'scheduled_at' => Carbon::parse($at)->addMinutes(10)->format('Y-m-d H:i:s'),
        ])->assertCreated()->json('data.appointment.id');

        Sanctum::actingAs($clinic);
        $this->postJson("/api/v2/business/clinic-appointments/{$id2}/confirm")
            ->assertStatus(422)->assertJsonValidationErrors('scheduled_at');
    }

    public function test_a_patient_cancels_and_a_stranger_cannot_see_it(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $stranger = $this->user(User::TYPE_CLIENT, 'Stranger');

        Sanctum::actingAs($patient);
        $id = $this->postJson('/api/v2/clinic-appointments', [
            'clinic_id' => $clinic->id, 'scheduled_at' => $this->soon(),
        ])->json('data.appointment.id');

        Sanctum::actingAs($stranger);
        $this->getJson("/api/v2/clinic-appointments/{$id}")->assertNotFound();

        Sanctum::actingAs($patient);
        $this->postJson("/api/v2/clinic-appointments/{$id}/cancel")
            ->assertOk()->assertJsonPath('data.appointment.status', 'cancelled');
    }

    public function test_a_past_time_is_rejected(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        Sanctum::actingAs($this->user(User::TYPE_CLIENT, 'Patient'));

        $this->postJson('/api/v2/clinic-appointments', [
            'clinic_id' => $clinic->id,
            'scheduled_at' => Carbon::parse('-1 day')->format('Y-m-d H:i:s'),
        ])->assertStatus(422)->assertJsonValidationErrors('scheduled_at');
    }

    public function test_a_clinic_delegate_manages_the_calendar(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $secretary = $this->user(User::TYPE_CLIENT, 'Secretary');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        BusinessStaff::create([
            'business_id' => $clinic->id, 'user_id' => $secretary->id,
            'capabilities' => [BusinessCapability::CLINIC], 'is_active' => true,
        ]);

        // The secretary books an appointment for the clinic.
        Sanctum::actingAs($secretary);
        $id = $this->postJson('/api/v2/business/clinic-appointments', [
            'patient_id' => $patient->id, 'scheduled_at' => $this->soon('+4 days 11:00'),
        ])->assertCreated()->json('data.appointment.id');

        $this->assertDatabaseHas('clinic_appointments', [
            'id' => $id, 'clinic_id' => $clinic->id, 'status' => 'confirmed',
        ]);
    }

    public function test_a_clinic_publishes_open_slots_and_a_patient_books_one(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $at = $this->soon('+5 days 09:00');

        // Clinic publishes two open slots (one duplicate is skipped).
        Sanctum::actingAs($clinic);
        $this->postJson('/api/v2/business/clinic-slots', [
            'slots' => [$at, $at, $this->soon('+5 days 09:30')],
            'duration_minutes' => 30,
        ])->assertCreated()->assertJsonPath('data.created', 2)->assertJsonPath('data.skipped', 1);

        // Patient sees the open slots and books the first one.
        Sanctum::actingAs($patient);
        $slotId = $this->getJson("/api/v2/clinics/{$clinic->id}/slots")
            ->assertOk()->assertJsonPath('data.data.0.starts_at', Carbon::parse($at)->toIso8601String())
            ->json('data.data.0.id');

        $apptId = $this->postJson("/api/v2/clinic-slots/{$slotId}/book")
            ->assertCreated()->assertJsonPath('data.appointment.status', 'confirmed')
            ->json('data.appointment.id');

        // The slot is now taken and the clinic was notified.
        $this->assertDatabaseHas('clinic_appointment_slots', ['id' => $slotId, 'appointment_id' => $apptId]);
        $this->assertTrue(AppNotification::query()->where('user_id', $clinic->id)
            ->where('notifiable_type', ClinicAppointment::class)->where('notifiable_id', $apptId)->exists());

        // The same slot can't be booked twice.
        $this->postJson("/api/v2/clinic-slots/{$slotId}/book")->assertStatus(422);
    }

    /** «الفتحات بدلها بمواعيد العمل السابق ضبطها» — slots sliced straight from the clinic's own configured hours. */
    public function test_a_clinic_generates_slots_from_its_own_working_hours(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $tomorrow = Carbon::tomorrow();

        \App\Models\BusinessWorkingHour::create([
            'business_id' => $clinic->id, 'day_of_week' => $tomorrow->dayOfWeek,
            'is_closed' => false, 'open_time' => '09:00:00', 'close_time' => '11:00:00',
        ]);
        // A day the clinic marked closed must generate nothing.
        \App\Models\BusinessWorkingHour::create([
            'business_id' => $clinic->id, 'day_of_week' => $tomorrow->copy()->addDay()->dayOfWeek,
            'is_closed' => true, 'open_time' => null, 'close_time' => null,
        ]);

        Sanctum::actingAs($clinic);
        $this->postJson('/api/v2/business/clinic-slots/generate-from-hours', [
            'weeks' => 1, 'interval_minutes' => 30,
        ])->assertCreated()->assertJsonPath('data.created', 4); // 09:00, 09:30, 10:00, 10:30

        $rows = $this->getJson('/api/v2/business/clinic-slots')->assertOk()->json('data.data');
        $this->assertCount(4, $rows);
        $this->assertSame(
            Carbon::parse($tomorrow->format('Y-m-d') . ' 09:00')->toIso8601String(),
            $rows[0]['starts_at'],
        );
    }

    public function test_the_patient_gets_a_day_and_a_two_hour_reminder_each_once(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $service = app(ClinicAppointmentService::class);

        $appointment = ClinicAppointment::create([
            'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'created_by' => $clinic->id,
            'scheduled_at' => Carbon::now()->addHours(6), 'duration_minutes' => 30,
            'status' => ClinicAppointment::STATUS_CONFIRMED,
        ]);

        // 6h out → only the day-before reminder fires, once.
        $this->assertSame(1, $service->sendDueReminders());
        $this->assertNotNull($appointment->fresh()->reminded_day_at);
        $this->assertNull($appointment->fresh()->reminded_soon_at);
        $this->assertSame(0, $service->sendDueReminders());

        // Only the patient is reminded — never the clinic.
        $this->assertTrue(AppNotification::query()->where('user_id', $patient->id)
            ->where('title_ar', 'تذكير بالموعد')->exists());
        $this->assertFalse(AppNotification::query()->where('user_id', $clinic->id)
            ->where('title_ar', 'تذكير بالموعد')->exists());

        // Move the appointment to 1h out → now the 2h reminder is due (once).
        $appointment->update(['scheduled_at' => Carbon::now()->addHour()]);
        $this->assertSame(1, $service->sendDueReminders());
        $this->assertNotNull($appointment->fresh()->reminded_soon_at);
        $this->assertSame(0, $service->sendDueReminders());
    }

    public function test_a_patient_reschedules_a_confirmed_appointment(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');

        // A slot-booked confirmed appointment, already reminded.
        $slot = ClinicAppointmentSlot::create([
            'clinic_id' => $clinic->id, 'starts_at' => $this->soon('+2 days 09:00'), 'duration_minutes' => 30,
        ]);
        $appointment = ClinicAppointment::create([
            'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'created_by' => $patient->id,
            'scheduled_at' => Carbon::parse($this->soon('+2 days 09:00')), 'duration_minutes' => 30,
            'status' => ClinicAppointment::STATUS_CONFIRMED, 'reminded_day_at' => Carbon::now(),
        ]);
        $slot->update(['appointment_id' => $appointment->id]);

        Sanctum::actingAs($patient);
        $this->postJson("/api/v2/clinic-appointments/{$appointment->id}/reschedule", [
            'scheduled_at' => $this->soon('+3 days 14:00'),
        ])->assertOk()->assertJsonPath('data.appointment.status', 'confirmed');

        $fresh = $appointment->fresh();
        $this->assertEquals(Carbon::parse($this->soon('+3 days 14:00')), $fresh->scheduled_at);
        $this->assertNull($fresh->reminded_day_at);            // reminders reset for the new time
        $this->assertNull($slot->fresh()->appointment_id);     // old published slot freed
        // The clinic is told the patient moved it.
        $this->assertTrue(AppNotification::query()->where('user_id', $clinic->id)
            ->where('title_ar', 'إعادة جدولة موعد')->exists());
    }

    public function test_rescheduling_onto_a_confirmed_slot_is_refused(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $p1 = $this->user(User::TYPE_CLIENT, 'P1');
        $p2 = $this->user(User::TYPE_CLIENT, 'P2');
        $taken = $this->soon('+2 days 10:00');

        // P1 holds a confirmed slot at $taken.
        ClinicAppointment::create([
            'clinic_id' => $clinic->id, 'patient_id' => $p1->id, 'created_by' => $clinic->id,
            'scheduled_at' => Carbon::parse($taken), 'duration_minutes' => 30,
            'status' => ClinicAppointment::STATUS_CONFIRMED,
        ]);
        // P2 has a confirmed appointment elsewhere and tries to move onto $taken.
        $p2Appt = ClinicAppointment::create([
            'clinic_id' => $clinic->id, 'patient_id' => $p2->id, 'created_by' => $clinic->id,
            'scheduled_at' => Carbon::parse($this->soon('+2 days 12:00')), 'duration_minutes' => 30,
            'status' => ClinicAppointment::STATUS_CONFIRMED,
        ]);

        Sanctum::actingAs($p2);
        $this->postJson("/api/v2/clinic-appointments/{$p2Appt->id}/reschedule", [
            'scheduled_at' => Carbon::parse($taken)->addMinutes(10)->format('Y-m-d H:i:s'),
        ])->assertStatus(422)->assertJsonValidationErrors('scheduled_at');
    }

    public function test_a_prescription_can_be_linked_to_the_visit(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');

        $appointment = ClinicAppointment::create([
            'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'created_by' => $clinic->id,
            'scheduled_at' => Carbon::now()->addDay(), 'duration_minutes' => 30,
            'status' => ClinicAppointment::STATUS_CONFIRMED,
        ]);

        // The clinic issues a prescription linked to the appointment.
        Sanctum::actingAs($clinic);
        $rxId = $this->postJson('/api/v2/prescriptions', [
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'items' => [['medicine_id' => Medicine::create(['name' => 'Paracetamol'])->id, 'dosage' => '500mg']],
        ])->assertCreated()->assertJsonPath('data.prescription.appointment_id', $appointment->id)
            ->json('data.prescription.id');

        // The appointment now surfaces the linked prescription id.
        Sanctum::actingAs($patient);
        $this->getJson("/api/v2/clinic-appointments/{$appointment->id}")
            ->assertOk()->assertJsonPath('data.appointment.prescription_id', (int) $rxId);

        // Same on the list the app's own "my appointments" screen actually
        // calls — the prescription relation must be loaded there too, not
        // only on show(), or the app's "view prescription" link never appears.
        $this->getJson('/api/v2/clinic-appointments')
            ->assertOk()->assertJsonPath('data.data.0.prescription_id', (int) $rxId);
    }

    public function test_a_prescription_cannot_link_to_another_clinics_appointment(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $otherClinic = $this->user(User::TYPE_BUSINESS, 'Other');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');

        $appointment = ClinicAppointment::create([
            'clinic_id' => $otherClinic->id, 'patient_id' => $patient->id, 'created_by' => $otherClinic->id,
            'scheduled_at' => Carbon::now()->addDay(), 'duration_minutes' => 30,
            'status' => ClinicAppointment::STATUS_CONFIRMED,
        ]);

        Sanctum::actingAs($clinic);
        $this->postJson('/api/v2/prescriptions', [
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'items' => [['medicine_id' => Medicine::create(['name' => 'Paracetamol'])->id]],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('prescriptions', ['appointment_id' => $appointment->id]);
    }

    public function test_a_first_time_patients_record_is_a_blank_slate(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $appointment = ClinicAppointment::create([
            'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'created_by' => $clinic->id,
            'scheduled_at' => Carbon::now()->addDay(), 'duration_minutes' => 30,
            'status' => ClinicAppointment::STATUS_CONFIRMED,
        ]);

        Sanctum::actingAs($clinic);
        $this->getJson("/api/v2/business/clinic-appointments/{$appointment->id}/patient-record")
            ->assertOk()
            ->assertJsonPath('data.is_first_visit', true)
            ->assertJsonPath('data.previous_prescription', null);
    }

    /** «افتح الروشتة والتقرير السابق» — a returning patient's latest report/prescription. */
    public function test_a_returning_patients_previous_prescription_and_report_open(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');

        $firstVisit = ClinicAppointment::create([
            'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'created_by' => $clinic->id,
            'scheduled_at' => Carbon::now()->subMonth(), 'duration_minutes' => 30,
            'status' => ClinicAppointment::STATUS_COMPLETED,
        ]);
        Sanctum::actingAs($clinic);
        $this->postJson('/api/v2/prescriptions', [
            'patient_id' => $patient->id,
            'appointment_id' => $firstVisit->id,
            'diagnosis' => 'Flu',
            'patient_condition' => 'Fever, sore throat',
            'items' => [['medicine_id' => Medicine::create(['name' => 'Paracetamol'])->id, 'dosage' => '500mg']],
        ])->assertCreated();

        $secondVisit = ClinicAppointment::create([
            'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'created_by' => $clinic->id,
            'scheduled_at' => Carbon::now()->addDay(), 'duration_minutes' => 30,
            'status' => ClinicAppointment::STATUS_CONFIRMED,
        ]);

        $this->getJson("/api/v2/business/clinic-appointments/{$secondVisit->id}/patient-record")
            ->assertOk()
            ->assertJsonPath('data.is_first_visit', false)
            ->assertJsonPath('data.previous_prescription.diagnosis', 'Flu')
            ->assertJsonPath('data.previous_prescription.patient_condition', 'Fever, sore throat')
            ->assertJsonPath('data.previous_prescription.items.0.dosage', '500mg');
    }

    /** The visit's OWN just-issued prescription is the current record, not a "previous" one. */
    public function test_the_current_visits_own_prescription_is_not_shown_as_previous(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $appointment = ClinicAppointment::create([
            'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'created_by' => $clinic->id,
            'scheduled_at' => Carbon::now()->addDay(), 'duration_minutes' => 30,
            'status' => ClinicAppointment::STATUS_CONFIRMED,
        ]);

        Sanctum::actingAs($clinic);
        $this->postJson('/api/v2/prescriptions', [
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'items' => [['medicine_id' => Medicine::create(['name' => 'Paracetamol'])->id]],
        ])->assertCreated();

        $this->getJson("/api/v2/business/clinic-appointments/{$appointment->id}/patient-record")
            ->assertOk()
            ->assertJsonPath('data.is_first_visit', true)
            ->assertJsonPath('data.previous_prescription', null);
    }

    public function test_the_patient_record_is_refused_without_the_prescriptions_capability(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $secretary = $this->user(User::TYPE_CLIENT, 'Secretary');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        BusinessStaff::create([
            'business_id' => $clinic->id, 'user_id' => $secretary->id,
            'capabilities' => [BusinessCapability::CLINIC], 'is_active' => true,
        ]);
        $appointment = ClinicAppointment::create([
            'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'created_by' => $clinic->id,
            'scheduled_at' => Carbon::now()->addDay(), 'duration_minutes' => 30,
            'status' => ClinicAppointment::STATUS_CONFIRMED,
        ]);

        Sanctum::actingAs($secretary);
        $this->getJson("/api/v2/business/clinic-appointments/{$appointment->id}/patient-record")
            ->assertStatus(403);
    }

    /** «تقدر تدخل مريض بين الحجوزات» — a walk-in joins today's queue already checked in, no slot reserved. */
    public function test_a_secretary_adds_a_walk_in_straight_into_the_queue(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');

        Sanctum::actingAs($clinic);
        $res = $this->postJson('/api/v2/business/clinic-appointments/walk-in', [
            'patient_id' => $patient->id,
        ])->assertCreated();

        $this->assertNotNull($res->json('data.appointment.checked_in_at'));
        $this->assertTrue($res->json('data.appointment.is_walk_in'));
        $this->assertSame('confirmed', $res->json('data.appointment.status'));

        $this->getJson('/api/v2/business/clinic-appointments/queue')->assertOk()
            ->assertJsonPath('data.next_id', (int) $res->json('data.appointment.id'));
    }

    /** «كل مريض له QR خاص بحجزه» — the patient's own code, scanned by the clinic to check them in. */
    public function test_the_patients_own_qr_checks_them_in(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $otherClinic = $this->user(User::TYPE_BUSINESS, 'OtherClinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');

        $appointment = ClinicAppointment::create([
            'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'created_by' => $clinic->id,
            'scheduled_at' => Carbon::now()->addHour(), 'duration_minutes' => 30,
            'status' => ClinicAppointment::STATUS_CONFIRMED,
        ]);

        Sanctum::actingAs($patient);
        $token = $this->getJson("/api/v2/clinic-appointments/{$appointment->id}/checkin-token")
            ->assertOk()->json('data.checkin_token');
        $this->assertNotEmpty($token);

        // A different clinic scanning the same token must not check it in.
        Sanctum::actingAs($otherClinic);
        $this->postJson("/api/v2/business/clinic-appointments/checkin/{$token}")->assertStatus(404);
        $this->assertNull($appointment->fresh()->checked_in_at);

        // The right clinic scans it — checked in.
        Sanctum::actingAs($clinic);
        $this->postJson("/api/v2/business/clinic-appointments/checkin/{$token}")->assertOk()
            ->assertJsonPath('data.appointment.id', $appointment->id);
        $this->assertNotNull($appointment->fresh()->checked_in_at);

        // Scanning again is a harmless no-op, not an error.
        $this->postJson("/api/v2/business/clinic-appointments/checkin/{$token}")->assertOk();
    }

    /** «1 كشف ثم 2 استشارة» — the clinic's own repeating pattern decides whose turn it is. */
    public function test_the_queue_pattern_alternates_visit_kinds(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $examPrice = $this->servicePrice($clinic, 'booking_examination');
        $consultPrice = $this->servicePrice($clinic, 'booking_consultation');

        Sanctum::actingAs($clinic);
        $this->patchJson('/api/v2/business/clinic-queue-pattern', [
            'pattern' => ['booking_examination', 'booking_consultation', 'booking_consultation'],
        ])->assertOk();

        // Two exam patients and two consultation patients all check in, exam patients first.
        $exam1 = $this->postJson('/api/v2/business/clinic-appointments/walk-in', [
            'patient_id' => $this->user(User::TYPE_CLIENT, 'Exam1')->id, 'service_price_id' => $examPrice->id,
        ])->json('data.appointment.id');
        $exam2 = $this->postJson('/api/v2/business/clinic-appointments/walk-in', [
            'patient_id' => $this->user(User::TYPE_CLIENT, 'Exam2')->id, 'service_price_id' => $examPrice->id,
        ])->json('data.appointment.id');
        $consult1 = $this->postJson('/api/v2/business/clinic-appointments/walk-in', [
            'patient_id' => $this->user(User::TYPE_CLIENT, 'Consult1')->id, 'service_price_id' => $consultPrice->id,
        ])->json('data.appointment.id');
        $consult2 = $this->postJson('/api/v2/business/clinic-appointments/walk-in', [
            'patient_id' => $this->user(User::TYPE_CLIENT, 'Consult2')->id, 'service_price_id' => $consultPrice->id,
        ])->json('data.appointment.id');

        // Nobody served yet today → pattern slot 0 → examination.
        $this->getJson('/api/v2/business/clinic-appointments/queue')->assertOk()
            ->assertJsonPath('data.next_id', $exam1)
            ->assertJsonPath('data.waiting.0.visit_kind', 'booking_examination');

        $this->postJson("/api/v2/business/clinic-appointments/{$exam1}/complete")->assertOk()
            ->assertJsonPath('data.next.id', $consult1)
            ->assertJsonPath('data.next.visit_kind', 'booking_consultation');

        $this->postJson("/api/v2/business/clinic-appointments/{$consult1}/complete")->assertOk()
            ->assertJsonPath('data.next.id', $consult2);

        // Pattern slot back to examination (served count 3 % 3 == 0) — but
        // exam2 is the only examination left waiting either way.
        $this->postJson("/api/v2/business/clinic-appointments/{$consult2}/complete")->assertOk()
            ->assertJsonPath('data.next.id', $exam2)
            ->assertJsonPath('data.next.visit_kind', 'booking_examination');
    }

    /** A kind the pattern calls for with nobody waiting doesn't block the doctor. */
    public function test_the_pattern_falls_back_when_its_kind_has_no_one_waiting(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $consultPrice = $this->servicePrice($clinic, 'booking_consultation');

        Sanctum::actingAs($clinic);
        $this->patchJson('/api/v2/business/clinic-queue-pattern', [
            'pattern' => ['booking_examination', 'booking_consultation'],
        ])->assertOk();

        // Only a consultation patient is waiting — the pattern wants an
        // examination first, but nobody's here for one.
        $only = $this->postJson('/api/v2/business/clinic-appointments/walk-in', [
            'patient_id' => $this->user(User::TYPE_CLIENT, 'OnlyOne')->id, 'service_price_id' => $consultPrice->id,
        ])->json('data.appointment.id');

        $this->getJson('/api/v2/business/clinic-appointments/queue')->assertOk()
            ->assertJsonPath('data.next_id', $only);
    }

    /** «تقدر تدخل غيره لحد ما يحضر» — calling someone specific overrides the pattern, without touching the one skipped. */
    public function test_calling_a_patient_now_overrides_the_pattern(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $examPrice = $this->servicePrice($clinic, 'booking_examination');

        Sanctum::actingAs($clinic);
        $this->patchJson('/api/v2/business/clinic-queue-pattern', ['pattern' => ['booking_examination']])->assertOk();

        $lateComer = $this->postJson('/api/v2/business/clinic-appointments/walk-in', [
            'patient_id' => $this->user(User::TYPE_CLIENT, 'LateComer')->id, 'service_price_id' => $examPrice->id,
        ])->json('data.appointment.id');
        $steppedOut = $this->postJson('/api/v2/business/clinic-appointments/walk-in', [
            'patient_id' => $this->user(User::TYPE_CLIENT, 'SteppedOut')->id, 'service_price_id' => $examPrice->id,
        ])->json('data.appointment.id');

        // The pattern would call $lateComer first (earliest checked_in_at) —
        // the secretary instead calls the other one now.
        $this->postJson("/api/v2/business/clinic-appointments/{$steppedOut}/call-now")->assertOk();

        $this->getJson('/api/v2/business/clinic-appointments/queue')->assertOk()
            ->assertJsonPath('data.next_id', $steppedOut);

        // $lateComer is untouched — still waiting, still checked in.
        $this->assertNotNull(ClinicAppointment::query()->findOrFail($lateComer)->checked_in_at);
        $this->assertSame('confirmed', ClinicAppointment::query()->findOrFail($lateComer)->status);
    }
}
