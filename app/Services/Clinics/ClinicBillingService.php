<?php

namespace App\Services\Clinics;

use App\Models\ClinicAppointment;
use App\Models\ClinicAppointmentItem;
use App\Models\ClinicAppointmentPayment;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The money of a clinic visit.
 *
 *  - the VISIT FEE is the base, paid on arrival;
 *  - each SERVICE the patient named is charged once the visit is done, at the price listed — which the clinic may raise
 *    or lower once the work is finished («final_price»); until then the account is the visit fee alone;
 *  - what is paid is recorded as PAYMENTS, one per instalment (normal in dental clinics and some others), and both the
 *    patient and the doctor read them.
 *
 * The platform does not hold this money: it is the clinic's own books for the visit, confirmed in the app.
 */
class ClinicBillingService
{
    public function __construct(private readonly NotificationDispatcherService $notifications)
    {
    }

    /** A visit takes money once it is on: booked, or done. */
    private const BILLABLE = [ClinicAppointment::STATUS_CONFIRMED, ClinicAppointment::STATUS_COMPLETED];

    /**
     * What the visit costs, what was paid and what is left.
     *
     * @return array<string,mixed>
     */
    public function summary(ClinicAppointment $a): array
    {
        $a->loadMissing('items.service', 'payments.recorder');

        // the cost of a service enters the account once the visit is done, or earlier when the clinic names it itself
        $done = $a->status === ClinicAppointment::STATUS_COMPLETED;

        $services = $a->items->map(function (ClinicAppointmentItem $i) use ($done) {
            $counted = $done || $i->final_price !== null;

            return [
                'item_id' => (int) $i->id,
                'id' => $i->specialty_service_id ? (int) $i->specialty_service_id : null,
                'name' => $i->service?->label() ?: (string) $i->name,
                'price' => $i->price,
                'final_price' => $i->final_price,
                // null while the service is not yet charged: «تظهر تكلفة الخدمة بعد الانتهاء»
                'amount' => $counted ? ($i->final_price ?? $i->price) : null,
                'counted' => $counted,
            ];
        })->values();

        $fee = $a->visit_fee !== null ? (float) $a->visit_fee : null;
        $servicesTotal = round((float) $services->sum(fn ($s) => (float) ($s['amount'] ?? 0)), 2);
        $total = round(($fee ?? 0.0) + $servicesTotal, 2);
        $paid = round((float) $a->payments->sum('amount'), 2);

        return [
            'visit_fee' => $fee,
            'services' => $services->all(),
            'services_total' => $servicesTotal,
            'total' => $total,
            'paid' => $paid,
            'remaining' => round(max($total - $paid, 0.0), 2),
            // a named service that is not charged yet (visit not done): the total is the visit fee until then
            'services_pending' => $services->contains(fn ($s) => ! $s['counted']),
            'payments' => $a->payments->map(fn (ClinicAppointmentPayment $p) => [
                'id' => (int) $p->id,
                'amount' => $p->amount,
                'kind' => (string) $p->kind,
                'note' => $p->note,
                'paid_at' => optional($p->paid_at)->toIso8601String(),
                'recorded_by' => $p->recorder?->displayName(),
                // pending | confirmed | disputed — both sides on record, so neither forgets nor denies
                'confirmation' => $p->confirmation(),
                'confirmed_at' => optional($p->patient_confirmed_at)->toIso8601String(),
                'dispute_note' => $p->dispute_note,
                // a payment the patient confirmed is his word as much as the clinic's: it is no longer the clinic's to remove
                'can_remove' => $p->patient_confirmed_at === null,
            ])->values()->all(),
            'awaiting_confirmation' => $a->payments->filter(fn ($p) => $p->confirmation() === 'pending')->count(),
            // what the clinic will ask at the next session (null = what is left)
            'installment_amount' => $a->installment_amount !== null ? (float) $a->installment_amount : null,
            // what this patient still owes this clinic from earlier visits — asked with this one
            'carried_over' => $this->carriedOver($a),
        ];
    }

    public function recordPayment(ClinicAppointment $a, User $by, float $amount, string $kind, ?string $note = null): ClinicAppointmentPayment
    {
        $this->assertBillable($a);

        if ($amount <= 0 || ! in_array($kind, ClinicAppointmentPayment::KINDS, true)) {
            throw ValidationException::withMessages(['amount' => __('اكتب مبلغًا صحيحًا.')]);
        }

        $payment = ClinicAppointmentPayment::query()->create([
            'clinic_appointment_id' => (int) $a->id,
            'amount' => round($amount, 2),
            'kind' => $kind,
            'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            'recorded_by' => (int) $by->id,
            'paid_at' => now(),
        ]);

        $this->notify('appointment_payment_recorded', (int) $a->patient_id, $a,
            'تسجيل دفعة', 'Payment recorded',
            'سجّلت العيادة دفعة ' . $this->money($payment->amount) . ' جنيه — أكّدها من التطبيق.',
            'The clinic recorded a payment of ' . $this->money($payment->amount) . ' EGP — confirm it in the app.');

        return $payment;
    }

    /** The patient says «نعم، دفعت هذا المبلغ». */
    public function confirmPayment(ClinicAppointment $a, ClinicAppointmentPayment $p): ClinicAppointmentPayment
    {
        abort_unless((int) $p->clinic_appointment_id === (int) $a->id, 404);

        $p->update(['patient_confirmed_at' => now(), 'disputed_at' => null, 'dispute_note' => null]);

        return $p;
    }

    /** The patient says «لم أدفع هذا المبلغ»: the clinic is told, and the payment stays on record as disputed. */
    public function disputePayment(ClinicAppointment $a, ClinicAppointmentPayment $p, ?string $note): ClinicAppointmentPayment
    {
        abort_unless((int) $p->clinic_appointment_id === (int) $a->id, 404);

        if ($p->patient_confirmed_at !== null) {
            throw ValidationException::withMessages(['payment' => __('دفعة أكّدتها لا يمكن الاعتراض عليها.')]);
        }

        $p->update(['disputed_at' => now(), 'dispute_note' => $note !== null && trim($note) !== '' ? trim($note) : null]);

        $this->notify('appointment_payment_disputed', (int) $a->clinic_id, $a,
            'اعتراض على دفعة', 'Payment disputed',
            'اعترض مريض على دفعة ' . $this->money($p->amount) . ' جنيه سجّلتها العيادة.',
            'A patient disputed a payment of ' . $this->money($p->amount) . ' EGP the clinic recorded.');

        return $p;
    }

    /** What the clinic will ask at the next session; null = what is left. */
    public function setInstallment(ClinicAppointment $a, ?float $amount): void
    {
        if ($amount !== null && $amount < 0) {
            throw ValidationException::withMessages(['amount' => __('اكتب مبلغًا صحيحًا.')]);
        }

        $a->update(['installment_amount' => $amount === null ? null : round($amount, 2)]);
    }

    /**
     * What this appointment's patient still owes this clinic from EARLIER finished visits: `amount` is everything left,
     * `due` is what is asked with this visit (the instalment the clinic set per visit, or what is left of it).
     *
     * @return array{amount:float,due:float}
     */
    public function carriedOver(ClinicAppointment $a): array
    {
        return $this->owedBy((int) $a->patient_id, (int) $a->clinic_id, $a->scheduled_at, (int) $a->id);
    }

    /** @return array{amount:float,due:float} */
    public function owedBy(int $patientId, int $clinicId, $before = null, ?int $exceptId = null): array
    {
        $rows = ClinicAppointment::query()
            ->where('clinic_id', $clinicId)->where('patient_id', $patientId)
            ->where('status', ClinicAppointment::STATUS_COMPLETED)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->when($before, fn ($q) => $q->where('scheduled_at', '<', $before))
            ->with('items', 'payments')
            ->get();

        $amount = 0.0;
        $due = 0.0;

        foreach ($rows as $r) {
            $total = (float) ($r->visit_fee ?? 0) + (float) $r->items->sum(fn ($i) => (float) ($i->final_price ?? $i->price ?? 0));
            $left = max($total - (float) $r->payments->sum('amount'), 0.0);

            if ($left <= 0) {
                continue;
            }

            $amount += $left;
            $due += $r->installment_amount !== null ? min((float) $r->installment_amount, $left) : $left;
        }

        return ['amount' => round($amount, 2), 'due' => round($due, 2)];
    }

    private function money(float $v): string
    {
        return $v == round($v) ? (string) (int) $v : number_format($v, 2, '.', '');
    }

    private function notify(string $eventKey, int $userId, ClinicAppointment $a, string $titleAr, string $titleEn, string $bodyAr, string $bodyEn): void
    {
        try {
            $this->notifications->dispatch($eventKey, $userId, [
                // Stored bilingual content — deliberately not wrapped in __().
                'title_ar' => $titleAr, 'title_en' => $titleEn, 'body_ar' => $bodyAr, 'body_en' => $bodyEn,
                'notifiable_type' => ClinicAppointment::class, 'notifiable_id' => (int) $a->id,
                'source_type' => ClinicAppointment::class, 'source_id' => (int) $a->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** A payment recorded by mistake is taken back (it is the clinic's own record). */
    public function removePayment(ClinicAppointment $a, ClinicAppointmentPayment $p): void
    {
        abort_unless((int) $p->clinic_appointment_id === (int) $a->id, 404);

        if ($p->patient_confirmed_at !== null) {
            throw ValidationException::withMessages(['payment' => __('دفعة أكّدها المريض لا تُحذف.')]);
        }

        $p->delete();
    }

    /** What the clinic charges for one service once it is done; null puts it back to the listed price. */
    public function setFinalPrice(ClinicAppointment $a, ClinicAppointmentItem $item, ?float $price): ClinicAppointmentItem
    {
        $this->assertBillable($a);
        abort_unless((int) $item->clinic_appointment_id === (int) $a->id, 404);

        if ($price !== null && $price < 0) {
            throw ValidationException::withMessages(['final_price' => __('اكتب مبلغًا صحيحًا.')]);
        }

        DB::transaction(fn () => $item->update(['final_price' => $price === null ? null : round($price, 2)]));

        return $item;
    }

    private function assertBillable(ClinicAppointment $a): void
    {
        if (! in_array($a->status, self::BILLABLE, true)) {
            throw ValidationException::withMessages(['status' => __('لا يُسجَّل دفع إلا لموعد مؤكد أو منتهٍ.')]);
        }
    }
}
