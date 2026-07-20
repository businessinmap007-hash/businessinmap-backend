<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Deposit;
use App\Models\Dispute;
use App\Models\OperationGuarantor;
use App\Models\PlatformService;
use App\Services\Guarantees\OperationGuarantorService;
use Illuminate\Validation\ValidationException;

class DisputeService
{
    public function __construct(
        protected DepositsEscrowService $depositsEscrowService,
        protected OperationGuarantorService $operationGuarantors,
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
            ->whereIn('status', [
                Dispute::STATUS_OPEN,
                Dispute::STATUS_MUTUAL_RESOLUTION,
                Dispute::STATUS_UNDER_REVIEW,
            ])
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $policySnapshot = is_array($payload['policy_snapshot'] ?? null)
            ? $payload['policy_snapshot']
            : [];

        $resolutionDays = max((int) (
            data_get($policySnapshot, 'dispute_resolution_days')
            ?? data_get($policySnapshot, 'policy.dispute_resolution_days')
            ?? $payload['dispute_resolution_days']
            ?? 15
        ), 1);

        $warningEveryDays = max((int) (
            data_get($policySnapshot, 'warning_every_days')
            ?? data_get($policySnapshot, 'policy.warning_every_days')
            ?? $payload['warning_every_days']
            ?? 3
        ), 1);

        $now = now();

        return Dispute::create([
            'platform_service_id' => $platformServiceId,
            'disputeable_type' => $disputeableType,
            'disputeable_id' => $disputeableId,
            'opened_by_user_id' => $openedByUserId,
            'against_user_id' => $againstUserId,

            'status' => Dispute::STATUS_MUTUAL_RESOLUTION,
            'type' => $payload['type'] ?? 'deposit',
            'deposit_id' => isset($payload['deposit_id']) ? (int) $payload['deposit_id'] : null,

            'reason_code' => $payload['reason_code'] ?? null,
            'reason_text' => $payload['reason_text'] ?? null,

            'resolution_type' => null,
            'resolution_payload' => null,

            'opened_at' => $now,
            'mutual_resolution_started_at' => $now,
            'mutual_resolution_deadline_at' => $now->copy()->addDays($resolutionDays),
            'warning_every_days' => $warningEveryDays,
            'last_warning_sent_at' => null,
            'next_warning_at' => $now->copy()->addDays($warningEveryDays),
            'warning_count' => 0,

            'client_cooperated_at' => null,
            'business_cooperated_at' => null,
            'client_non_cooperation_flag' => false,
            'business_non_cooperation_flag' => false,

            'resolved_at' => null,
            'closed_at' => null,
            'resolved_by' => null,

            'meta' => [
                'actor_id' => $actorId,
                'policy_snapshot' => $policySnapshot,
                'source_payload' => $payload,
            ],
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
        if (! in_array($dispute->status, [
            Dispute::STATUS_OPEN,
            Dispute::STATUS_MUTUAL_RESOLUTION,
            Dispute::STATUS_UNDER_REVIEW,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => __('الحالة الحالية للنزاع لا تسمح بتنفيذ القرار.'),
            ]);
        }

        $disputeable = $this->resolveDisputeable($dispute);

        if ($disputeable instanceof Booking) {
            $this->resolveBookingDispute(
                booking: $disputeable,
                resolutionType: $resolutionType,
                resolutionPayload: $resolutionPayload
            );

            // Dispute over: return the frozen guarantee coverage (client's own +
            // any friend co-guarantors) — it was held throughout the dispute and
            // is never charged.
            $this->operationGuarantors->releaseOperation(OperationGuarantor::OP_BOOKING, (int) $disputeable->id);
        }

        $payload = is_array($dispute->resolution_payload ?? null)
            ? $dispute->resolution_payload
            : [];

        $payload['resolved_by'] = $actorId;
        $payload['resolution_payload'] = $resolutionPayload;

        $dispute->resolution_type = $resolutionType;
        $dispute->resolution_payload = $payload;
        $dispute->status = Dispute::STATUS_RESOLVED;
        $dispute->resolved_by = $actorId;
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
                'deposit' => __('لا يوجد Deposit مرتبط بهذا الحجز.'),
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
                break;

            case 'split':
                $clientPercent = (float) ($resolutionPayload['client_percent'] ?? 0);
                $businessPercent = (float) ($resolutionPayload['business_percent'] ?? 0);

                if (round($clientPercent + $businessPercent, 2) !== 100.00) {
                    throw ValidationException::withMessages([
                        'split' => __('مجموع النسب يجب أن يساوي 100%.'),
                    ]);
                }

                break;

            default:
                throw ValidationException::withMessages([
                    'resolution_type' => __('نوع القرار غير مدعوم.'),
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
                'platform_service' => __('تعذر تحديد Platform Service الخاص بالحجوزات.'),
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