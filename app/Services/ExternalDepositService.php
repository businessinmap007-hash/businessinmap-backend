<?php

namespace App\Services;

use App\Models\Booking;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Validation\ValidationException;

/**
 * «العربون خارج المنصة» — المالك، 2026-10-19/20: the deposit is NOT confiscated and it does NOT stop the customer's
 * account — he books anywhere as usual; only an opened dispute holds an account back. A business that takes its
 * deposit by cash or a transfer outside the platform records that it arrived, and the booking does not start before
 * that. The business may also accept the booking WITHOUT any deposit paid outside («قبول العربون»).
 *
 * The record lives in the booking's meta (no wallet money moves, so there is no escrow row to create):
 *   pending  → the business has accepted the booking and the deposit is still outstanding
 *   received → the business confirmed it got it (cash / transfer), `method` says which
 *   waived  → the business accepts the booking without the deposit
 *
 * Starting the booking asks for received / waived (ServiceExecutionEngine::moveBookingToInProgress).
 *
 * «غير قابل للاسترداد» (owner, 2026-10-28): a stay at the hotel's non-refundable rate owes the WHOLE price in advance — the same
 * record, `kind` = prepayment, amount = the booking's price. The hotel confirms it arrived (cash / transfer) or waives it, and the
 * guest is not checked in before that. Nothing in the wallet moves or is confiscated.
 */
class ExternalDepositService
{
    public const META_KEY = '_external_deposit';
    public const METHODS = ['cash', 'transfer'];

    public function __construct(
        private readonly ServiceExecutionEngine $engine,
        private readonly NotificationDispatcherService $notifications,
    ) {
    }

    /** A stay at the non-refundable rate: its whole price is paid to the hotel in advance. */
    public function isPrepayment(Booking $booking): bool
    {
        return data_get($booking->meta, 'rate_plan') === \App\Services\Hotels\StayTerms::NON_REFUNDABLE;
    }

    /** Does this booking owe a deposit (or, at the non-refundable rate, its whole price) outside the platform right now? */
    public function required(Booking $booking): bool
    {
        if ($this->isPrepayment($booking)) {
            return true;
        }

        try {
            $policy = $this->engine->depositPolicy($booking);
        } catch (\Throwable) {
            return false;
        }

        return (bool) ($policy['required'] ?? false) && (bool) ($policy['external_deposit_required'] ?? false);
    }

    /** @return array{required:bool,kind:string,status:?string,amount:float,method:?string,reference:?string} */
    public function state(Booking $booking): array
    {
        $meta = (array) (($booking->meta ?? [])[self::META_KEY] ?? []);
        $required = $this->required($booking);
        $status = $meta['status'] ?? null;

        if ($required && $status === null && in_array((string) $booking->status, [Booking::STATUS_ACCEPTED, Booking::STATUS_IN_PROGRESS], true)) {
            $status = 'pending';
        }

        $amount = 0.0;
        if ($required && $this->isPrepayment($booking)) {
            $amount = round((float) $booking->price, 2);
        } elseif ($required) {
            try {
                $amount = round((float) ($this->engine->depositPolicy($booking)['external_deposit_amount'] ?? 0), 2);
            } catch (\Throwable) {
            }
        }

        return [
            'required' => $required,
            // `prepayment`: the whole price of a non-refundable stay; `deposit`: the business's own deposit terms
            'kind' => $this->isPrepayment($booking) ? 'prepayment' : 'deposit',
            'status' => $required ? $status : null,
            'amount' => $amount,
            'method' => $meta['method'] ?? null,
            'reference' => $meta['reference'] ?? null,
        ];
    }

    /** Received or waived: the deposit stands in the way of nothing. */
    public function isSettled(Booking $booking): bool
    {
        $status = ((array) (($booking->meta ?? [])[self::META_KEY] ?? []))['status'] ?? null;

        return in_array($status, ['received', 'waived'], true);
    }

    /** The business says it received the deposit — in cash or by a transfer outside the platform. */
    public function confirmReceived(Booking $booking, int $businessId, string $method, ?string $reference = null): array
    {
        $this->assertOwn($booking, $businessId);

        if (! in_array($method, self::METHODS, true)) {
            throw ValidationException::withMessages(['method' => __('اختر كاش أو تحويل.')]);
        }

        $this->record($booking, ['status' => 'received', 'method' => $method, 'reference' => $reference ?: null]);

        $this->isPrepayment($booking)
            ? $this->tell($booking, 'booking.external_deposit_received',
                'استلم الفندق المبلغ المقدم', 'The hotel received the prepayment',
                'أكّد الفندق أنه استلم المبلغ كاملًا. حجزك جاهز.',
                'The hotel confirmed it received the full amount. Your booking is ready.')
            : $this->tell($booking, 'booking.external_deposit_received',
                'استلم النشاط العربون', 'The deposit was received',
                'أكّد النشاط أنه استلم العربون. حجزك جاهز للتنفيذ.',
                'The business confirmed it received the deposit. Your booking is ready.');

        return $this->state($booking->fresh());
    }

    /** The business accepts the booking with no deposit paid outside — «قبول العربون». */
    public function waive(Booking $booking, int $businessId): array
    {
        $this->assertOwn($booking, $businessId);

        $this->record($booking, ['status' => 'waived', 'method' => null, 'reference' => null]);

        $this->isPrepayment($booking)
            ? $this->tell($booking, 'booking.external_deposit_waived',
                'تنازل الفندق عن الدفع المقدم', 'The hotel waived the prepayment',
                'قبل الفندق حجزك دون دفع المبلغ مقدمًا.',
                'The hotel accepted your booking without the payment in advance.')
            : $this->tell($booking, 'booking.external_deposit_waived',
                'قبل النشاط حجزك بلا عربون', 'Booking accepted without a deposit',
                'قبل النشاط حجزك دون دفع عربون.',
                'The business accepted your booking without a deposit.');

        return $this->state($booking->fresh());
    }

    private function assertOwn(Booking $booking, int $businessId): void
    {
        if ((int) $booking->business_id !== $businessId) {
            throw ValidationException::withMessages(['deposit' => __('هذا الحجز ليس لنشاطك.')]);
        }

        if (! in_array((string) $booking->status, [Booking::STATUS_ACCEPTED, Booking::STATUS_IN_PROGRESS], true)) {
            throw ValidationException::withMessages(['deposit' => __('لا ينطبق ذلك على هذا الحجز الآن.')]);
        }

        if (! $this->required($booking)) {
            throw ValidationException::withMessages(['deposit' => __('هذا الحجز لا يطلب عربونًا خارج المنصة.')]);
        }
    }

    /** @param array<string,mixed> $data */
    private function record(Booking $booking, array $data): void
    {
        $meta = (array) ($booking->meta ?? []);
        $meta[self::META_KEY] = $data + ['at' => now()->toDateTimeString()];
        $booking->meta = $meta;
        $booking->save();
    }

    private function tell(Booking $booking, string $eventKey, string $titleAr, string $titleEn, string $bodyAr, string $bodyEn): void
    {
        try {
            $this->notifications->dispatch($eventKey, (int) $booking->user_id, [
                'title_ar' => $titleAr, 'title_en' => $titleEn, 'body_ar' => $bodyAr, 'body_en' => $bodyEn,
                'notifiable_type' => Booking::class, 'notifiable_id' => (int) $booking->id,
                'source_type' => Booking::class, 'source_id' => (int) $booking->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
