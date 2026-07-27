<?php

namespace App\Services\Clinics;

use App\Models\AgendaItem;
use App\Models\ClinicAppointment;
use App\Models\ClinicAppointmentSlot;
use App\Models\ReminderPreference;
use App\Models\User;
use App\Services\Agenda\AgendaService;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Clinic appointment lifecycle: a patient requests a time, the clinic confirms
 * (or the clinic books directly) — never double-booking a confirmed slot — then
 * completes it or marks a no-show. Each hand-off pushes a notification.
 */
class ClinicAppointmentService
{
    public function __construct(
        private readonly NotificationDispatcherService $notifications,
        private readonly AgendaService $agenda,
    ) {
    }

    /** Patient requests an appointment at a time; the clinic will confirm it. */
    public function request(User $patient, User $clinic, array $data): ClinicAppointment
    {
        $appointment = ClinicAppointment::create([
            'clinic_id' => (int) $clinic->id,
            'patient_id' => (int) $patient->id,
            'created_by' => (int) $patient->id,
            'scheduled_at' => $data['scheduled_at'],
            'duration_minutes' => $data['duration_minutes'] ?? 30,
            'status' => ClinicAppointment::STATUS_REQUESTED,
            'reason' => $data['reason'] ?? null,
        ]);

        $this->notify('appointment_requested', (int) $clinic->id, $appointment,
            'طلب موعد جديد', 'New appointment request',
            'طلب مريض موعدًا في ' . $appointment->scheduled_at->format('Y-m-d H:i') . '.',
            'A patient requested an appointment on ' . $appointment->scheduled_at->format('Y-m-d H:i') . '.');

        return $appointment;
    }

    /** Clinic books an appointment directly for a patient (confirmed at once). */
    public function bookByClinic(User $clinic, User $patient, array $data): ClinicAppointment
    {
        $start = Carbon::parse($data['scheduled_at']);
        $duration = (int) ($data['duration_minutes'] ?? 30);
        $this->assertNoConflict((int) $clinic->id, $start, $duration, null);
        $this->agenda->assertFree((int) $patient->id, $start, $start->copy()->addMinutes($duration));

        $appointment = ClinicAppointment::create([
            'clinic_id' => (int) $clinic->id,
            'patient_id' => (int) $patient->id,
            'created_by' => (int) $clinic->id,
            'scheduled_at' => $start,
            'duration_minutes' => $duration,
            'status' => ClinicAppointment::STATUS_CONFIRMED,
            'reason' => $data['reason'] ?? null,
        ]);

        $this->syncAgenda($appointment);
        $this->notifyConfirmed($appointment);

        return $appointment;
    }

    /** Clinic confirms a requested appointment (guarding against overlap). */
    public function confirm(ClinicAppointment $appointment): ClinicAppointment
    {
        if ($appointment->status !== ClinicAppointment::STATUS_REQUESTED) {
            throw ValidationException::withMessages(['status' => __('لا يمكن تأكيد هذا الموعد في حالته الحالية.')]);
        }

        $this->assertNoConflict(
            (int) $appointment->clinic_id,
            $appointment->scheduled_at,
            (int) $appointment->duration_minutes,
            (int) $appointment->id,
        );
        $this->agenda->assertFree(
            (int) $appointment->patient_id,
            $appointment->scheduled_at,
            $appointment->scheduled_at->copy()->addMinutes((int) $appointment->duration_minutes),
        );

        $appointment->update(['status' => ClinicAppointment::STATUS_CONFIRMED]);
        $this->syncAgenda($appointment);
        $this->notifyConfirmed($appointment);

        return $appointment;
    }

    public function reject(ClinicAppointment $appointment): ClinicAppointment
    {
        $this->transition($appointment, [ClinicAppointment::STATUS_REQUESTED, ClinicAppointment::STATUS_CONFIRMED], ClinicAppointment::STATUS_CANCELLED);
        $this->agenda->closeForSource($appointment, AgendaItem::STATUS_CANCELLED);

        return $appointment;
    }

    public function complete(ClinicAppointment $appointment): ClinicAppointment
    {
        $this->transition($appointment, [ClinicAppointment::STATUS_CONFIRMED], ClinicAppointment::STATUS_COMPLETED);
        $this->agenda->closeForSource($appointment, AgendaItem::STATUS_DONE);

        return $appointment;
    }

    public function noShow(ClinicAppointment $appointment): ClinicAppointment
    {
        $this->transition($appointment, [ClinicAppointment::STATUS_CONFIRMED], ClinicAppointment::STATUS_NO_SHOW);
        $this->agenda->closeForSource($appointment, AgendaItem::STATUS_DONE);

        return $appointment;
    }

    /** Patient cancels, as long as it has not already happened/closed. */
    public function cancelByPatient(ClinicAppointment $appointment): ClinicAppointment
    {
        if (! in_array($appointment->status, ClinicAppointment::ACTIVE_STATUSES, true)) {
            throw ValidationException::withMessages(['status' => __('لا يمكن إلغاء هذا الموعد.')]);
        }

        $appointment->update(['status' => ClinicAppointment::STATUS_CANCELLED]);
        $this->agenda->closeForSource($appointment, AgendaItem::STATUS_CANCELLED);

        return $appointment;
    }

    /**
     * Clinic publishes one open slot. Silently skips (returns null) a duplicate
     * start time — the (clinic_id, starts_at) unique key makes republishing safe.
     */
    public function publishSlot(User $clinic, Carbon $start, int $duration = 30): ?ClinicAppointmentSlot
    {
        $slot = ClinicAppointmentSlot::query()->firstOrCreate(
            ['clinic_id' => (int) $clinic->id, 'starts_at' => $start],
            ['duration_minutes' => $duration, 'created_by' => (int) $clinic->id],
        );

        return $slot->wasRecentlyCreated ? $slot : null;
    }

    /**
     * Publish a recurring weekly grid of slots in one go: for each future date in
     * the next `$days` whose weekday (Carbon 0=Sun..6=Sat) is selected, open a slot
     * at every listed time. Duplicates are skipped. Returns [created, skipped].
     */
    public function generateSlots(User $clinic, array $weekdays, array $times, int $days, int $duration = 30): array
    {
        $created = 0;
        $skipped = 0;
        $today = Carbon::today();

        for ($d = 0; $d <= $days; $d++) {
            $date = $today->copy()->addDays($d);
            if (! in_array($date->dayOfWeek, $weekdays, true)) {
                continue;
            }

            foreach ($times as $time) {
                [$h, $m] = array_map('intval', explode(':', $time));
                $at = $date->copy()->setTime($h, $m, 0);
                if ($at->isPast()) {
                    continue;
                }

                $this->publishSlot($clinic, $at, $duration) ? $created++ : $skipped++;
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /** Clinic deletes an open (unbooked) slot; a booked one can't be removed here. */
    public function deleteSlot(ClinicAppointmentSlot $slot): void
    {
        if ($slot->appointment_id !== null) {
            throw ValidationException::withMessages(['slot' => __('لا يمكن حذف فتحة محجوزة.')]);
        }

        $slot->delete();
    }

    /**
     * Patient books an open published slot in one tap. Because the clinic offered
     * the slot, the resulting appointment is confirmed at once (overlap-guarded),
     * the slot is marked taken, and the clinic is notified.
     */
    public function bookSlot(User $patient, ClinicAppointmentSlot $slot, array $data = []): ClinicAppointment
    {
        return DB::transaction(function () use ($patient, $slot, $data) {
            // Lock the row so two patients can't take the same slot at once.
            $slot = ClinicAppointmentSlot::query()->lockForUpdate()->findOrFail($slot->id);

            if (! $slot->isOpen()) {
                throw ValidationException::withMessages(['slot' => __('هذه الفتحة لم تعد متاحة.')]);
            }

            $this->assertNoConflict((int) $slot->clinic_id, $slot->starts_at, (int) $slot->duration_minutes, null);
            $this->agenda->assertFree((int) $patient->id, $slot->starts_at, $slot->starts_at->copy()->addMinutes((int) $slot->duration_minutes));

            $appointment = ClinicAppointment::create([
                'clinic_id' => (int) $slot->clinic_id,
                'patient_id' => (int) $patient->id,
                'created_by' => (int) $patient->id,
                'scheduled_at' => $slot->starts_at,
                'duration_minutes' => (int) $slot->duration_minutes,
                'status' => ClinicAppointment::STATUS_CONFIRMED,
                'reason' => $data['reason'] ?? null,
            ]);

            $slot->update(['appointment_id' => (int) $appointment->id]);
            $this->syncAgenda($appointment);

            // The patient chose an offered slot → tell the clinic it's now booked.
            $this->notify('appointment_requested', (int) $slot->clinic_id, $appointment,
                'حجز موعد', 'Appointment booked',
                'حجز مريض فتحة موعد في ' . $appointment->scheduled_at->format('Y-m-d H:i') . '.',
                'A patient booked a slot on ' . $appointment->scheduled_at->format('Y-m-d H:i') . '.');

            return $appointment;
        });
    }

    /**
     * Pre-visit reminders to the **patient only** (the clinic already has the
     * appointment on its calendar). Two fire per confirmed appointment, each
     * exactly once, at the patient's own configured lead times (defaults 24h and
     * 2h — see [[ReminderPreference]]). `reminded_day_at`/`reminded_soon_at` mark
     * the first/second reminder as sent. Returns how many were sent.
     */
    public function sendDueReminders(int $limit = 200): int
    {
        $now = Carbon::now();

        $due = ClinicAppointment::query()
            ->where('status', ClinicAppointment::STATUS_CONFIRMED)
            ->where('scheduled_at', '>', $now)
            ->where('scheduled_at', '<=', $now->copy()->addMinutes(ReminderPreference::MAX_FIRST_LEAD))
            ->where(fn ($q) => $q->whereNull('reminded_day_at')->orWhereNull('reminded_soon_at'))
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get();

        $prefs = ReminderPreference::query()
            ->whereIn('user_id', $due->pluck('patient_id')->unique()->all())
            ->get()->keyBy('user_id');

        $sent = 0;
        foreach ($due as $appointment) {
            $pref = $prefs->get((int) $appointment->patient_id) ?? new ReminderPreference();
            $first = $pref->firstLead();
            $second = $pref->secondLead();
            $when = $appointment->scheduled_at;

            $secondDue = $second !== null && $now->gte($when->copy()->subMinutes($second));
            $firstDue = $now->gte($when->copy()->subMinutes($first));

            // Send at most one per run: the closer reminder wins, and it also
            // supersedes an unsent first reminder (a last-minute booking).
            if ($appointment->reminded_soon_at === null && $secondDue) {
                $this->remindPatient($appointment,
                    'تذكير: موعدك قريبًا، في ' . $when->format('Y-m-d H:i') . '.',
                    'Reminder: your appointment is soon, at ' . $when->format('Y-m-d H:i') . '.');
                $appointment->reminded_soon_at = $now;
                $appointment->reminded_day_at = $appointment->reminded_day_at ?? $now;
                $appointment->save();
                $sent++;
            } elseif ($appointment->reminded_day_at === null && $firstDue) {
                $this->remindPatient($appointment,
                    'تذكير: لديك موعد قادم في ' . $when->format('Y-m-d H:i') . '.',
                    'Reminder: you have an upcoming appointment on ' . $when->format('Y-m-d H:i') . '.');
                $appointment->reminded_day_at = $now;
                $appointment->save();
                $sent++;
            }
        }

        return $sent;
    }

    private function remindPatient(ClinicAppointment $appointment, string $bodyAr, string $bodyEn): void
    {
        $this->notify('appointment_reminder', (int) $appointment->patient_id, $appointment,
            'تذكير بالموعد', 'Appointment reminder', $bodyAr, $bodyEn);
    }

    /**
     * Patient moves their own appointment to a new time while it is still active.
     * A confirmed appointment keeps its confirmation if the new time is free
     * (overlap-guarded, ignoring itself); a requested one just moves. The clinic
     * is notified, any linked published slot is freed, and both reminder markers
     * reset so the new time is reminded afresh.
     */
    public function rescheduleByPatient(ClinicAppointment $appointment, Carbon $newStart, ?int $duration = null): ClinicAppointment
    {
        $this->move($appointment, $newStart, $duration);

        // Tell the clinic the patient changed it.
        $this->notify('appointment_rescheduled', (int) $appointment->clinic_id, $appointment,
            'إعادة جدولة موعد', 'Appointment rescheduled',
            'غيّر المريض موعده إلى ' . $appointment->scheduled_at->format('Y-m-d H:i') . '.',
            'The patient moved their appointment to ' . $appointment->scheduled_at->format('Y-m-d H:i') . '.');

        return $appointment;
    }

    /** Clinic moves the appointment to a new time and tells the patient. */
    public function rescheduleByClinic(ClinicAppointment $appointment, Carbon $newStart, ?int $duration = null): ClinicAppointment
    {
        $this->move($appointment, $newStart, $duration);

        $this->notify('appointment_rescheduled', (int) $appointment->patient_id, $appointment,
            'تغيير موعدك', 'Your appointment moved',
            'غيّرت العيادة موعدك إلى ' . $appointment->scheduled_at->format('Y-m-d H:i') . '.',
            'The clinic moved your appointment to ' . $appointment->scheduled_at->format('Y-m-d H:i') . '.');

        return $appointment;
    }

    /**
     * Shared reschedule core: guard the new time on both the clinic calendar and
     * (for a confirmed appointment) the patient's agenda — each ignoring this
     * appointment itself — then move it, free any linked slot, reset reminders,
     * and re-sync the agenda entry.
     */
    private function move(ClinicAppointment $appointment, Carbon $newStart, ?int $duration): void
    {
        if (! in_array($appointment->status, ClinicAppointment::ACTIVE_STATUSES, true)) {
            throw ValidationException::withMessages(['status' => __('لا يمكن إعادة جدولة هذا الموعد.')]);
        }

        $duration = $duration ?? (int) $appointment->duration_minutes;

        if ($appointment->status === ClinicAppointment::STATUS_CONFIRMED) {
            $this->assertNoConflict((int) $appointment->clinic_id, $newStart, $duration, (int) $appointment->id);
            $this->agenda->assertFree(
                (int) $appointment->patient_id, $newStart, $newStart->copy()->addMinutes($duration),
                [$appointment->getMorphClass(), $appointment->getKey()],
            );
        }

        DB::transaction(function () use ($appointment, $newStart, $duration) {
            ClinicAppointmentSlot::query()
                ->where('appointment_id', $appointment->id)
                ->update(['appointment_id' => null]);

            $appointment->update([
                'scheduled_at' => $newStart,
                'duration_minutes' => $duration,
                'reminded_day_at' => null,
                'reminded_soon_at' => null,
            ]);

            if ($appointment->status === ClinicAppointment::STATUS_CONFIRMED) {
                $this->syncAgenda($appointment);
            }
        });
    }

    /** Mirror a confirmed appointment onto the patient's agenda. */
    private function syncAgenda(ClinicAppointment $appointment): void
    {
        $appointment->loadMissing('clinic:id,name');
        $title = trim(__('موعد') . ' - ' . (string) ($appointment->clinic?->name ?? ''));

        $this->agenda->syncCommitment(
            (int) $appointment->patient_id,
            AgendaItem::KIND_APPOINTMENT,
            $appointment,
            $title,
            $appointment->scheduled_at,
            $appointment->scheduled_at->copy()->addMinutes((int) $appointment->duration_minutes),
        );
    }

    /** True if a confirmed appointment already overlaps the given window. */
    public function assertNoConflict(int $clinicId, Carbon $start, int $duration, ?int $ignoreId): void
    {
        $end = $start->copy()->addMinutes($duration);

        $conflict = ClinicAppointment::query()
            ->where('clinic_id', $clinicId)
            ->where('status', ClinicAppointment::STATUS_CONFIRMED)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            // overlap: existing.start < newEnd AND existing.end > newStart
            ->where('scheduled_at', '<', $end)
            ->whereRaw('DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) > ?', [$start])
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'scheduled_at' => __('هذا الموعد يتعارض مع موعد مؤكَّد آخر.'),
            ]);
        }
    }

    private function transition(ClinicAppointment $appointment, array $from, string $to): ClinicAppointment
    {
        if (! in_array($appointment->status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('لا يمكن تنفيذ هذا الإجراء على الموعد الآن.')]);
        }

        $appointment->update(['status' => $to]);

        return $appointment;
    }

    private function notifyConfirmed(ClinicAppointment $appointment): void
    {
        $this->notify('appointment_confirmed', (int) $appointment->patient_id, $appointment,
            'تأكيد الموعد', 'Appointment confirmed',
            'تأكّد موعدك في ' . $appointment->scheduled_at->format('Y-m-d H:i') . '.',
            'Your appointment on ' . $appointment->scheduled_at->format('Y-m-d H:i') . ' is confirmed.');
    }

    private function notify(string $eventKey, int $userId, ClinicAppointment $appointment, string $titleAr, string $titleEn, string $bodyAr, string $bodyEn): void
    {
        try {
            $this->notifications->dispatch($eventKey, $userId, [
                // Stored bilingual content — deliberately not wrapped in __().
                'title_ar' => $titleAr,
                'title_en' => $titleEn,
                'body_ar' => $bodyAr,
                'body_en' => $bodyEn,
                'notifiable_type' => ClinicAppointment::class,
                'notifiable_id' => (int) $appointment->id,
                'source_type' => ClinicAppointment::class,
                'source_id' => (int) $appointment->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
