<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Deposit;
use App\Models\Dispute;
use App\Models\PlatformService;
use Illuminate\Validation\ValidationException;

class DisputeService
{
    public function __construct(
        protected DepositsEscrowService $depositsEscrowService,
    ) {
    }

    public function open(
        int $platformServiceId,
        string $disputeableType,
        int $disputeableId,
        int $openedByUserId,
        ?int $againstUserId = null,
        ?int $actorId = null,
        array $payload = []
    ): Dispute {
        $existing = Dispute::query()
            ->where('platform_service_id', $platformServiceId)
            ->where('disputeable_type', $disputeableType)
            ->where('disputeable_id', $disputeableId)
            ->whereIn('status', ['open', 'under_review'])
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        return Dispute::create([
            'platform_service_id' => $platformServiceId,
            'disputeable_type'    => $disputeableType,
            'disputeable_id'      => $disputeableId,
            'opened_by_user_id'   => $openedByUserId,
            'against_user_id'     => $againstUserId,
            'status'              => 'open',
            'reason_code'         => $payload['reason_code'] ?? null,
            'reason_text'         => $payload['reason_text'] ?? null,
            'resolution_type'     => null,
            'resolution_payload'  => null,
            'opened_at'           => now(),
            'resolved_at'         => null,
            'closed_at'           => null,
        ]);
    }

    public function openForBooking(
        Booking $booking,
        int $openedByUserId,
        ?int $actorId = null,
        array $payload = []
    ): Dispute {
        $platformServiceId = $this->resolveBookingPlatformServiceId($booking);

        $againstUserId = $openedByUserId === (int) $booking->user_id
            ? (int) $booking->business_id
            : (int) $booking->user_id;

        return $this->open(
            platformServiceId: $platformServiceId,
            disputeableType: Booking::class,
            disputeableId: (int) $booking->id,
            openedByUserId: $openedByUserId,
            againstUserId: $againstUserId,
            actorId: $actorId,
            payload: $payload
        );
    }

    public function resolve(
        Dispute $dispute,
        string $resolutionType,
        array $resolutionPayload = [],
        ?int $actorId = null
    ): Dispute {
        if (! in_array($dispute->status, ['open', 'under_review'], true)) {
            throw ValidationException::withMessages([
                'status' => 'الحالة الحالية للنزاع لا تسمح بتنفيذ القرار.',
            ]);
        }

        $disputeable = $this->resolveDisputeable($dispute);

        if ($disputeable instanceof Booking) {
            $this->resolveBookingDispute(
                booking: $disputeable,
                resolutionType: $resolutionType,
                resolutionPayload: $resolutionPayload
            );
        }

        $dispute->resolution_type = $resolutionType;
        $dispute->resolution_payload = $resolutionPayload;
        $dispute->status = 'resolved';
        $dispute->resolved_at = now();
        $dispute->save();

        return $dispute;
    }

    protected function resolveBookingDispute(
        Booking $booking,
        string $resolutionType,
        array $resolutionPayload = []
    ): void {
        $deposit = Deposit::query()
            ->where('target_type', Booking::class)
            ->where('target_id', (int) $booking->id)
            ->orderByDesc('id')
            ->first();

        if (! $deposit) {
            throw ValidationException::withMessages([
                'deposit' => 'لا يوجد Deposit مرتبط بهذا الحجز.',
            ]);
        }

        if ($deposit->isFinal()) {
            return;
        }

        switch ($resolutionType) {
            case 'release_business':
                $this->depositsEscrowService->release($deposit);
                break;

            case 'refund_client':
                $this->depositsEscrowService->refund($deposit, true, true);
                break;

            case 'no_action':
                // لا يوجد إجراء مالي
                break;

            case 'split':
                // حاليًا لا توجد طبقة توزيع جزئي فعلية على locked balances
                // لذلك نحفظ القرار فقط إلى أن يتم بناء wallet-hold split engine.
                $clientPercent   = (float) ($resolutionPayload['client_percent'] ?? 0);
                $businessPercent = (float) ($resolutionPayload['business_percent'] ?? 0);

                if (round($clientPercent + $businessPercent, 2) !== 100.00) {
                    throw ValidationException::withMessages([
                        'split' => 'مجموع النسب يجب أن يساوي 100%.',
                    ]);
                }
                break;

            default:
                throw ValidationException::withMessages([
                    'resolution_type' => 'نوع القرار غير مدعوم.',
                ]);
        }
    }

    protected function resolveBookingPlatformServiceId(Booking $booking): int
    {
        if ((int) $booking->service_id > 0) {
            return (int) $booking->service_id;
        }

        $platformService = PlatformService::query()
            ->where('key', 'booking')
            ->first();

        if (! $platformService) {
            throw ValidationException::withMessages([
                'platform_service' => 'تعذر تحديد Platform Service الخاص بالحجوزات.',
            ]);
        }

        return (int) $platformService->id;
    }

    protected function resolveDisputeable(Dispute $dispute): mixed
    {
        if (
            ! empty($dispute->disputeable_type) &&
            ! empty($dispute->disputeable_id) &&
            class_exists($dispute->disputeable_type)
        ) {
            return $dispute->disputeable_type::find($dispute->disputeable_id);
        }

        return null;
    }
}