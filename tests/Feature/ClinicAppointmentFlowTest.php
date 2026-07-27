<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\BusinessStaff;
use App\Models\ClinicAppointment;
use App\Models\User;
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
}
