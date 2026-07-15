<?php

namespace App\Services\Guarantees;

use App\Models\GuaranteeLevel;
use App\Models\GuaranteeTransaction;
use App\Models\UserGuarantee;
use Illuminate\Support\Facades\DB;

final class GuaranteeAutoDowngradeService
{
    public function __construct(
        private readonly GuaranteeBoostEvaluator $boostEvaluator
    ) {}

    public function syncEffectiveLevel(
        UserGuarantee $guarantee,
        ?string $referenceType = null,
        ?int $referenceId = null,
        array $meta = []
    ): array {
        return DB::transaction(function () use ($guarantee, $referenceType, $referenceId, $meta) {
            /** @var UserGuarantee $lockedGuarantee */
            $lockedGuarantee = UserGuarantee::query()
                ->whereKey((int) $guarantee->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedGuarantee->purchasedLevel) {
                return [
                    'changed' => false,
                    'reason' => 'missing_purchased_level',
                    'guarantee' => $lockedGuarantee,
                    'level' => null,
                ];
            }

            if (in_array((string) $lockedGuarantee->status, [
                UserGuarantee::STATUS_CANCELLED,
                UserGuarantee::STATUS_SUSPENDED,
            ], true)) {
                return [
                    'changed' => false,
                    'reason' => 'inactive_status',
                    'guarantee' => $lockedGuarantee,
                    'level' => $lockedGuarantee->effectiveLevel,
                ];
            }

            if ((string) $lockedGuarantee->status === UserGuarantee::STATUS_UNDERFUNDED) {
                return [
                    'changed' => false,
                    'reason' => 'underfunded_waiting_grace',
                    'guarantee' => $lockedGuarantee,
                    'level' => $lockedGuarantee->effectiveLevel,
                ];
            }

            $bestLevel = $this->bestEligibleLevel($lockedGuarantee);
            $oldEffectiveLevelId = $lockedGuarantee->effective_level_id ? (int) $lockedGuarantee->effective_level_id : null;
            $oldStatus = (string) $lockedGuarantee->status;
            $oldCoverage = round((float) $lockedGuarantee->current_coverage_amount, 2);

            if (! $bestLevel) {
                $lockedGuarantee->effective_level_id = null;
                $lockedGuarantee->status = UserGuarantee::STATUS_PENDING_OPERATIONS;
                $lockedGuarantee->current_coverage_amount = round((float) $lockedGuarantee->pending_coverage_amount, 2);
                $lockedGuarantee->is_boosted = false;
            } else {
                $coverage = $this->boostEvaluator->activeCoverageFor($lockedGuarantee, $bestLevel);
                $lockedGuarantee->effective_level_id = (int) $bestLevel->id;
                $lockedGuarantee->status = UserGuarantee::STATUS_ACTIVE;
                $lockedGuarantee->current_coverage_amount = $coverage['coverage_amount'];
                $lockedGuarantee->is_boosted = $coverage['is_boosted'];
                $lockedGuarantee->activated_at = $lockedGuarantee->activated_at ?: now();
            }

            $newEffectiveLevelId = $lockedGuarantee->effective_level_id ? (int) $lockedGuarantee->effective_level_id : null;
            $newStatus = (string) $lockedGuarantee->status;
            $newCoverage = round((float) $lockedGuarantee->current_coverage_amount, 2);

            $changed = $oldEffectiveLevelId !== $newEffectiveLevelId
                || $oldStatus !== $newStatus
                || $oldCoverage !== $newCoverage;

            if (! $changed) {
                $lockedGuarantee->save();

                return [
                    'changed' => false,
                    'reason' => 'already_synced',
                    'guarantee' => $lockedGuarantee->refresh(),
                    'level' => $bestLevel,
                ];
            }

            $isDowngrade = $this->isDowngrade($oldEffectiveLevelId, $newEffectiveLevelId);

            if ($isDowngrade) {
                $lockedGuarantee->downgraded_at = now();
            }

            $lockedGuarantee->meta = array_merge(
                is_array($lockedGuarantee->meta ?? null) ? $lockedGuarantee->meta : [],
                [
                    'last_downgrade_check_at' => now()->toDateTimeString(),
                    'last_downgrade_reference_type' => $referenceType,
                    'last_downgrade_reference_id' => $referenceId,
                ]
            );

            $lockedGuarantee->save();

            $transactionType = $isDowngrade ? 'upgrade' : 'lock';
            $logicalType = $isDowngrade ? 'downgrade' : 'coverage_sync';

            $idempotencyKey = $meta['idempotency_key'] ?? $this->buildIdempotencyKey(
                $lockedGuarantee,
                $oldEffectiveLevelId,
                $newEffectiveLevelId,
                $referenceType,
                $referenceId
            );

            // Idempotent: the same downgrade/sync (same key) is logged once.
            // Re-runs (e.g. repeated admin sync) skip the insert instead of
            // hitting the unique constraint.
            if (! GuaranteeTransaction::query()->where('idempotency_key', $idempotencyKey)->exists()) {
                GuaranteeTransaction::create([
                    'user_id' => (int) $lockedGuarantee->user_id,
                    'user_guarantee_id' => (int) $lockedGuarantee->id,
                    'type' => $transactionType,
                    'amount' => 0,
                    'coverage_amount' => $newCoverage,
                    'balance_before' => null,
                    'balance_after' => null,
                    'locked_before' => round((float) $lockedGuarantee->locked_amount, 2),
                    'locked_after' => round((float) $lockedGuarantee->locked_amount, 2),
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'reason' => $isDowngrade
                        ? 'Guarantee auto downgrade'
                        : 'Guarantee coverage status sync',
                    'idempotency_key' => $idempotencyKey,
                    'meta' => array_merge($meta, [
                        'logical_type' => $logicalType,
                        'stored_type' => $transactionType,
                        'old_effective_level_id' => $oldEffectiveLevelId,
                        'new_effective_level_id' => $newEffectiveLevelId,
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                        'old_coverage_amount' => $oldCoverage,
                        'new_coverage_amount' => $newCoverage,
                        'completed_operations_count' => (int) $lockedGuarantee->completed_operations_count,
                        'trust_score' => (float) $lockedGuarantee->trust_score,
                        'disputes_lost_count' => (int) $lockedGuarantee->disputes_lost_count,
                        'late_cancellations_count' => (int) $lockedGuarantee->late_cancellations_count,
                    ]),
                ]);
            }

            return [
                'changed' => true,
                'reason' => $isDowngrade ? 'downgraded' : 'synced',
                'guarantee' => $lockedGuarantee->refresh(),
                'level' => $bestLevel,
            ];
        });
    }

    public function downgradeExpiredGrace(
        UserGuarantee $guarantee,
        ?string $referenceType = null,
        ?int $referenceId = null,
        array $meta = []
    ): array {
        return DB::transaction(function () use ($guarantee, $referenceType, $referenceId, $meta) {
            /** @var UserGuarantee $lockedGuarantee */
            $lockedGuarantee = UserGuarantee::query()
                ->whereKey((int) $guarantee->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $lockedGuarantee->status !== UserGuarantee::STATUS_UNDERFUNDED) {
                return [
                    'changed' => false,
                    'reason' => 'not_underfunded',
                    'guarantee' => $lockedGuarantee,
                    'level' => $lockedGuarantee->effectiveLevel,
                ];
            }

            if ($lockedGuarantee->grace_until && $lockedGuarantee->grace_until->isFuture()) {
                return [
                    'changed' => false,
                    'reason' => 'grace_not_expired',
                    'guarantee' => $lockedGuarantee,
                    'level' => $lockedGuarantee->effectiveLevel,
                ];
            }

            $bestLevel = $this->bestFundedLevel($lockedGuarantee);
            $oldPurchasedLevelId = $lockedGuarantee->purchased_level_id ? (int) $lockedGuarantee->purchased_level_id : null;
            $oldEffectiveLevelId = $lockedGuarantee->effective_level_id ? (int) $lockedGuarantee->effective_level_id : null;
            $oldStatus = (string) $lockedGuarantee->status;
            $oldCoverage = round((float) $lockedGuarantee->current_coverage_amount, 2);

            if (! $bestLevel) {
                $lockedGuarantee->effective_level_id = null;
                $lockedGuarantee->status = UserGuarantee::STATUS_SUSPENDED;
                $lockedGuarantee->current_coverage_amount = 0;
                $lockedGuarantee->is_boosted = false;
                $lockedGuarantee->grace_until = null;
            } else {
                $lockedGuarantee->purchased_level_id = (int) $bestLevel->id;
                $lockedGuarantee->effective_level_id = $this->qualifiesForLevel($lockedGuarantee, $bestLevel) ? (int) $bestLevel->id : null;
                $lockedGuarantee->status = $lockedGuarantee->effective_level_id
                    ? UserGuarantee::STATUS_ACTIVE
                    : UserGuarantee::STATUS_PENDING_OPERATIONS;
                $lockedGuarantee->pending_coverage_amount = round((float) $bestLevel->pending_coverage_amount, 2);
                $lockedGuarantee->active_coverage_amount = round((float) $bestLevel->active_coverage_amount, 2);

                if ($lockedGuarantee->effective_level_id) {
                    $coverage = $this->boostEvaluator->activeCoverageFor($lockedGuarantee, $bestLevel);
                    $lockedGuarantee->current_coverage_amount = $coverage['coverage_amount'];
                    $lockedGuarantee->is_boosted = $coverage['is_boosted'];
                } else {
                    $lockedGuarantee->current_coverage_amount = round((float) $bestLevel->pending_coverage_amount, 2);
                    $lockedGuarantee->is_boosted = false;
                }

                $lockedGuarantee->grace_until = null;
            }

            $lockedGuarantee->downgraded_at = now();
            $lockedGuarantee->meta = array_merge(
                is_array($lockedGuarantee->meta ?? null) ? $lockedGuarantee->meta : [],
                [
                    'last_grace_downgrade_at' => now()->toDateTimeString(),
                    'last_grace_reference_type' => $referenceType,
                    'last_grace_reference_id' => $referenceId,
                ]
            );
            $lockedGuarantee->save();

            $logicalType = $bestLevel ? 'downgrade' : 'suspend';
            $storedType = $bestLevel ? 'upgrade' : 'lock';

            $idempotencyKey = $meta['idempotency_key'] ?? $this->buildGraceIdempotencyKey($lockedGuarantee, $referenceType, $referenceId);

            if (! GuaranteeTransaction::query()->where('idempotency_key', $idempotencyKey)->exists()) {
                GuaranteeTransaction::create([
                    'user_id' => (int) $lockedGuarantee->user_id,
                    'user_guarantee_id' => (int) $lockedGuarantee->id,
                    'type' => $storedType,
                    'amount' => 0,
                    'coverage_amount' => round((float) $lockedGuarantee->current_coverage_amount, 2),
                    'balance_before' => null,
                    'balance_after' => null,
                    'locked_before' => round((float) $lockedGuarantee->locked_amount, 2),
                    'locked_after' => round((float) $lockedGuarantee->locked_amount, 2),
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'reason' => $bestLevel
                        ? 'Guarantee grace period expired - downgraded'
                        : 'Guarantee grace period expired - suspended',
                    'idempotency_key' => $idempotencyKey,
                    'meta' => array_merge($meta, [
                        'logical_type' => $logicalType,
                        'stored_type' => $storedType,
                        'old_purchased_level_id' => $oldPurchasedLevelId,
                        'new_purchased_level_id' => $bestLevel ? (int) $bestLevel->id : null,
                        'old_effective_level_id' => $oldEffectiveLevelId,
                        'new_effective_level_id' => $lockedGuarantee->effective_level_id ? (int) $lockedGuarantee->effective_level_id : null,
                        'old_status' => $oldStatus,
                        'new_status' => (string) $lockedGuarantee->status,
                        'old_coverage_amount' => $oldCoverage,
                        'new_coverage_amount' => round((float) $lockedGuarantee->current_coverage_amount, 2),
                    ]),
                ]);
            }

            return [
                'changed' => true,
                'reason' => $bestLevel ? 'grace_downgraded' : 'grace_suspended',
                'guarantee' => $lockedGuarantee->refresh(),
                'level' => $bestLevel,
            ];
        });
    }

    protected function bestEligibleLevel(UserGuarantee $guarantee): ?GuaranteeLevel
    {
        $purchasedLevel = $guarantee->purchasedLevel;

        if (! $purchasedLevel) {
            return null;
        }

        return GuaranteeLevel::query()
            ->where('target_type', (string) $guarantee->target_type)
            ->where('is_active', 1)
            ->where('priority', '<=', (int) $purchasedLevel->priority)
            ->where('required_locked_amount', '<=', round((float) $guarantee->locked_amount, 2))
            ->orderByDesc('priority')
            ->orderByDesc('required_locked_amount')
            ->orderByDesc('id')
            ->get()
            ->first(fn (GuaranteeLevel $level) => $this->qualifiesForLevel($guarantee, $level));
    }

    protected function bestFundedLevel(UserGuarantee $guarantee): ?GuaranteeLevel
    {
        return GuaranteeLevel::query()
            ->where('target_type', (string) $guarantee->target_type)
            ->where('is_active', 1)
            ->where('required_locked_amount', '<=', round((float) $guarantee->locked_amount, 2))
            ->orderByDesc('priority')
            ->orderByDesc('required_locked_amount')
            ->orderByDesc('id')
            ->first();
    }

    protected function qualifiesForLevel(UserGuarantee $guarantee, GuaranteeLevel $level): bool
    {
        return (int) $guarantee->completed_operations_count >= (int) $level->required_completed_operations
            && (float) $guarantee->trust_score >= (float) $level->required_trust_score
            && (
                $level->max_lost_disputes === null
                || (int) $guarantee->disputes_lost_count <= (int) $level->max_lost_disputes
            )
            && (
                $level->max_late_cancellations === null
                || (int) $guarantee->late_cancellations_count <= (int) $level->max_late_cancellations
            );
    }

    protected function isDowngrade(?int $oldEffectiveLevelId, ?int $newEffectiveLevelId): bool
    {
        if (! $oldEffectiveLevelId) {
            return false;
        }

        if (! $newEffectiveLevelId) {
            return true;
        }

        $oldPriority = GuaranteeLevel::query()->whereKey($oldEffectiveLevelId)->value('priority');
        $newPriority = GuaranteeLevel::query()->whereKey($newEffectiveLevelId)->value('priority');

        return (int) $newPriority < (int) $oldPriority;
    }

    protected function buildIdempotencyKey(
        UserGuarantee $guarantee,
        ?int $oldEffectiveLevelId,
        ?int $newEffectiveLevelId,
        ?string $referenceType,
        ?int $referenceId
    ): string {
        $reference = $referenceType && $referenceId
            ? ($referenceType . ':' . $referenceId)
            : ('score:' . now()->format('YmdHis') . ':' . uniqid());

        return implode(':', [
            'guarantee_downgrade',
            (int) $guarantee->id,
            (int) ($oldEffectiveLevelId ?: 0),
            (int) ($newEffectiveLevelId ?: 0),
            $reference,
        ]);
    }

    protected function buildGraceIdempotencyKey(
        UserGuarantee $guarantee,
        ?string $referenceType,
        ?int $referenceId
    ): string {
        $reference = $referenceType && $referenceId
            ? ($referenceType . ':' . $referenceId)
            : ('grace:' . now()->format('YmdHis') . ':' . uniqid());

        return implode(':', [
            'guarantee_grace_downgrade',
            (int) $guarantee->id,
            $reference,
        ]);
    }
}
