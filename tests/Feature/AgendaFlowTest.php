<?php

namespace Tests\Feature;

use App\Models\AgendaItem;
use App\Models\ClinicAppointment;
use App\Models\MealSchedule;
use App\Models\Prescription;
use App\Models\User;
use App\Services\Agenda\AgendaService;
use App\Services\Agenda\MedicationScheduleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The personal agenda: a user's unified day, cross-service conflict (a clinic
 * appointment can't be booked over another commitment), self-added tasks, meal
 * times, and prescription doses scheduled onto the agenda as reminders.
 */
class AgendaFlowTest extends TestCase
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

    public function test_a_user_adds_a_task_and_reads_their_day(): void
    {
        $user = $this->user(User::TYPE_CLIENT, 'User');
        Sanctum::actingAs($user);

        $at = Carbon::tomorrow()->setTime(9, 0);
        $id = $this->postJson('/api/v2/agenda', [
            'title' => 'مذاكرة', 'starts_at' => $at->format('Y-m-d H:i:s'),
        ])->assertCreated()->json('data.item.id');

        $this->getJson('/api/v2/agenda?date=' . $at->toDateString())
            ->assertOk()
            ->assertJsonPath('data.items.0.id', (int) $id)
            ->assertJsonPath('data.items.0.kind', 'personal');
    }

    public function test_a_clinic_appointment_cannot_be_booked_over_another_commitment(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $at = Carbon::tomorrow()->setTime(10, 0);

        // The patient blocks 10:00–10:45 with a personal task.
        Sanctum::actingAs($patient);
        $this->postJson('/api/v2/agenda', [
            'title' => 'اجتماع', 'starts_at' => $at->format('Y-m-d H:i:s'),
            'ends_at' => $at->copy()->addMinutes(45)->format('Y-m-d H:i:s'),
        ])->assertCreated();

        // The clinic tries to book the patient at an overlapping 10:15 → refused.
        Sanctum::actingAs($clinic);
        $this->postJson('/api/v2/business/clinic-appointments', [
            'patient_id' => $patient->id,
            'scheduled_at' => $at->copy()->addMinutes(15)->format('Y-m-d H:i:s'),
        ])->assertStatus(422)->assertJsonValidationErrors('scheduled_at');

        // A non-overlapping 11:00 is fine, and it lands on the patient's agenda.
        $this->postJson('/api/v2/business/clinic-appointments', [
            'patient_id' => $patient->id,
            'scheduled_at' => $at->copy()->addHour()->format('Y-m-d H:i:s'),
        ])->assertCreated();

        $this->assertDatabaseHas('agenda_items', [
            'user_id' => $patient->id, 'kind' => 'appointment', 'status' => 'active',
        ]);
    }

    public function test_completing_an_appointment_closes_its_agenda_item(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');

        Sanctum::actingAs($clinic);
        $id = $this->postJson('/api/v2/business/clinic-appointments', [
            'patient_id' => $patient->id,
            'scheduled_at' => Carbon::tomorrow()->setTime(12, 0)->format('Y-m-d H:i:s'),
        ])->assertCreated()->json('data.appointment.id');

        $this->postJson("/api/v2/business/clinic-appointments/{$id}/complete")->assertOk();

        $this->assertDatabaseHas('agenda_items', [
            'source_type' => (new ClinicAppointment())->getMorphClass(),
            'source_id' => $id, 'status' => 'done',
        ]);
    }

    public function test_the_clinic_reschedules_an_appointment(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');

        Sanctum::actingAs($clinic);
        $id = $this->postJson('/api/v2/business/clinic-appointments', [
            'patient_id' => $patient->id,
            'scheduled_at' => Carbon::tomorrow()->setTime(9, 0)->format('Y-m-d H:i:s'),
        ])->assertCreated()->json('data.appointment.id');

        $newAt = Carbon::tomorrow()->addDay()->setTime(15, 0);
        $this->postJson("/api/v2/business/clinic-appointments/{$id}/reschedule", [
            'scheduled_at' => $newAt->format('Y-m-d H:i:s'),
        ])->assertOk()->assertJsonPath('data.appointment.status', 'confirmed');

        // The agenda item moved with it, and the patient was told.
        $this->assertDatabaseHas('agenda_items', [
            'source_id' => $id, 'starts_at' => $newAt->format('Y-m-d H:i:s'),
        ]);
        $this->assertTrue(\App\Models\AppNotification::query()->where('user_id', $patient->id)
            ->where('title_ar', 'تغيير موعدك')->exists());
    }

    public function test_meal_times_are_saved_and_read_back(): void
    {
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        Sanctum::actingAs($patient);

        $this->putJson('/api/v2/me/meal-times', [
            'breakfast_at' => '07:30', 'lunch_at' => '13:00', 'dinner_at' => '21:00',
        ])->assertOk()->assertJsonPath('data.meal_times.dinner_at', '21:00');

        $this->getJson('/api/v2/me/meal-times')
            ->assertOk()->assertJsonPath('data.meal_times.breakfast_at', '07:30');
    }

    public function test_a_prescription_dose_is_scheduled_onto_the_agenda(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');

        // A late dinner time guarantees the "after dinner" dose is still today/future.
        MealSchedule::create([
            'user_id' => $patient->id, 'breakfast_at' => '08:00', 'lunch_at' => '14:00',
            'dinner_at' => Carbon::now()->addHours(2)->format('H:i:s'),
        ]);

        // Doctor issues a once-a-day-after-dinner medicine for 3 days.
        Sanctum::actingAs($clinic);
        $rxId = $this->postJson('/api/v2/prescriptions', [
            'patient_id' => $patient->id,
            'items' => [[
                'name' => 'Antibiotic', 'dosage' => '250mg',
                'frequency_per_day' => 1, 'food_timing' => 'after',
                'time_slots' => ['dinner'], 'duration_days' => 3,
            ]],
        ])->assertCreated()->json('data.prescription.id');

        // The patient schedules the reminders → 3 medication items appear.
        Sanctum::actingAs($patient);
        $this->postJson("/api/v2/prescriptions/{$rxId}/schedule-reminders")
            ->assertOk()->assertJsonPath('data.reminders', 3);

        $this->assertSame(3, AgendaItem::query()
            ->where('user_id', $patient->id)->where('kind', 'medication')->count());

        // A due medication reminder is pushed once.
        $item = AgendaItem::query()->where('user_id', $patient->id)->where('kind', 'medication')
            ->orderBy('starts_at')->first();
        $item->update(['starts_at' => Carbon::now()->subMinute()]);

        $this->assertSame(1, app(AgendaService::class)->sendDueReminders());
        $this->assertNotNull($item->fresh()->reminded_at);
        $this->assertTrue(\App\Models\AppNotification::query()->where('user_id', $patient->id)
            ->where('title_ar', 'تذكير بالدواء')->exists());
    }

    public function test_a_service_booking_lands_on_the_agenda_and_blocks_a_clinic(): void
    {
        $restaurant = $this->user(User::TYPE_BUSINESS, 'Restaurant');
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $at = Carbon::tomorrow()->setTime(20, 0);

        // A restaurant booking mirrors onto the customer's agenda via the observer.
        \App\Models\Booking::create([
            'user_id' => $patient->id, 'business_id' => $restaurant->id,
            'status' => \App\Models\Booking::STATUS_ACCEPTED,
            'date' => $at->toDateString(), 'time' => $at->format('H:i'),
            'starts_at' => $at, 'ends_at' => $at->copy()->addHours(2),
        ]);

        $this->assertDatabaseHas('agenda_items', [
            'user_id' => $patient->id, 'kind' => 'booking', 'status' => 'active',
        ]);

        // The clinic cannot book the patient during dinner.
        Sanctum::actingAs($clinic);
        $this->postJson('/api/v2/business/clinic-appointments', [
            'patient_id' => $patient->id,
            'scheduled_at' => $at->copy()->addMinutes(30)->format('Y-m-d H:i:s'),
        ])->assertStatus(422)->assertJsonValidationErrors('scheduled_at');
    }

    public function test_a_service_booking_is_refused_over_a_clinic_appointment(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $restaurant = $this->user(User::TYPE_BUSINESS, 'Restaurant');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $at = Carbon::tomorrow()->setTime(18, 0);

        // A confirmed clinic appointment occupies 18:00 on the patient's agenda.
        Sanctum::actingAs($clinic);
        $this->postJson('/api/v2/business/clinic-appointments', [
            'patient_id' => $patient->id, 'scheduled_at' => $at->format('Y-m-d H:i:s'),
        ])->assertCreated();

        // Booking a service that overlaps 18:00 is now refused (mutual guard, fired
        // before any pricing work).
        Sanctum::actingAs($patient);
        $this->postJson('/api/v2/bookings', [
            'business_id' => $restaurant->id,
            'service_id' => 1,
            'starts_at' => $at->copy()->addMinutes(15)->format('Y-m-d H:i:s'),
            'ends_at' => $at->copy()->addHour()->format('Y-m-d H:i:s'),
        ])->assertStatus(422)->assertJsonValidationErrors('starts_at');
    }
}
