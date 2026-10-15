<?php

namespace Tests\Feature;

use App\Models\ClinicAppointment;
use App\Models\User;
use App\Support\BusinessCapability;
use App\Support\WallClock;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The OPTIONAL arrival QR of a clinic / hospital: the business turns it on, the patient scans it, and only then is his
 * turn activated in the queue. Rolls back.
 */
class ClinicArrivalQrTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $type, string $tag): User
    {
        $u = new User();
        $u->name = $tag.' '.Str::random(4);
        $u->email = strtolower($tag).'-'.uniqid().'@example.test';
        $u->phone = '01'.random_int(100000000, 999999999);
        $u->password = 'secret-password';
        $u->type = $type;
        $u->api_token = Str::random(80);
        $u->save();

        return $u;
    }

    private function appointment(User $clinic, User $patient, $at = null): ClinicAppointment
    {
        return ClinicAppointment::create([
            'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'created_by' => $clinic->id,
            'scheduled_at' => $at ?? WallClock::now()->addHour(), 'duration_minutes' => 30,
            'status' => ClinicAppointment::STATUS_CONFIRMED,
        ]);
    }

    public function test_the_qr_is_off_until_the_clinic_turns_it_on_and_a_scan_activates_the_turn(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $appointment = $this->appointment($clinic, $patient);

        // off by default: no token to scan, and a guessed one is refused
        Sanctum::actingAs($patient);
        $this->postJson('/api/v2/clinic-appointments/arrive', ['code' => 'whatever'])->assertNotFound();

        // the clinic (a member with the clinic capability) turns it on
        $clinic->forceFill(['arrival_qr_enabled' => true])->save();
        $token = (string) app(\App\Services\Clinics\ClinicAppointmentService::class)->arrivalQr($clinic)['token'];
        $this->assertSame(24, strlen($token));

        $this->assertNull($appointment->fresh()->checked_in_at);

        Sanctum::actingAs($patient);
        $this->postJson('/api/v2/clinic-appointments/arrive', ['code' => $token])->assertOk();
        $this->assertNotNull($appointment->fresh()->checked_in_at);

        // scanning twice is not an error
        $this->postJson('/api/v2/clinic-appointments/arrive', ['code' => $token])->assertOk();
    }

    public function test_a_patient_with_no_appointment_today_is_not_activated_and_a_rotated_code_kills_the_old_one(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $this->appointment($clinic, $patient, WallClock::now()->addDays(3));

        $svc = app(\App\Services\Clinics\ClinicAppointmentService::class);
        $old = (string) $svc->arrivalQr($clinic, true)['token'];

        Sanctum::actingAs($patient);
        $this->postJson('/api/v2/clinic-appointments/arrive', ['code' => $old])->assertStatus(422);

        $new = (string) $svc->arrivalQr($clinic->fresh(), true, true)['token'];
        $this->assertNotSame($old, $new);
        $this->postJson('/api/v2/clinic-appointments/arrive', ['code' => $old])->assertNotFound();
    }

    public function test_the_optional_day_closure_refuses_new_bookings_for_that_date_only_until_it_is_reopened(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $day = WallClock::now()->addDays(2)->setTime(10, 0);

        $svc = app(\App\Services\Clinics\ClinicAppointmentService::class);
        $this->assertSame($day->toDateString(), $svc->setDayClosure($clinic, true, $day->toDateString())['closed_date']);

        Sanctum::actingAs($patient);
        $this->postJson('/api/v2/clinic-appointments', ['clinic_id' => $clinic->id, 'scheduled_at' => $day->format('Y-m-d H:i:s')])
            ->assertStatus(422)->assertJsonValidationErrors('scheduled_at');

        // another day is untouched, and the same button reopens it
        $this->postJson('/api/v2/clinic-appointments', [
            'clinic_id' => $clinic->id, 'scheduled_at' => $day->copy()->addDay()->format('Y-m-d H:i:s'),
        ])->assertCreated();

        $this->assertNull($svc->setDayClosure($clinic->fresh(), false)['closed_date']);
        $this->postJson('/api/v2/clinic-appointments', ['clinic_id' => $clinic->id, 'scheduled_at' => $day->format('Y-m-d H:i:s')])
            ->assertCreated();
    }
}
