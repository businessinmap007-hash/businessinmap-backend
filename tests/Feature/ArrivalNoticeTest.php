<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\ClinicAppointment;
use App\Models\User;
use App\Services\Clinics\ClinicAppointmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «يجب التواجد قبل الموعد بـ ١٥ دقيقة» — the business's own notice, on the form, on the booking and appointment the
 * customer sees, and in the reminder.
 */
class ArrivalNoticeTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $type, string $tag): User
    {
        return User::create([
            'name' => $tag . ' ' . Str::random(4),
            'email' => strtolower($tag) . uniqid() . '@example.test',
            'password' => bcrypt('Test1234'),
            'type' => $type,
            'category_id' => 24,
            'category_child_id' => 536,
            'api_token' => Str::random(60),
            'phone' => '010' . random_int(10000000, 99999999),
        ]);
    }

    public function test_a_business_sets_its_notice_and_clears_it(): void
    {
        $business = $this->user(User::TYPE_BUSINESS, 'Notice');
        Sanctum::actingAs($business);

        $this->getJson('/api/v2/business/booking-settings/arrival-notice')
            ->assertOk()->assertJsonPath('data.message', null);

        $this->withHeaders(['Accept-Language' => 'ar'])
            ->putJson('/api/v2/business/booking-settings/arrival-notice', ['minutes' => 15, 'text' => 'أحضر بطاقتك.'])
            ->assertOk()
            ->assertJsonPath('data.minutes', 15)
            ->assertJsonPath('data.message', 'يجب التواجد قبل الموعد بـ 15 دقيقة. أحضر بطاقتك.');

        $this->putJson('/api/v2/business/booking-settings/arrival-notice', ['minutes' => 999])->assertStatus(422);

        $this->putJson('/api/v2/business/booking-settings/arrival-notice', ['minutes' => 0, 'text' => ''])
            ->assertOk()->assertJsonPath('data.message', null);
    }

    public function test_the_customer_sees_it_on_the_form_and_on_the_clinic_appointment_and_the_reminder_repeats_it(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');

        Sanctum::actingAs($clinic);
        $this->putJson('/api/v2/business/booking-settings/arrival-notice', ['minutes' => 20])->assertOk();

        Sanctum::actingAs($patient);
        $this->getJson("/api/v2/bookings/form/{$clinic->id}")
            ->assertOk()->assertJsonPath('data.arrival_notice.minutes', 20);

        $appointment = ClinicAppointment::create([
            'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'created_by' => $clinic->id,
            'scheduled_at' => Carbon::now()->addHours(6), 'duration_minutes' => 30,
            'status' => ClinicAppointment::STATUS_CONFIRMED,
        ]);

        $this->getJson('/api/v2/clinic-appointments')
            ->assertOk()->assertJsonPath('data.data.0.arrival_notice.minutes', 20);

        app(ClinicAppointmentService::class)->sendDueReminders();
        $this->assertTrue(AppNotification::query()->where('user_id', $patient->id)
            ->where('body_ar', 'like', '%يجب التواجد قبل الموعد بـ 20 دقيقة%')->exists());
    }
}
