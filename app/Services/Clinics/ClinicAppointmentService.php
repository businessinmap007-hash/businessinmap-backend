<?php

namespace App\Services\Clinics;

use App\Models\ClinicAppointment;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Clinic appointment lifecycle: a patient requests a time, the clinic confirms
 * (or the clinic books directly) — never double-booking a confirmed slot — then
 * completes it or marks a no-show. Each hand-off pushes a notification.
 */
class ClinicAppointmentService
{
    public function __construct(private readonly NotificationDispatcherService $notifications)
    {
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
        $this->assertNoConflict((int) $clinic->id, $start, (int) ($data['duration_minutes'] ?? 30), null);

        $appointment = ClinicAppointment::create([
            'clinic_id' => (int) $clinic->id,
            'patient_id' => (int) $patient->id,
            'created_by' => (int) $clinic->id,
            'scheduled_at' => $start,
            'duration_minutes' => $data['duration_minutes'] ?? 30,
            'status' => ClinicAppointment::STATUS_CONFIRMED,
            'reason' => $data['reason'] ?? null,
        ]);

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

        $appointment->update(['status' => ClinicAppointment::STATUS_CONFIRMED]);
        $this->notifyConfirmed($appointment);

        return $appointment;
    }

    public function reject(ClinicAppointment $appointment): ClinicAppointment
    {
        return $this->transition($appointment, [ClinicAppointment::STATUS_REQUESTED, ClinicAppointment::STATUS_CONFIRMED], ClinicAppointment::STATUS_CANCELLED);
    }

    public function complete(ClinicAppointment $appointment): ClinicAppointment
    {
        return $this->transition($appointment, [ClinicAppointment::STATUS_CONFIRMED], ClinicAppointment::STATUS_COMPLETED);
    }

    public function noShow(ClinicAppointment $appointment): ClinicAppointment
    {
        return $this->transition($appointment, [ClinicAppointment::STATUS_CONFIRMED], ClinicAppointment::STATUS_NO_SHOW);
    }

    /** Patient cancels, as long as it has not already happened/closed. */
    public function cancelByPatient(ClinicAppointment $appointment): ClinicAppointment
    {
        if (! in_array($appointment->status, ClinicAppointment::ACTIVE_STATUSES, true)) {
            throw ValidationException::withMessages(['status' => __('لا يمكن إلغاء هذا الموعد.')]);
        }

        $appointment->update(['status' => ClinicAppointment::STATUS_CANCELLED]);

        return $appointment;
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
