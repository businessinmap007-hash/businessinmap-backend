<?php

namespace Tests\Feature;

use App\Models\AgendaFeedToken;
use App\Models\AgendaItem;
use App\Models\ClinicAppointment;
use App\Models\ClinicAppointmentSlot;
use App\Models\MealSchedule;
use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\ReminderPreference;
use App\Models\User;
use App\Services\Agenda\AgendaService;
use App\Services\Clinics\ClinicAppointmentService;
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

    public function test_the_week_view_returns_seven_days_with_the_task_on_its_day(): void
    {
        $user = $this->user(User::TYPE_CLIENT, 'User');
        Sanctum::actingAs($user);

        // A task next Tuesday 09:00.
        $tuesday = Carbon::today()->next(Carbon::TUESDAY)->setTime(9, 0);
        $this->postJson('/api/v2/agenda', [
            'title' => 'مهمة', 'starts_at' => $tuesday->format('Y-m-d H:i:s'),
        ])->assertCreated();

        // The week containing that Tuesday: 7 days from its Saturday, task on day.
        $res = $this->getJson('/api/v2/agenda/week?date=' . $tuesday->toDateString())
            ->assertOk()->json('data');

        $this->assertCount(7, $res['days']);
        $this->assertSame($tuesday->copy()->startOfWeek(Carbon::SATURDAY)->toDateString(), $res['from']);

        $tuesdayCell = collect($res['days'])->firstWhere('date', $tuesday->toDateString());
        $this->assertCount(1, $tuesdayCell['items']);
        $this->assertSame('مهمة', $tuesdayCell['items'][0]['title']);
    }

    public function test_the_agenda_exports_as_an_ics_file(): void
    {
        $user = $this->user(User::TYPE_CLIENT, 'User');
        Sanctum::actingAs($user);

        $at = Carbon::tomorrow()->setTime(9, 0);
        $this->postJson('/api/v2/agenda', [
            'title' => 'Dentist; visit',
            'starts_at' => $at->format('Y-m-d H:i:s'),
            'ends_at' => $at->copy()->addMinutes(30)->format('Y-m-d H:i:s'),
        ])->assertCreated();

        $res = $this->get('/api/v2/agenda/export.ics')
            ->assertOk()
            ->assertHeader('content-type', 'text/calendar; charset=utf-8');

        $body = $res->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $body);
        $this->assertStringContainsString('BEGIN:VEVENT', $body);
        $this->assertStringContainsString('UID:agenda-', $body);
        // The semicolon in the title is escaped per RFC 5545.
        $this->assertStringContainsString('SUMMARY:Dentist\\; visit', $body);
        // DTSTART is emitted in UTC (Z suffix).
        $this->assertMatchesRegularExpression('/DTSTART:\d{8}T\d{6}Z/', $body);
        $this->assertStringContainsString('END:VCALENDAR', $body);
    }

    public function test_the_public_calendar_feed_serves_the_users_agenda(): void
    {
        $user = $this->user(User::TYPE_CLIENT, 'User');
        AgendaItem::create([
            'user_id' => $user->id, 'kind' => AgendaItem::KIND_PERSONAL, 'title' => 'FeedTask',
            'starts_at' => Carbon::tomorrow()->setTime(9, 0), 'ends_at' => Carbon::tomorrow()->setTime(9, 30),
            'blocking' => true, 'status' => AgendaItem::STATUS_ACTIVE,
        ]);
        $token = AgendaFeedToken::forUser($user->id)->token;

        // No authentication — the token in the path is the only credential.
        $body = $this->get("/api/v2/agenda/feed/{$token}.ics")
            ->assertOk()
            ->assertHeader('content-type', 'text/calendar; charset=utf-8')
            ->getContent();
        $this->assertStringContainsString('SUMMARY:FeedTask', $body);

        // An unknown token returns an empty calendar (200), never an error — so a
        // subscriber cannot probe which tokens exist.
        $this->get('/api/v2/agenda/feed/deadbeefdeadbeef.ics')
            ->assertOk()
            ->assertDontSee('BEGIN:VEVENT');
    }

    public function test_rotating_the_feed_url_invalidates_the_old_one(): void
    {
        $user = $this->user(User::TYPE_CLIENT, 'User');
        AgendaItem::create([
            'user_id' => $user->id, 'kind' => AgendaItem::KIND_PERSONAL, 'title' => 'FeedTask',
            'starts_at' => Carbon::tomorrow()->setTime(9, 0), 'ends_at' => Carbon::tomorrow()->setTime(9, 30),
            'blocking' => true, 'status' => AgendaItem::STATUS_ACTIVE,
        ]);

        Sanctum::actingAs($user);
        $oldUrl = $this->getJson('/api/v2/me/agenda-feed')->assertOk()->json('data.url');
        $oldPath = parse_url($oldUrl, PHP_URL_PATH);

        $newUrl = $this->postJson('/api/v2/me/agenda-feed/rotate')->assertOk()->json('data.url');
        $this->assertNotSame($oldUrl, $newUrl);

        // The old URL no longer resolves to the agenda; the new one does.
        $this->get($oldPath)->assertOk()->assertDontSee('BEGIN:VEVENT');
        $this->get(parse_url($newUrl, PHP_URL_PATH))->assertOk()->assertSee('SUMMARY:FeedTask');
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

        // The clock is frozen rather than offset. «now + 2h» was meant to keep
        // the "after dinner" dose in the future, but it is formatted as H:i:s
        // and loses the date — so between 22:00 and midnight UTC it wrapped to
        // an early-morning time TODAY, 22 hours in the past, today's dose was
        // skipped and only 2 of the 3 appeared. The test failed for two hours a
        // day and passed the other twenty-two.
        Carbon::setTestNow(Carbon::today()->setTime(9, 0));

        MealSchedule::create([
            'user_id' => $patient->id, 'breakfast_at' => '08:00', 'lunch_at' => '14:00',
            'dinner_at' => '20:00',
        ]);

        // Doctor issues a once-a-day-after-dinner medicine for 3 days.
        Sanctum::actingAs($clinic);
        $rxId = $this->postJson('/api/v2/prescriptions', [
            'patient_id' => $patient->id,
            'items' => [[
                'medicine_id' => Medicine::create(['name' => 'Antibiotic'])->id, 'dosage' => '250mg',
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

    public function test_a_recurring_weekly_task_skips_a_clashing_day(): void
    {
        // Pin "now" to a Wednesday so the 3-week horizon holds exactly 3 Mondays.
        Carbon::setTestNow(Carbon::today()->next(Carbon::WEDNESDAY)->setTime(8, 0));

        try {
            $user = $this->user(User::TYPE_CLIENT, 'User');
            Sanctum::actingAs($user);

            // Pre-block the first upcoming Monday 09:00–09:30 with a one-off task.
            $monday = Carbon::today()->next(Carbon::MONDAY)->setTime(9, 0);
            $this->postJson('/api/v2/agenda', [
                'title' => 'ثابت', 'starts_at' => $monday->format('Y-m-d H:i:s'),
            ])->assertCreated();

            // Weekly 09:00 Monday for 3 weeks → 3 Mondays, but the pre-blocked one
            // is skipped, so only 2 land.
            $this->postJson('/api/v2/agenda/recurring', [
                'title' => 'تمرين', 'start_time' => '09:00', 'frequency' => 'weekly',
                'weekdays' => [Carbon::MONDAY], 'weeks' => 3,
            ])->assertCreated()
                ->assertJsonPath('data.created', 2)
                ->assertJsonPath('data.skipped', 1);

            $this->assertSame(3, AgendaItem::query()->where('user_id', $user->id)
                ->where('kind', 'personal')->where('status', 'active')->count());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_a_clinic_generates_a_weekly_slot_grid(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        Sanctum::actingAs($clinic);

        // Sundays & Tuesdays, two times, one week → up to 4 slots.
        $res = $this->postJson('/api/v2/business/clinic-slots/generate', [
            'weekdays' => [Carbon::SUNDAY, Carbon::TUESDAY],
            'times' => ['10:00', '10:30'],
            'weeks' => 1,
            'duration_minutes' => 30,
        ])->assertCreated()->json('data');

        $this->assertGreaterThan(0, $res['created']);
        $this->assertSame($res['created'], ClinicAppointmentSlot::query()
            ->where('clinic_id', $clinic->id)->count());

        // Every generated slot falls on a selected weekday at a listed time.
        ClinicAppointmentSlot::query()->where('clinic_id', $clinic->id)->get()
            ->each(function ($s) {
                $this->assertContains($s->starts_at->dayOfWeek, [Carbon::SUNDAY, Carbon::TUESDAY]);
                $this->assertContains($s->starts_at->format('H:i'), ['10:00', '10:30']);
            });
    }

    public function test_a_custom_lead_delays_the_appointment_reminder(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $service = app(ClinicAppointmentService::class);

        // The patient wants only a 60-minutes-before reminder (second disabled).
        ReminderPreference::create([
            'user_id' => $patient->id, 'appointment_first_lead_minutes' => 60,
            'appointment_second_lead_minutes' => null, 'agenda_lead_minutes' => 0,
        ]);

        // 3h out: with the default 24h lead this would fire, but not with 60 min.
        $appt = ClinicAppointment::create([
            'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'created_by' => $clinic->id,
            'scheduled_at' => Carbon::now()->addHours(3), 'duration_minutes' => 30,
            'status' => ClinicAppointment::STATUS_CONFIRMED,
        ]);
        $this->assertSame(0, $service->sendDueReminders());
        $this->assertNull($appt->fresh()->reminded_day_at);

        // Move it to 30 min out → now within the 60-minute lead → fires once.
        $appt->update(['scheduled_at' => Carbon::now()->addMinutes(30)]);
        $this->assertSame(1, $service->sendDueReminders());
        $this->assertNotNull($appt->fresh()->reminded_day_at);
        // Second is disabled → soon marker stays null even though it's close.
        $this->assertNull($appt->fresh()->reminded_soon_at);
        $this->assertSame(0, $service->sendDueReminders());
    }

    public function test_an_agenda_lead_reminds_before_the_item_time(): void
    {
        $user = $this->user(User::TYPE_CLIENT, 'User');
        $service = app(AgendaService::class);

        // Remind 15 minutes before an agenda item.
        ReminderPreference::create(['user_id' => $user->id, 'agenda_lead_minutes' => 15]);

        // A dose 10 minutes from now is already within the 15-minute lead → due.
        AgendaItem::create([
            'user_id' => $user->id, 'kind' => AgendaItem::KIND_MEDICATION, 'title' => 'Dose',
            'starts_at' => Carbon::now()->addMinutes(10), 'blocking' => false,
            'status' => AgendaItem::STATUS_ACTIVE, 'remind' => true,
        ]);

        $this->assertSame(1, $service->sendDueReminders());
    }

    public function test_reminder_preferences_are_saved_and_validated(): void
    {
        $user = $this->user(User::TYPE_CLIENT, 'User');
        Sanctum::actingAs($user);

        $this->putJson('/api/v2/me/reminder-preferences', [
            'appointment_first_lead_minutes' => 720,
            'appointment_second_lead_minutes' => 30,
            'agenda_lead_minutes' => 10,
        ])->assertOk()->assertJsonPath('data.reminder_preferences.appointment_first_lead_minutes', 720);

        // The second reminder can't be as far out as the first.
        $this->putJson('/api/v2/me/reminder-preferences', [
            'appointment_first_lead_minutes' => 60,
            'appointment_second_lead_minutes' => 120,
            'agenda_lead_minutes' => 0,
        ])->assertStatus(422);

        $this->getJson('/api/v2/me/reminder-preferences')
            ->assertOk()->assertJsonPath('data.reminder_preferences.appointment_second_lead_minutes', 30);
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
