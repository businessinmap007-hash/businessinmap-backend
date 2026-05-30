<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Deposit;
use App\Models\Dispute;
use Illuminate\Validation\ValidationException;

class BookingDepositService
{
    public function __construct(
        protected DepositsEscrowService $depositsEscrowService,
        protected DisputeService $disputeService,
    ) {
    }

    public function latestDeposit(?Booking $booking): ?Deposit
    {
        if (! $booking) {
            return null;
        }

        return Deposit::query()
            ->where('target_type', Booking::class)
            ->where('target_id', (int) $booking->id)
            ->orderByDesc('id')
            ->first();
    }

    public function latestDepositOrFail(Booking $booking): Deposit
    {
        $deposit = $this->latestDeposit($booking);

        if (! $deposit) {
            throw ValidationException::withMessages([
                'deposit' => 'لا يوجد Deposit لهذا الحجز.',
            ]);
        }

        return $deposit;
    }

    public function freezeForBooking(Booking $booking, float $holdAmount): Deposit
    {
        if ($holdAmount <= 0) {
            throw ValidationException::withMessages([
                'deposit' => 'قيمة الـ Deposit غير صالحة.',
            ]);
        }

        $existing = $this->latestDeposit($booking);

        if ($existing && ! $existing->isFinal()) {
            $this->syncDepositConfirmationsFromBookingMeta($booking, $existing);

            return $existing;
        }

        $total = round($holdAmount * 2, 2);

        $deposit = $this->depositsEscrowService->create(
            clientId: (int) $booking->user_id,
            businessId: (int) $booking->business_id,
            totalAmount: $total,
            clientPercent: 50,
            businessPercent: 50,
            targetType: Booking::class,
            targetId: (int) $booking->id,
        );

        $this->syncDepositConfirmationsFromBookingMeta($booking, $deposit);

        return $deposit;
    }

    public function releaseForBooking(Booking $booking): Deposit
    {
        $deposit = $this->latestDepositOrFail($booking);

        return $this->depositsEscrowService->release($deposit);
    }

    public function refundForBooking(
        Booking $booking,
        bool $refundClient = true,
        bool $refundBusiness = true
    ): Deposit {
        $deposit = $this->latestDepositOrFail($booking);

        return $this->depositsEscrowService->refund(
            $deposit,
            $refundClient,
            $refundBusiness
        );
    }

    public function openDisputeForBooking(
        Booking $booking,
        int $openedByUserId,
        ?int $actorId = null,
        array $payload = []
    ): Dispute {
        $deposit = $this->latestDepositOrFail($booking);

        if ($deposit->isFinal()) {
            throw ValidationException::withMessages([
                'deposit' => 'لا يمكن فتح نزاع بعد إنهاء حالة الـ Deposit.',
            ]);
        }

        $existing = Dispute::query()
            ->where('disputeable_type', Booking::class)
            ->where('disputeable_id', (int) $booking->id)
            ->whereIn('status', ['open', 'under_review'])
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->disputeService->openForBooking(
            booking: $booking,
            openedByUserId: $openedByUserId,
            actorId: $actorId,
            payload: $payload
        );
    }
    protected function syncDepositConfirmationsFromBookingMeta(Booking $booking, Deposit $deposit): void
    {
        $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
        $confirm = is_array($meta['_start_confirm'] ?? null) ? $meta['_start_confirm'] : [];

        $dirty = false;

        if (! empty($confirm['client']) && ! (bool) $deposit->client_confirmed) {
            $deposit->client_confirmed = true;
            $dirty = true;
        }

        if (! empty($confirm['business']) && ! (bool) $deposit->business_confirmed) {
            $deposit->business_confirmed = true;
            $dirty = true;
        }

        if ($dirty) {
            $deposit->save();
        }
    }
}