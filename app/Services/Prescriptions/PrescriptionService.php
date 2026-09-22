<?php

namespace App\Services\Prescriptions;

use App\Models\Address;
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
     * A customer asks a pharmacy directly for medicine — no doctor, no items
     * yet (the pharmacy reads the photo/note and replies with real, priced
     * lines via {@see quoteForCustomer()}). Deliberately NOT bound to the
     * medicine dictionary at this step: unlike a doctor's prescription, the
     * whole point here is the customer does not know the exact drug name.
     */
    public function request(User $customer, User $pharmacy, ?string $note): Prescription
    {
        $prescription = Prescription::create([
            'patient_id' => (int) $customer->id,
            'pharmacy_id' => (int) $pharmacy->id,
            'origin' => Prescription::ORIGIN_CUSTOMER,
            'status' => Prescription::STATUS_REQUESTED,
            'request_note' => $note,
        ]);

        $this->notify('prescription_received', (int) $pharmacy->id, $prescription,
            'طلب دواء جديد', 'A new medicine request',
            'طلب منك عميل دواءً — راجع الصورة أو الملاحظة ورُد بالسعر.', 'A customer asked you for medicine — check the photo or note and reply with a price.');

        return $prescription;
    }

    /**
     * The pharmacy reads a customer's direct request and replies with real,
     * dictionary-bound, already-priced lines in one shot (no separate
     * pricing step — there is no doctor's line to price against here).
     * Nothing is prepared yet: the customer must accept this quote first.
     *
     * @param  array<int,array{medicine_id:int,quantity:int,unit_price:float,note?:string}>  $items
     */
    public function quoteForCustomer(Prescription $prescription, array $items): Prescription
    {
        if ($prescription->origin !== Prescription::ORIGIN_CUSTOMER || $prescription->status !== Prescription::STATUS_REQUESTED) {
            throw ValidationException::withMessages([
                'status' => __('لا يمكن تسعير هذا الطلب في حالته الحالية.'),
            ]);
        }

        return DB::transaction(function () use ($prescription, $items) {
            $total = 0.0;

            foreach ($items as $item) {
                $medicine = Medicine::query()->findOrFail((int) $item['medicine_id']);
                $unitPrice = round((float) $item['unit_price'], 2);
                $quantity = (int) $item['quantity'];
                $lineTotal = round($unitPrice * $quantity, 2);
                $total += $lineTotal;

                $prescription->items()->create([
                    'medicine_id' => $medicine->id,
                    'name' => $medicine->name,
                    'instructions' => $item['note'] ?? null,
                    'unit_price' => $unitPrice,
                    'billed_quantity' => $quantity,
                    'line_total' => $lineTotal,
                ]);

                $medicine->increment('uses_count');
            }

            $prescription->update([
                'status' => Prescription::STATUS_QUOTED,
                'medicine_total' => round($total, 2),
                'priced_at' => now(),
            ]);

            $this->notify('prescription_priced', (int) $prescription->patient_id, $prescription,
                'عرض سعر لطلب دوائك', 'A quote for your medicine request',
                'ردّت الصيدلية على طلبك — راجع الأصناف والسعر ووافق للمتابعة.', 'The pharmacy replied to your request — check the items and price, then confirm to proceed.');

            return $prescription->fresh('items');
        });
    }

    /** The customer accepts the pharmacy's quote — now the pharmacy may start preparing it. */
    public function confirmQuote(Prescription $prescription): Prescription
    {
        $this->transition($prescription, [Prescription::STATUS_QUOTED], Prescription::STATUS_SENT);

        $this->notify('prescription_received', (int) $prescription->pharmacy_id, $prescription,
            'العميل وافق على السعر', 'The customer accepted the quote',
            'وافق العميل على سعر طلبه — جهّز الدواء.', 'The customer accepted the quote for their request — go ahead and prepare it.');

        return $prescription;
    }

    /** The pharmacy cannot fulfil a direct customer request (unreadable photo, out of stock...). */
    public function declineRequest(Prescription $prescription, ?string $note): Prescription
    {
        if (! in_array($prescription->status, [Prescription::STATUS_REQUESTED, Prescription::STATUS_QUOTED], true)) {
            throw ValidationException::withMessages([
                'status' => __('لا يمكن رفض هذا الطلب في حالته الحالية.'),
            ]);
        }

        $prescription->update(['status' => Prescription::STATUS_CANCELLED, 'request_note' => trim(($prescription->request_note ?? '') . ($note ? "
— الصيدلية: {$note}" : ''))]);

        $this->notify('prescription_priced', (int) $prescription->patient_id, $prescription,
            'تعذّر تنفيذ طلب الدواء', 'Your medicine request could not be fulfilled',
            'اعتذرت الصيدلية عن تنفيذ طلبك — جرّب صيدلية أخرى.', 'The pharmacy could not fulfil your request — try another pharmacy.');

        return $prescription;
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
     *
     * A saved address-book entry ($addressId) wins over the free-text
     * $address, mirroring CustomerCartService::placeOrder — `delivery_address`
     * stores the resolved, human-readable SNAPSHOT either way (never
     * rewritten by a later address-book edit), `delivery_address_id` is the
     * pointer back for the frontend to preselect it next time.
     */
    public function sendToPharmacy(Prescription $prescription, User $pharmacy, string $fulfillment, ?string $address, ?int $addressId = null): Prescription
    {
        if (! in_array($prescription->status, [Prescription::STATUS_ISSUED], true)) {
            throw ValidationException::withMessages([
                'status' => __('لا يمكن إرسال هذه الوصفة في حالتها الحالية.'),
            ]);
        }

        $resolvedAddress = null;
        $resolvedAddressId = null;

        if ($fulfillment === Prescription::FULFILLMENT_DELIVERY) {
            if ($addressId) {
                $book = Address::query()
                    ->where('id', $addressId)
                    ->where('user_id', (int) $prescription->patient_id)
                    ->first();

                if (! $book) {
                    throw ValidationException::withMessages([
                        'address_id' => __('العنوان غير موجود.'),
                    ]);
                }

                $resolvedAddress = $book->toDeliveryLine();
                $resolvedAddressId = (int) $book->id;
            } elseif ($address) {
                $resolvedAddress = $address;
            } else {
                throw ValidationException::withMessages([
                    'delivery_address' => __('أدخل عنوان التوصيل.'),
                ]);
            }
        }

        $prescription->update([
            'pharmacy_id' => (int) $pharmacy->id,
            'fulfillment_type' => $fulfillment,
            'delivery_address' => $resolvedAddress,
            'delivery_address_id' => $resolvedAddressId,
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

    /**
     * Pharmacy states its own price for each line — its price, not the
     * doctor's, not the shared drug dictionary's, and never inferred from
     * the pharmacy's own «قاموس الأدوية» catalog (stock and price both move
     * day to day). All-or-nothing: every item must be priced together, so
     * the invoice this produces is never partial. Re-priceable any time
     * before dispense — stock or price can still change while it waits.
     *
     * @param  array<int,array{prescription_item_id:int,unit_price:float,billed_quantity:int}>  $lines
     */
    public function price(Prescription $prescription, array $lines): Prescription
    {
        if (in_array($prescription->status, [
            Prescription::STATUS_ISSUED,
            Prescription::STATUS_DISPENSED,
            Prescription::STATUS_CANCELLED,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => __('لا يمكن تسعير الوصفة في حالتها الحالية.'),
            ]);
        }

        return DB::transaction(function () use ($prescription, $lines) {
            $total = 0.0;

            foreach ($lines as $line) {
                $unitPrice = round((float) $line['unit_price'], 2);
                $quantity = (int) $line['billed_quantity'];
                $lineTotal = round($unitPrice * $quantity, 2);
                $total += $lineTotal;

                PrescriptionItem::whereKey((int) $line['prescription_item_id'])->update([
                    'unit_price' => $unitPrice,
                    'billed_quantity' => $quantity,
                    'line_total' => $lineTotal,
                ]);
            }

            $prescription->update([
                'medicine_total' => round($total, 2),
                'priced_at' => now(),
            ]);

            $this->notify('prescription_priced', (int) $prescription->patient_id, $prescription,
                'فاتورة دوائك جاهزة', 'Your medicine invoice is ready',
                'حددت الصيدلية سعر دوائك — راجع الفاتورة قبل الاستلام.', 'The pharmacy has priced your medicine — check the invoice before pickup.');

            return $prescription->fresh('items');
        });
    }

    /** Pharmacy: dispensed (delivered or handed over) — only once priced. */
    public function dispense(Prescription $prescription): Prescription
    {
        if ($prescription->medicine_total === null) {
            throw ValidationException::withMessages([
                'medicine_total' => __('يجب تسعير الوصفة قبل صرفها.'),
            ]);
        }

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
