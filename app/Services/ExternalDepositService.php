<?php

namespace App\Services;

use App\Models\Booking;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * «العربون خارج المنصة» — المالك، 2026-10-19: the deposit is NOT confiscated. When a business takes its deposit by
 * cash or a transfer outside the platform, the customer's account is frozen for new dealings until the BUSINESS says
 * it received the deposit. The business may also accept the booking WITHOUT any deposit paid outside («قبول العربون»).
 *
 * The record lives in the booking's meta (no wallet money moves, so there is no escrow row to create):
 *   pending  → the business has accepted the booking and the deposit is still outstanding
 *   received → the business confirmed it got it (cash / transfer), `method` says which
 *   waived  → the business accepts the booking without the deposit
 *
 * Starting the booking asks for received / waived (ServiceExecutionEngine::moveBookingToInProgress).
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

    /** Does this booking owe a deposit outside the platform right now (by its business's current terms)? */
    public function required(Booking $booking): bool
    {
        try {
            $policy = $this->engine->depositPolicy($booking);
        } catch (\Throwable) {
            return false;
        }

        return (bool) ($policy['required'] ?? false) && (bool) ($policy['external_deposit_required'] ?? false);
    }

    /** @return array{required:bool,status:?string,amount:float,method:?string,reference:?string} */
    public function state(Booking $booking): array
    {
        $meta = (array) (($booking->meta ?? [])[self::META_KEY] ?? []);
        $required = $this->required($booking);
        $status = $meta['status'] ?? null;

        if ($required && $status === null && in_array((string) $booking->status, [Booking::STATUS_ACCEPTED, Booking::STATUS_IN_PROGRESS], true)) {
            $status = 'pending';
        }

        $amount = 0.0;
        if ($required) {
            try {
                $amount = round((float) ($this->engine->depositPolicy($booking)['external_deposit_amount'] ?? 0), 2);
            } catch (\Throwable) {
            }
        }

        return [
            'required' => $required,
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

    /**
     * Bookings of this customer that keep his account frozen: accepted or running, owing the deposit outside the
     * platform, and the business has not yet said it arrived.
     *
     * @return Collection<int,Booking>
     */
    public function freezingBookingsOf(int $userId): Collection
    {
        return Booking::query()
            ->where('user_id', $userId)
            ->whereIn('status', [Booking::STATUS_ACCEPTED, Booking::STATUS_IN_PROGRESS])
            ->latest('id')->limit(30)->get()
            ->filter(fn (Booking $b) => ! $this->isSettled($b) && $this->required($b))
            ->values();
    }

    /** The business says it received the deposit — in cash or by a transfer outside the platform. */
    public function confirmReceived(Booking $booking, int $businessId, string $method, ?string $reference = null): array
    {
        $this->assertOwn($booking, $businessId);

        if (! in_array($method, self::METHODS, true)) {
            throw ValidationException::withMessages(['method' => __('اختر كاش أو تحويل.')]);
        }

        $this->record($booking, ['status' => 'received', 'method' => $method, 'reference' => $reference ?: null]);

        $this->tell($booking, 'booking.external_deposit_received',
            'استلم النشاط العربون', 'The deposit was received',
            'أكّد النشاط أنه استلم العربون. حسابك يعمل كالمعتاد.',
            'The business confirmed it received the deposit. Your account works as usual again.');

        return $this->state($booking->fresh());
    }

    /** The business accepts the booking with no deposit paid outside — «قبول العربون». */
    public function waive(Booking $booking, int $businessId): array
    {
        $this->assertOwn($booking, $businessId);

        $this->record($booking, ['status' => 'waived', 'method' => null, 'reference' => null]);

        $this->tell($booking, 'booking.external_deposit_waived',
            'قبل النشاط حجزك بلا عربون', 'Booking accepted without a deposit',
            'قبل النشاط حجزك دون دفع عربون. حسابك يعمل كالمعتاد.',
            'The business accepted your booking without a deposit. Your account works as usual again.');

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
