<?php

namespace App\Services\Guarantees;

use App\Models\GuaranteeLevel;
use App\Models\User;
use App\Models\UserGuarantee;

final class GuaranteeOperationCoverageService
{
    public const OP_BOOKING = 'booking';
    public const OP_DELIVERY_ORDER = 'delivery_order';
    public const OP_MARKETPLACE_ORDER = 'marketplace_order';
    public const OP_SERVICE_REQUEST = 'service_request';
    public const OP_SUBSCRIPTION = 'subscription';
    public const OP_CUSTOM = 'custom';

    public function __construct(
        private readonly GuaranteeCoverageService $coverageService
    ) {}

    public function check(
        User $user,
        float $amount,
        string $operationType,
        ?int $operationId = null,
        ?string $targetType = null,
        array $context = []
    ): array {
        $amount = round(max($amount, 0), 2);
        $targetType = $targetType ?: $this->resolveTargetType($user);
        $guarantee = $this->coverageService->activeGuarantee($user, $targetType);

        if (! $guarantee) {
            return $this->decision(
                covered: false,
                reason: 'missing_guarantee',
                user: $user,
                amount: $amount,
                operationType: $operationType,
                operationId: $operationId,
                targetType: $targetType,
                guarantee: null,
                context: $context
            );
        }

        if (! $guarantee->isUsable()) {
            return $this->decision(
                covered: false,
                reason: 'guarantee_not_usable',
                user: $user,
                amount: $amount,
                operationType: $operationType,
                operationId: $operationId,
                targetType: $targetType,
                guarantee: $guarantee,
                context: $context
            );
        }

        if (! $guarantee->covers($amount)) {
            return $this->decision(
                covered: false,
                reason: 'insufficient_coverage',
                user: $user,
                amount: $amount,
                operationType: $operationType,
                operationId: $operationId,
                targetType: $targetType,
                guarantee: $guarantee,
                context: $context
            );
        }

        return $this->decision(
            covered: true,
            reason: 'covered',
            user: $user,
            amount: $amount,
            operationType: $operationType,
            operationId: $operationId,
            targetType: $targetType,
            guarantee: $guarantee,
            context: $context
        );
    }

    public function requireCoverage(
        User $user,
        float $amount,
        string $operationType,
        ?int $operationId = null,
        ?string $targetType = null,
        array $context = []
    ): array {
        $decision = $this->check(
            user: $user,
            amount: $amount,
            operationType: $operationType,
            operationId: $operationId,
            targetType: $targetType,
            context: $context
        );

        if (! (bool) $decision['covered']) {
            throw new GuaranteeCoverageException(
                message: $this->messageForReason((string) $decision['reason']),
                decision: $decision
            );
        }

        return $decision;
    }

    public function payloadFor(User $user, ?string $targetType = null): array
    {
        return $this->coverageService->payload($user, $targetType);
    }

    private function decision(
        bool $covered,
        string $reason,
        User $user,
        float $amount,
        string $operationType,
        ?int $operationId,
        string $targetType,
        ?UserGuarantee $guarantee,
        array $context
    ): array {
        return [
            'covered' => $covered,
            'reason' => $reason,
            'operation_type' => $operationType,
            'operation_id' => $operationId,
            'amount' => $amount,
            'target_type' => $targetType,
            'user_id' => (int) $user->id,
            'guarantee_id' => $guarantee ? (int) $guarantee->id : null,
            'status' => $guarantee ? (string) $guarantee->status : null,
            'locked_amount' => $guarantee ? round((float) $guarantee->locked_amount, 2) : 0.0,
            'current_coverage_amount' => $guarantee ? round((float) $guarantee->current_coverage_amount, 2) : 0.0,
            'is_boosted' => $guarantee ? (bool) $guarantee->is_boosted : false,
            'used_coverage_amount' => $guarantee ? round((float) $guarantee->used_coverage_amount, 2) : 0.0,
            'available_coverage_amount' => $guarantee ? round((float) $guarantee->availableCoverage(), 2) : 0.0,
            'purchased_level_id' => $guarantee && $guarantee->purchased_level_id ? (int) $guarantee->purchased_level_id : null,
            'effective_level_id' => $guarantee && $guarantee->effective_level_id ? (int) $guarantee->effective_level_id : null,
            'context' => $context,
        ];
    }

    private function resolveTargetType(User $user): string
    {
        return $user->isBusiness()
            ? GuaranteeLevel::TARGET_BUSINESS
            : GuaranteeLevel::TARGET_CLIENT;
    }

    private function messageForReason(string $reason): string
    {
        return match ($reason) {
            'missing_guarantee' => __('لا يوجد ضمان مفعل لهذه العملية.'),
            'guarantee_not_usable' => __('الضمان الحالي غير قابل للاستخدام.'),
            'insufficient_coverage' => __('قيمة التغطية المتاحة لا تكفي لهذه العملية.'),
            default => __('لا يمكن تغطية هذه العملية بالضمان الحالي.'),
        };
    }
}
