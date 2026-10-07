<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Deposit;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «قبول الديبوزت المجمّد كدفعة» — an OPTIONAL term of the business (`accept_deposit_as_payment`).
 *
 * A deposit is a seriousness measure; it is not part of the price, and it comes back once the customer has paid the
 * whole value directly outside the app (both confirm). This is the one exception, and only when the business has
 * opted in: the CUSTOMER asks that the frozen deposit be taken as a payment instead of a transfer outside the
 * platform, and the BUSINESS accepts (or declines). On acceptance the customer's hold moves to the business and the
 * business's own counter-hold returns to it — the same ruling-style settlement the escrow already has, 0/100.
 *
 * Platform fees are NOT touched: they are charged separately, from each party's wallet, never from the deposit.
 * Guarantees are NOT touched either: a frozen guarantee never moves to the business here.
 */
class BookingDepositPaymentService
{
    public const META_KEY = '_deposit_as_payment';

    public function __construct(
        protected BookingDepositService $deposits,
        protected DepositsEscrowService $escrow,
        protected BookingDepositPolicyResolver $policies,
    ) {
    }

    /** What the app needs to show: may it be asked, was it, was it accepted, for how much. */
    public function state(Booking $booking): array
    {
        $meta = (array) (($booking->meta ?? [])[self::META_KEY] ?? []);
        $accepted = ! empty($meta['accepted_at']);

        return [
            'allowed' => ! $accepted && $this->refusal($booking) === null,
            'requested' => ! empty($meta['requested_at']) && empty($meta['declined_at']) && ! $accepted,
            'declined' => ! empty($meta['declined_at']) && empty($meta['requested_at']),
            'accepted' => $accepted,
            'amount' => (float) ($meta['amount'] ?? 0),
        ];
    }

    /** Why this booking cannot take it, or null when it can. */
    private function refusal(Booking $booking): ?string
    {
        if ((string) $booking->status !== Booking::STATUS_IN_PROGRESS) {
            return __('لا يمكن ذلك إلا بعد بدء التنفيذ.');
        }

        $business = $booking->business;
        $service = $booking->service;
        $policy = $business && $service ? $this->policies->resolve($business, $service) : ['enabled' => false];

        if (! ($policy['enabled'] ?? false) || ! ($policy['accept_deposit_as_payment'] ?? false)) {
            return __('هذا النشاط لا يقبل الديبوزت كدفعة.');
        }

        $deposit = $this->deposits->latestDeposit($booking);

        if (! $deposit || ! $deposit->isFrozen() || (float) $deposit->client_amount <= 0) {
            return __('لا يوجد ديبوزت مجمّد من العميل.');
        }

        if ($this->deposits->hasLiveDispute($deposit)) {
            return __('يوجد نزاع مفتوح على هذا الحجز.');
        }

        return null;
    }

    private function assertAllowed(Booking $booking): Deposit
    {
        $why = $this->refusal($booking);

        if ($why !== null) {
            throw ValidationException::withMessages(['deposit' => $why]);
        }

        return $this->deposits->latestDepositOrFail($booking);
    }

    /** The customer asks that the frozen deposit be taken as a payment. */
    public function request(Booking $booking, int $clientId): array
    {
        if ((int) $booking->user_id !== $clientId) {
            throw ValidationException::withMessages(['deposit' => __('هذا الحساب ليس عميل هذا الحجز.')]);
        }

        $this->assertAllowed($booking);

        $meta = (array) ($booking->meta ?? []);
        $current = (array) ($meta[self::META_KEY] ?? []);

        if (! empty($current['requested_at']) && empty($current['declined_at'])) {
            throw ValidationException::withMessages(['deposit' => __('سبق طلب ذلك.')]);
        }

        $meta[self::META_KEY] = ['requested_at' => now()->toDateTimeString()];
        $booking->meta = $meta;
        $booking->save();

        $this->notify($booking, (int) $booking->business_id, 'booking.deposit_payment_requested',
            'طلب اعتماد الديبوزت كدفعة', 'Deposit as payment requested',
            'العميل يطلب أن يُعتمد ديبوزته المجمّد كدفعة بدل التحويل خارج المنصة.',
            'The customer asks that the frozen deposit be taken as a payment instead of an outside transfer.');

        return $this->state($booking->fresh());
    }

    /** The business accepts: the customer's hold moves to it, its own counter-hold returns. */
    public function accept(Booking $booking, int $businessId): array
    {
        if ((int) $booking->business_id !== $businessId) {
            throw ValidationException::withMessages(['deposit' => __('هذا الحجز ليس لنشاطك.')]);
        }

        $deposit = $this->assertAllowed($booking);
        $this->assertRequested($booking);

        $amount = (float) $deposit->client_amount;

        DB::transaction(function () use ($booking, $deposit, $amount) {
            $this->escrow->split($deposit, 0, 100);

            $booking->refresh();
            $meta = (array) ($booking->meta ?? []);
            $meta[self::META_KEY] = array_merge((array) ($meta[self::META_KEY] ?? []), [
                'accepted_at' => now()->toDateTimeString(),
                'amount' => round($amount, 2),
            ]);
            unset($meta[self::META_KEY]['declined_at']);
            $booking->meta = $meta;
            $booking->save();
        });

        $this->notify($booking, (int) $booking->user_id, 'booking.deposit_payment_accepted',
            'اعتُمد ديبوزتك كدفعة', 'Your deposit was taken as a payment',
            'قبل النشاط ديبوزتك المجمّد كدفعة من قيمة الحجز.',
            'The business accepted your frozen deposit as a payment toward the booking.');

        return $this->state($booking->fresh());
    }

    /** The business declines: the deposit stays frozen and the customer pays outside as usual. */
    public function decline(Booking $booking, int $businessId): array
    {
        if ((int) $booking->business_id !== $businessId) {
            throw ValidationException::withMessages(['deposit' => __('هذا الحجز ليس لنشاطك.')]);
        }

        $this->assertRequested($booking);

        $meta = (array) ($booking->meta ?? []);
        $meta[self::META_KEY] = ['declined_at' => now()->toDateTimeString()];
        $booking->meta = $meta;
        $booking->save();

        $this->notify($booking, (int) $booking->user_id, 'booking.deposit_payment_declined',
            'لم يُعتمد الديبوزت كدفعة', 'Deposit not taken as a payment',
            'رفض النشاط اعتماد الديبوزت كدفعة. يبقى مجمّدًا، وادفع القيمة مباشرة كالمعتاد.',
            'The business declined. The deposit stays frozen; pay the value directly as usual.');

        return $this->state($booking->fresh());
    }

    private function assertRequested(Booking $booking): void
    {
        $meta = (array) (($booking->meta ?? [])[self::META_KEY] ?? []);

        if (empty($meta['requested_at']) || ! empty($meta['declined_at']) || ! empty($meta['accepted_at'])) {
            throw ValidationException::withMessages(['deposit' => __('لا يوجد طلب معلّق.')]);
        }
    }

    private function notify(Booking $booking, int $userId, string $event, string $titleAr, string $titleEn, string $bodyAr, string $bodyEn): void
    {
        try {
            app(NotificationDispatcherService::class)->dispatch($event, $userId, [
                'title_ar' => $titleAr,
                'title_en' => $titleEn,
                'body_ar' => $bodyAr,
                'body_en' => $bodyEn,
                'notifiable_type' => Booking::class,
                'notifiable_id' => (int) $booking->id,
                'source_id' => (int) $booking->id,
                'skip_realtime' => true,
            ]);
        } catch (\Throwable $e) {
            report($e); // a missed notice must never undo a money decision
        }
    }
}
