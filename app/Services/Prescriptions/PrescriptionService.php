<?php

namespace App\Services\Prescriptions;

use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\PrescriptionShare;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The prescription lifecycle: a doctor issues a روشتة for a patient; the patient
 * sends it to a pharmacy; the pharmacy prepares it, marks it ready, and
 * dispenses it (delivered or picked up). Each hand-off pushes a notification to
 * the party who now needs to act.
 */
class PrescriptionService
{
    public function __construct(private readonly NotificationDispatcherService $notifications)
    {
    }

    /**
     * A doctor issues a prescription for a patient, with its medicine lines.
     *
     * @param  array<int,array<string,mixed>>  $items
     */
    public function issue(User $doctor, User $patient, array $header, array $items): Prescription
    {
        return DB::transaction(function () use ($doctor, $patient, $header, $items) {
            $prescription = Prescription::create([
                'doctor_id' => (int) $doctor->id,
                'patient_id' => (int) $patient->id,
                'appointment_id' => isset($header['appointment_id']) ? (int) $header['appointment_id'] : null,
                'status' => Prescription::STATUS_ISSUED,
                'diagnosis' => $header['diagnosis'] ?? null,
                'patient_condition' => $header['patient_condition'] ?? null,
                'notes' => $header['notes'] ?? null,
                'issued_at' => now(),
            ]);

            $this->createItems($prescription, $items);

            $this->notify('prescription_issued', (int) $patient->id, $prescription,
                'وصفة طبية جديدة', 'New prescription',
                'كتب لك الطبيب وصفة طبية جديدة.', 'Your doctor issued a new prescription for you.');

            return $prescription->load('items');
        });
    }

    /** @param  array<int,array<string,mixed>>  $items */
    private function createItems(Prescription $prescription, array $items): void
    {
        foreach ($items as $item) {
            // The dictionary row is the one source of truth for the name
            // printed on the prescription — never the client's own text,
            // and never `name_ar` (a phonetic alias, not a registered
            // brand — see MedicineController::serialize).
            $medicine = Medicine::query()->findOrFail((int) $item['medicine_id']);

            // "٢ أسبوع" ⇒ duration_value=2, duration_unit=weeks,
            // duration_days=14 — the scheduler only ever reads the days
            // total, so it needs no change; these two are display/input
            // only.
            $durationUnit = $item['duration_unit'] ?? null;
            $durationValue = isset($item['duration_value']) ? (int) $item['duration_value'] : null;
            $durationDays = ($durationUnit && $durationValue)
                ? $durationValue * PrescriptionItem::DURATION_UNIT_DAYS[$durationUnit]
                : null;

            $prescription->items()->create([
                'medicine_id' => $medicine->id,
                'name' => $medicine->name,
                'dosage' => $item['dosage'] ?? null,
                'quantity' => $item['quantity'] ?? null,
                'instructions' => $item['instructions'] ?? null,
                'frequency_per_day' => $item['frequency_per_day'] ?? null,
                'food_timing' => $item['food_timing'] ?? null,
                'time_slots' => $item['time_slots'] ?? null,
                'duration_days' => $durationDays,
                'duration_unit' => $durationUnit,
                'duration_value' => $durationValue,
            ]);

            $medicine->increment('uses_count');
        }
    }

    /**
     * Grant a second doctor read-only access — either the patient or the
     * ORIGINAL doctor may do this («الاثنين معا»); enforced by the caller
     * (PrescriptionController::share), which is who actually knows who is
     * asking. Idempotent: sharing the same doctor twice is a no-op.
     */
    public function share(Prescription $prescription, User $doctor, User $sharedBy): PrescriptionShare
    {
        if ((int) $doctor->id === (int) $prescription->doctor_id) {
            throw ValidationException::withMessages([
                'doctor_id' => __('هذا الطبيب هو من أصدر الوصفة بالفعل.'),
            ]);
        }

        return PrescriptionShare::query()->firstOrCreate(
            ['prescription_id' => $prescription->id, 'doctor_id' => $doctor->id],
            ['shared_by_user_id' => $sharedBy->id],
        );
    }

    /**
     * The ORIGINAL doctor amends a prescription. Never overwrites: a new
     * prescription row is created (revises_prescription_id points back at
     * this one), and this one is cancelled — never deleted — so the full
     * history stays readable by everyone who could already read it.
     *
     * @param  array<int,array<string,mixed>>  $items
     */
    public function revise(Prescription $prescription, array $header, array $items): Prescription
    {
        if ($prescription->status === Prescription::STATUS_DISPENSED) {
            throw ValidationException::withMessages([
                'status' => __('لا يمكن تعديل وصفة تم صرفها بالفعل — أصدر وصفة جديدة.'),
            ]);
        }

        if ($prescription->status === Prescription::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'status' => __('هذه الوصفة ملغاة بالفعل ولا يمكن تعديلها.'),
            ]);
        }

        return DB::transaction(function () use ($prescription, $header, $items) {
            $revision = Prescription::create([
                'doctor_id' => (int) $prescription->doctor_id,
                'patient_id' => (int) $prescription->patient_id,
                'appointment_id' => $prescription->appointment_id,
                'revises_prescription_id' => (int) $prescription->id,
                'status' => Prescription::STATUS_ISSUED,
                'diagnosis' => $header['diagnosis'] ?? $prescription->diagnosis,
                'patient_condition' => $header['patient_condition'] ?? $prescription->patient_condition,
                'notes' => $header['notes'] ?? $prescription->notes,
                'issued_at' => now(),
            ]);

            $this->createItems($revision, $items);

            $prescription->update(['status' => Prescription::STATUS_CANCELLED]);

            $this->notify('prescription_issued', (int) $prescription->patient_id, $revision,
                'تعديل على وصفتك الطبية', 'Your prescription was amended',
                'عدّل طبيبك وصفتك الطبية — راجع النسخة الجديدة.', 'Your doctor amended your prescription — check the new version.');

            foreach ($prescription->sharedDoctorIds() as $doctorId) {
                PrescriptionShare::query()->firstOrCreate([
                    'prescription_id' => $revision->id,
                    'doctor_id' => $doctorId,
                ], ['shared_by_user_id' => (int) $prescription->doctor_id]);
            }

            return $revision->load('items');
        });
    }

    /**
     * The patient sends the prescription to a pharmacy to be dispensed, choosing
     * delivery (with an address) or pickup.
     */
    public function sendToPharmacy(Prescription $prescription, User $pharmacy, string $fulfillment, ?string $address): Prescription
    {
        if (! in_array($prescription->status, [Prescription::STATUS_ISSUED], true)) {
            throw ValidationException::withMessages([
                'status' => __('لا يمكن إرسال هذه الوصفة في حالتها الحالية.'),
            ]);
        }

        if ($fulfillment === Prescription::FULFILLMENT_DELIVERY && ! $address) {
            throw ValidationException::withMessages([
                'delivery_address' => __('أدخل عنوان التوصيل.'),
            ]);
        }

        $prescription->update([
            'pharmacy_id' => (int) $pharmacy->id,
            'fulfillment_type' => $fulfillment,
            'delivery_address' => $fulfillment === Prescription::FULFILLMENT_DELIVERY ? $address : null,
            'status' => Prescription::STATUS_SENT,
        ]);

        $this->notify('prescription_received', (int) $pharmacy->id, $prescription,
            'وصفة طبية لتجهيزها', 'A prescription to prepare',
            'وصلتك وصفة طبية جديدة لتجهيز الدواء.', 'A new prescription arrived for you to prepare.');

        return $prescription;
    }

    /** Pharmacy: begin preparing (from sent). */
    public function startPreparing(Prescription $prescription): Prescription
    {
        return $this->transition($prescription, [Prescription::STATUS_SENT], Prescription::STATUS_PREPARING);
    }

    /** Pharmacy: the medicine is ready — tell the patient. */
    public function markReady(Prescription $prescription): Prescription
    {
        $this->transition($prescription, [Prescription::STATUS_SENT, Prescription::STATUS_PREPARING], Prescription::STATUS_READY);

        $this->notify('prescription_ready', (int) $prescription->patient_id, $prescription,
            'دواؤك جاهز', 'Your medicine is ready',
            'جهّزت الصيدلية دواءك، وهو جاهز الآن.', 'The pharmacy has prepared your medicine — it is ready now.');

        return $prescription;
    }

    /** Pharmacy: dispensed (delivered or handed over). */
    public function dispense(Prescription $prescription): Prescription
    {
        $this->transition($prescription, [Prescription::STATUS_READY, Prescription::STATUS_PREPARING], Prescription::STATUS_DISPENSED);
        $prescription->update(['dispensed_at' => now()]);

        return $prescription;
    }

    /** Pharmacy: cannot fulfil — return it to the patient to send elsewhere. */
    public function reject(Prescription $prescription): Prescription
    {
        $this->transition(
            $prescription,
            [Prescription::STATUS_SENT, Prescription::STATUS_PREPARING],
            Prescription::STATUS_ISSUED,
        );
        $prescription->update(['pharmacy_id' => null, 'fulfillment_type' => null, 'delivery_address' => null]);

        return $prescription;
    }

    /** Doctor or patient cancels, as long as it has not been dispensed. */
    public function cancel(Prescription $prescription): Prescription
    {
        if ($prescription->status === Prescription::STATUS_DISPENSED) {
            throw ValidationException::withMessages([
                'status' => __('لا يمكن إلغاء وصفة تم صرفها.'),
            ]);
        }

        $prescription->update(['status' => Prescription::STATUS_CANCELLED]);

        return $prescription;
    }

    /** Move a prescription between states, guarding the allowed origins. */
    private function transition(Prescription $prescription, array $from, string $to): Prescription
    {
        if (! in_array($prescription->status, $from, true)) {
            throw ValidationException::withMessages([
                'status' => __('لا يمكن تنفيذ هذا الإجراء على الوصفة الآن.'),
            ]);
        }

        $prescription->update(['status' => $to]);

        return $prescription;
    }

    /** Best-effort push — a delivery failure never blocks the state change. */
    private function notify(string $eventKey, int $userId, Prescription $prescription, string $titleAr, string $titleEn, string $bodyAr, string $bodyEn): void
    {
        try {
            $this->notifications->dispatch($eventKey, $userId, [
                // Stored bilingual content — deliberately not wrapped in __().
                'title_ar' => $titleAr,
                'title_en' => $titleEn,
                'body_ar' => $bodyAr,
                'body_en' => $bodyEn,
                'notifiable_type' => Prescription::class,
                'notifiable_id' => (int) $prescription->id,
                'source_type' => Prescription::class,
                'source_id' => (int) $prescription->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
