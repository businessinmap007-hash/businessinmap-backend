<?php

namespace App\Services\Clinics;

use App\Models\ClinicAppointment;
use App\Models\ClinicAppointmentItem;
use App\Models\ClinicAppointmentPayment;
use App\Models\User;
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
            ])->values()->all(),
        ];
    }

    public function recordPayment(ClinicAppointment $a, User $by, float $amount, string $kind, ?string $note = null): ClinicAppointmentPayment
    {
        $this->assertBillable($a);

        if ($amount <= 0 || ! in_array($kind, ClinicAppointmentPayment::KINDS, true)) {
            throw ValidationException::withMessages(['amount' => __('اكتب مبلغًا صحيحًا.')]);
        }

        return ClinicAppointmentPayment::query()->create([
            'clinic_appointment_id' => (int) $a->id,
            'amount' => round($amount, 2),
            'kind' => $kind,
            'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            'recorded_by' => (int) $by->id,
            'paid_at' => now(),
        ]);
    }

    /** A payment recorded by mistake is taken back (it is the clinic's own record). */
    public function removePayment(ClinicAppointment $a, ClinicAppointmentPayment $p): void
    {
        abort_unless((int) $p->clinic_appointment_id === (int) $a->id, 404);

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
