<?php

namespace App\Services\Prescriptions;

use App\Models\Medicine;
use App\Models\Prescription;
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
                'notes' => $header['notes'] ?? null,
                'issued_at' => now(),
            ]);

            foreach ($items as $item) {
                $prescription->items()->create([
                    'name' => $item['name'],
                    'dosage' => $item['dosage'] ?? null,
                    'quantity' => $item['quantity'] ?? null,
                    'instructions' => $item['instructions'] ?? null,
                    'frequency_per_day' => $item['frequency_per_day'] ?? null,
                    'food_timing' => $item['food_timing'] ?? null,
                    'time_slots' => $item['time_slots'] ?? null,
                    'duration_days' => $item['duration_days'] ?? null,
                ]);

                // Grow the shared dictionary from what doctors actually write, so
                // the next doctor sees this drug in their picker (name + strength).
                Medicine::remember((string) $item['name'], $item['dosage'] ?? null, (int) $doctor->id);
            }

            $this->notify('prescription_issued', (int) $patient->id, $prescription,
                'وصفة طبية جديدة', 'New prescription',
                'كتب لك الطبيب وصفة طبية جديدة.', 'Your doctor issued a new prescription for you.');

            return $prescription->load('items');
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
