<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\BusinessStaff;
use App\Models\ClinicAppointment;
use App\Models\ClinicAppointmentSlot;
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
        $u->save();

        return $u;
    }

    private function soon(string $at = '+2 days 10:00'): string
    {
        return Carbon::parse($at)->format('Y-m-d H:i:s');
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
            'items' => [['name' => 'Paracetamol', 'dosage' => '500mg']],
        ])->assertCreated()->assertJsonPath('data.prescription.appointment_id', $appointment->id)
            ->json('data.prescription.id');

        // The appointment now surfaces the linked prescription id.
        Sanctum::actingAs($patient);
        $this->getJson("/api/v2/clinic-appointments/{$appointment->id}")
            ->assertOk()->assertJsonPath('data.appointment.prescription_id', (int) $rxId);
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
            'items' => [['name' => 'Paracetamol']],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('prescriptions', ['appointment_id' => $appointment->id]);
    }
}
