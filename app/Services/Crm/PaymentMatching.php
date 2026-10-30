<?php

namespace App\Services\Crm;

use App\Models\Crm\AccountMove;
use App\Models\Crm\Customer;
use App\Models\Crm\Payment;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * «دفعة الآجل تُؤكَّد من الطرفين» (owner, 2026-10-30). Either side starts it — the business «استلمت», the customer
 * «دفعت» — and the OTHER side confirms it himself; until then it lowers nothing. For a transfer (a wallet outside the
 * app, InstaPay, a bank) each side types the transaction reference from his own app, blind — neither ever sees what
 * the other typed before they match — and the references must agree after normalising (spaces and dashes dropped,
 * Arabic digits read as Latin, letters case-blind); the amount is the one the first side wrote. Three wrong tries end
 * it as «غير مطابقة». A reference counts once per business. A customer outside the app cannot confirm: the business's
 * word stands alone, flagged «مؤكدة من المحل فقط», with the printed receipt he signs.
 */
final class PaymentMatching
{
    public function __construct(private readonly CreditLedger $ledger) {}

    public static function normalize(?string $ref): ?string
    {
        if ($ref === null) {
            return null;
        }
        $ref = strtr($ref, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
        $ref = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $ref));

        return $ref === '' ? null : mb_substr($ref, 0, 64);
    }

    /** @param array{uuid?:string,amount:float|string,method:string,reference?:?string,note?:?string,device_uuid?:?string} $data */
    public function start(Customer $customer, string $side, int $actorId, array $data): Payment
    {
        $uuid = (string) ($data['uuid'] ?? '') ?: (string) Str::uuid();
        if ($existing = Payment::query()->where('uuid', $uuid)->where('business_id', $customer->business_id)->first()) {
            return $existing;
        }

        if ($side === Payment::BY_CUSTOMER && ! $customer->user_id) {
            throw ValidationException::withMessages(['customer' => __('هذا الملف ليس مربوطًا بحسابك.')]);
        }

        $method = (string) $data['method'];
        $ref = self::normalize($data['reference'] ?? null);
        if ($method !== Payment::CASH && $ref === null) {
            throw ValidationException::withMessages(['reference' => __('اكتب رقم العملية من تطبيق البنك أو المحفظة.')]);
        }
        if ($ref !== null) {
            $this->assertUnused((int) $customer->business_id, $ref);
        }

        $amount = round((float) $data['amount'], 2);
        $offApp = ! $customer->user_id;

        $payment = DB::transaction(function () use ($customer, $side, $actorId, $data, $uuid, $method, $ref, $amount, $offApp) {
            $payment = Payment::create([
                'uuid' => $uuid,
                'business_id' => $customer->business_id,
                'customer_id' => $customer->id,
                'amount' => $amount,
                'method' => $method,
                'started_by' => $side,
                'status' => Payment::PENDING,
                'business_ref' => $side === Payment::BY_BUSINESS ? $ref : null,
                'customer_ref' => $side === Payment::BY_CUSTOMER ? $ref : null,
                'business_confirmed_at' => $side === Payment::BY_BUSINESS ? now() : null,
                'business_confirmed_by' => $side === Payment::BY_BUSINESS ? $actorId : null,
                'customer_confirmed_at' => $side === Payment::BY_CUSTOMER ? now() : null,
                'business_only' => $offApp,
                'note' => isset($data['note']) ? mb_substr((string) $data['note'], 0, 255) : null,
                'created_by' => $actorId,
                'device_uuid' => $data['device_uuid'] ?? null,
            ]);

            // no one on the other side to confirm: the business's own receipt is the record
            if ($offApp) {
                $this->settle($payment);
            }

            return $payment;
        });

        if (! $offApp) {
            $this->tellOtherSide($payment->fresh(), 'crm_payment_pending');
        }

        return $payment->fresh();
    }

    /** The other side's «تأكيد» — with his own reference for a transfer. */
    public function confirm(Payment $payment, string $side, int $actorId, ?string $reference): Payment
    {
        $this->assertPending($payment);
        if ($payment->started_by === $side) {
            throw ValidationException::withMessages(['payment' => __('التأكيد من الطرف الآخر وليس ممن سجّل الدفعة.')]);
        }

        if ($payment->isTransfer()) {
            $ref = self::normalize($reference);
            if ($ref === null) {
                throw ValidationException::withMessages(['reference' => __('اكتب رقم العملية من تطبيق البنك أو المحفظة عندك.')]);
            }
            $theirs = $side === Payment::BY_BUSINESS ? $payment->customer_ref : $payment->business_ref;

            if (! hash_equals((string) $theirs, $ref)) {
                $attempts = $payment->attempts + 1;
                $final = $attempts >= Payment::MAX_ATTEMPTS;
                $payment->update(['attempts' => $attempts, 'status' => $final ? Payment::MISMATCH : Payment::PENDING]);

                if ($final) {
                    $this->tell($payment, Payment::BY_BUSINESS, 'crm_payment_mismatch');
                    $this->tell($payment, Payment::BY_CUSTOMER, 'crm_payment_mismatch');
                    throw ValidationException::withMessages(['reference' => __('رقم العملية لم يتطابق 3 مرات: أُغلقت الدفعة كغير مطابقة. تواصلا في محادثة العملية أو سجّلا دفعة جديدة.')]);
                }

                throw ValidationException::withMessages(['reference' => __('رقم العملية لا يطابق ما كتبه الطرف الآخر. راجعه (محاولة :n من :max).', ['n' => $attempts, 'max' => Payment::MAX_ATTEMPTS])]);
            }
            $this->assertUnused((int) $payment->business_id, $ref, $payment->id);
        }

        DB::transaction(function () use ($payment, $side, $actorId) {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $this->assertPending($locked);

            $locked->update($side === Payment::BY_BUSINESS
                ? ['business_confirmed_at' => now(), 'business_confirmed_by' => $actorId, 'business_ref' => $locked->isTransfer() ? $locked->customer_ref : null]
                : ['customer_confirmed_at' => now(), 'customer_ref' => $locked->isTransfer() ? $locked->business_ref : null]);

            $this->settle($locked);
        });

        $payment = $payment->fresh();
        $this->tell($payment, $payment->started_by, 'crm_payment_confirmed');

        return $payment;
    }

    /** «لم أدفع هذا» / «لم يصلني» — it never counts, and stays in the record with its reason. */
    public function reject(Payment $payment, string $side, ?string $reason): Payment
    {
        $this->assertPending($payment);
        if ($payment->started_by === $side) {
            throw ValidationException::withMessages(['payment' => __('من سجّل الدفعة يلغيها ولا يرفضها.')]);
        }

        $payment->update(['status' => Payment::REJECTED, 'rejected_by' => $side, 'reason' => $reason ? mb_substr($reason, 0, 255) : null]);
        $this->tell($payment, $payment->started_by, 'crm_payment_rejected');

        return $payment->fresh();
    }

    /** The side who started it takes it back while the other has not answered. */
    public function cancel(Payment $payment, string $side): Payment
    {
        $this->assertPending($payment);
        if ($payment->started_by !== $side) {
            throw ValidationException::withMessages(['payment' => __('يلغي الدفعة من سجّلها فقط.')]);
        }

        $payment->update(['status' => Payment::CANCELLED]);

        return $payment->fresh();
    }

    /** What one side may see: never the other side's reference while it is still to be matched. */
    public function view(Payment $payment, string $side): array
    {
        $mine = $side === Payment::BY_BUSINESS ? $payment->business_ref : $payment->customer_ref;
        $confirmed = $payment->status === Payment::CONFIRMED;

        return [
            'id' => $payment->id,
            'uuid' => $payment->uuid,
            'customer_id' => $payment->customer_id,
            'number' => $payment->number,
            'amount' => (float) $payment->amount,
            'method' => $payment->method,
            'started_by' => $payment->started_by,
            'status' => $payment->status,
            'reference' => $confirmed ? ($payment->business_ref ?? $payment->customer_ref) : $mine,
            'attempts' => $payment->attempts,
            'attempts_left' => max(0, Payment::MAX_ATTEMPTS - $payment->attempts),
            'business_confirmed' => $payment->business_confirmed_at !== null,
            'customer_confirmed' => $payment->customer_confirmed_at !== null,
            'business_only' => $payment->business_only,
            'awaiting_me' => $payment->status === Payment::PENDING && $payment->started_by !== $side,
            'rejected_by' => $payment->rejected_by,
            'reason' => $payment->reason,
            'note' => $payment->note,
            'created_at' => $payment->created_at,
            'confirmed_at' => $payment->confirmed_at,
        ];
    }

    private function settle(Payment $payment): void
    {
        $number = (int) Payment::query()->where('business_id', $payment->business_id)->lockForUpdate()->max('number') + 1;
        $payment->update(['status' => Payment::CONFIRMED, 'confirmed_at' => now(), 'number' => $number]);

        $customer = Customer::query()->findOrFail($payment->customer_id);
        $this->ledger->post($customer, AccountMove::PAYMENT, -1 * (float) $payment->amount, [
            'uuid' => $this->ledger->uuidFrom('payment|' . $payment->uuid),
            'source_type' => 'crm_payment',
            'source_id' => $payment->id,
            'note' => __('دفعة :method — إيصال :n', ['method' => __(self::methodLabel($payment->method)), 'n' => $number]),
            'created_by' => $payment->business_confirmed_by ?? $payment->created_by,
            'device_uuid' => $payment->device_uuid,
        ]);
    }

    private function assertPending(Payment $payment): void
    {
        if ($payment->status !== Payment::PENDING) {
            throw ValidationException::withMessages(['payment' => __('هذه الدفعة لم تعد بانتظار التأكيد.')]);
        }
    }

    private function assertUnused(int $businessId, string $ref, ?int $except = null): void
    {
        $used = Payment::query()
            ->where('business_id', $businessId)
            ->where('status', Payment::CONFIRMED)
            ->when($except, fn ($q) => $q->where('id', '!=', $except))
            ->where(fn ($q) => $q->where('business_ref', $ref)->orWhere('customer_ref', $ref))
            ->exists();

        if ($used) {
            throw ValidationException::withMessages(['reference' => __('رقم العملية هذا استُخدم في دفعة سابقة عند نفس النشاط.')]);
        }
    }

    public static function methodLabel(string $method): string
    {
        return match ($method) {
            'wallet' => 'محفظة',
            'instapay' => 'إنستاباي',
            'bank' => 'تحويل بنكي',
            default => 'نقدًا',
        };
    }

    private function tellOtherSide(Payment $payment, string $event): void
    {
        $this->tell($payment, $payment->started_by === Payment::BY_BUSINESS ? Payment::BY_CUSTOMER : Payment::BY_BUSINESS, $event);
    }

    private function tell(Payment $payment, string $side, string $event): void
    {
        try {
            $customer = Customer::query()->find($payment->customer_id);
            if (! $customer) {
                return;
            }
            $to = $side === Payment::BY_BUSINESS ? (int) $payment->business_id : (int) $customer->user_id;
            if (! $to) {
                return;
            }
            $business = (string) optional(User::query()->find($payment->business_id))->name;
            $amount = number_format((float) $payment->amount, 2);
            $who = $side === Payment::BY_BUSINESS ? $customer->name : $business;

            [$ar, $en] = match ($event) {
                'crm_payment_pending' => $side === Payment::BY_CUSTOMER
                    ? ["سجّل {$who} دفعة منك بقيمة {$amount} ج — أكّدها أو ارفضها.", "{$who} recorded a payment of {$amount} EGP from you — confirm or reject it."]
                    : ["يقول {$who} إنه دفع {$amount} ج — أكّد الاستلام أو ارفضه.", "{$who} says they paid {$amount} EGP — confirm receipt or reject it."],
                'crm_payment_confirmed' => ["تأكدت الدفعة {$amount} ج مع {$who} من الطرفين.", "The {$amount} EGP payment with {$who} is confirmed by both sides."],
                'crm_payment_rejected' => ["رُفضت الدفعة {$amount} ج من {$who}.", "{$who} rejected the {$amount} EGP payment."],
                default => ["لم يتطابق رقم العملية للدفعة {$amount} ج مع {$who}: أُغلقت الدفعة.", "The transaction reference of the {$amount} EGP payment with {$who} did not match: it was closed."],
            };

            app(NotificationDispatcherService::class)->dispatch($event, $to, [
                'body_ar' => $ar,
                'body_en' => $en,
                'source_type' => 'crm_payment',
                'source_id' => $payment->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
