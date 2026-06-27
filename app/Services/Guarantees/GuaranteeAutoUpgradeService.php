<?php

namespace App\Services\Guarantees;

use App\Models\GuaranteeLevel;
use App\Models\GuaranteeTransaction;
use App\Models\User;
use App\Models\UserGuarantee;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class GuaranteeAutoUpgradeService
{
    public function autoUpgrade(
        User $user,
        ?string $targetType = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        array $meta = []
    ): array {
        $targetType = $targetType ?: $this->resolveTargetType($user);

        return DB::transaction(function () use ($user, $targetType, $referenceType, $referenceId, $meta) {
            $wallet = $this->lockedActiveWallet($user);

            $guarantee = UserGuarantee::query()
                ->where('user_id', (int) $user->id)
                ->where('target_type', $targetType)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            $availableForGuarantee = round(
                (float) $wallet->balance + (float) optional($guarantee)->locked_amount,
                2
            );

            $bestLevel = GuaranteeLevel::query()
                ->where('target_type', $targetType)
                ->where('is_active', 1)
                ->where('required_locked_amount', '<=', $availableForGuarantee)
                ->orderByDesc('priority')
                ->orderByDesc('required_locked_amount')
                ->orderByDesc('id')
                ->first();

            if (! $bestLevel) {
                return [
                    'changed' => false,
                    'reason' => 'no_affordable_level',
                    'guarantee' => $guarantee,
                    'level' => null,
                ];
            }

            return $this->applyLevelInsideTransaction(
                user: $user,
                wallet: $wallet,
                guarantee: $guarantee,
                level: $bestLevel,
                referenceType: $referenceType,
                referenceId: $referenceId,
                meta: array_merge($meta, ['auto' => true]),
                failWhenBalanceInsufficient: false
            );
        });
    }

    public function upgradeToLevel(
        User $user,
        GuaranteeLevel $level,
        ?string $referenceType = null,
        ?int $referenceId = null,
        array $meta = []
    ): array {
        if (! $level->is_active) {
            throw ValidationException::withMessages([
                'level' => 'مستوى الضمان غير مفعل.',
            ]);
        }

        if ($level->target_type !== $this->resolveTargetType($user)) {
            throw ValidationException::withMessages([
                'level' => 'مستوى الضمان غير مناسب لنوع المستخدم.',
            ]);
        }

        return DB::transaction(function () use ($user, $level, $referenceType, $referenceId, $meta) {
            $wallet = $this->lockedActiveWallet($user);

            $guarantee = UserGuarantee::query()
                ->where('user_id', (int) $user->id)
                ->where('target_type', (string) $level->target_type)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            return $this->applyLevelInsideTransaction(
                user: $user,
                wallet: $wallet,
                guarantee: $guarantee,
                level: $level,
                referenceType: $referenceType,
                referenceId: $referenceId,
                meta: array_merge($meta, ['manual_level_selection' => true]),
                failWhenBalanceInsufficient: true
            );
        });
    }

    protected function applyLevelInsideTransaction(
        User $user,
        Wallet $wallet,
        ?UserGuarantee $guarantee,
        GuaranteeLevel $level,
        ?string $referenceType,
        ?int $referenceId,
        array $meta,
        bool $failWhenBalanceInsufficient
    ): array {
        $currentLevel = $guarantee?->purchasedLevel;

        if ($currentLevel && (int) $currentLevel->priority > (int) $level->priority) {
            return [
                'changed' => false,
                'reason' => 'current_level_is_higher',
                'guarantee' => $guarantee,
                'level' => $currentLevel,
            ];
        }

        if ($currentLevel
            && (int) $currentLevel->id === (int) $level->id
            && $guarantee
            && (float) $guarantee->locked_amount >= (float) $level->required_locked_amount
            && $guarantee->status !== UserGuarantee::STATUS_UNDERFUNDED
        ) {
            $this->refreshCoverageStatus($guarantee, $level);
            $guarantee->save();

            return [
                'changed' => false,
                'reason' => 'already_on_level',
                'guarantee' => $guarantee->refresh(),
                'level' => $level,
            ];
        }

        $requiredLocked = round((float) $level->required_locked_amount, 2);
        $currentLocked = round((float) optional($guarantee)->locked_amount, 2);
        $additionalLock = max(round($requiredLocked - $currentLocked, 2), 0);

        if ($additionalLock > round((float) $wallet->balance, 2)) {
            if ($failWhenBalanceInsufficient) {
                throw ValidationException::withMessages([
                    'wallet' => 'الرصيد الحر غير كافٍ للانتقال إلى مستوى الضمان المختار.',
                ]);
            }

            return [
                'changed' => false,
                'reason' => 'insufficient_free_balance',
                'guarantee' => $guarantee,
                'level' => $level,
            ];
        }

        $walletBefore = [
            'balance' => round((float) $wallet->balance, 2),
            'locked' => round((float) $wallet->locked_balance, 2),
        ];

        if ($additionalLock > 0) {
            $wallet->balance = round((float) $wallet->balance - $additionalLock, 2);
            $wallet->locked_balance = round((float) $wallet->locked_balance + $additionalLock, 2);
            $wallet->last_activity_at = now();
            $wallet->save();
        }

        $guarantee = $guarantee ?: new UserGuarantee([
            'user_id' => (int) $user->id,
            'target_type' => (string) $level->target_type,
            'completed_operations_count' => 0,
            'cancelled_operations_count' => 0,
            'late_cancellations_count' => 0,
            'disputes_opened_count' => 0,
            'disputes_lost_count' => 0,
            'trust_score' => 0,
            'used_coverage_amount' => 0,
        ]);

        $oldLevelId = $guarantee->purchased_level_id;
        $oldStatus = $guarantee->status;

        $guarantee->purchased_level_id = (int) $level->id;
        $guarantee->locked_amount = $requiredLocked;
        $guarantee->pending_coverage_amount = (float) $level->pending_coverage_amount;
        $guarantee->active_coverage_amount = (float) $level->active_coverage_amount;
        $guarantee->cancelled_at = null;
        $guarantee->grace_until = null;

        $this->refreshCoverageStatus($guarantee, $level);

        if ((int) $oldLevelId !== (int) $level->id) {
            $guarantee->upgraded_at = now();
        }

        $guarantee->meta = array_merge(
            is_array($guarantee->meta ?? null) ? $guarantee->meta : [],
            [
                'last_auto_upgrade_check_at' => now()->toDateTimeString(),
                'last_upgrade_reference_type' => $referenceType,
                'last_upgrade_reference_id' => $referenceId,
            ]
        );

        $guarantee->save();

        $idempotencyKey = $meta['idempotency_key']
            ?? $this->buildIdempotencyKey($user, $level, $referenceType, $referenceId, $oldLevelId);

        if ($additionalLock > 0 || (int) $oldLevelId !== (int) $level->id || (string) $oldStatus !== (string) $guarantee->status) {
            $existingTx = $idempotencyKey
                ? GuaranteeTransaction::query()->where('idempotency_key', $idempotencyKey)->first()
                : null;

            if (! $existingTx) {
                GuaranteeTransaction::create([
                    'user_id' => (int) $user->id,
                    'user_guarantee_id' => (int) $guarantee->id,
                    'type' => $oldLevelId ? 'upgrade' : 'lock',
                    'amount' => $additionalLock,
                    'coverage_amount' => (float) $guarantee->current_coverage_amount,
                    'balance_before' => $walletBefore['balance'],
                    'balance_after' => round((float) $wallet->balance, 2),
                    'locked_before' => $walletBefore['locked'],
                    'locked_after' => round((float) $wallet->locked_balance, 2),
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'reason' => $oldLevelId ? 'Guarantee auto upgrade' : 'Guarantee auto lock',
                    'idempotency_key' => $idempotencyKey,
                    'meta' => array_merge($meta, [
                        'old_level_id' => $oldLevelId ? (int) $oldLevelId : null,
                        'new_level_id' => (int) $level->id,
                        'level_code' => (string) $level->code,
                        'old_status' => $oldStatus,
                        'new_status' => (string) $guarantee->status,
                        'additional_locked_amount' => $additionalLock,
                    ]),
                ]);
            }
        }

        $user->forceFill([
            'guarantee_enabled' => true,
            'rating_enabled' => true,
            'commercial_operations_enabled' => true,
        ])->save();

        return [
            'changed' => true,
            'reason' => $oldLevelId ? 'upgraded' : 'created',
            'guarantee' => $guarantee->refresh(),
            'level' => $level,
        ];
    }

    protected function refreshCoverageStatus(UserGuarantee $guarantee, GuaranteeLevel $level): void
    {
        if ($this->qualifiesForFullCoverage($guarantee, $level)) {
            $guarantee->effective_level_id = (int) $level->id;
            $guarantee->status = UserGuarantee::STATUS_ACTIVE;
            $guarantee->current_coverage_amount = (float) $level->active_coverage_amount;
            $guarantee->activated_at = $guarantee->activated_at ?: now();

            return;
        }

        $guarantee->effective_level_id = null;
        $guarantee->status = UserGuarantee::STATUS_PENDING_OPERATIONS;
        $guarantee->current_coverage_amount = (float) $level->pending_coverage_amount;
    }

    protected function qualifiesForFullCoverage(UserGuarantee $guarantee, GuaranteeLevel $level): bool
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

    protected function lockedActiveWallet(User $user): Wallet
    {
        $wallet = Wallet::query()
            ->where('user_id', (int) $user->id)
            ->lockForUpdate()
            ->first();

        if (! $wallet || (string) $wallet->status !== Wallet::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'wallet' => 'المحفظة غير موجودة أو غير مفعلة.',
            ]);
        }

        return $wallet;
    }

    protected function resolveTargetType(User $user): string
    {
        return $user->isBusiness()
            ? GuaranteeLevel::TARGET_BUSINESS
            : GuaranteeLevel::TARGET_CLIENT;
    }

    protected function buildIdempotencyKey(
        User $user,
        GuaranteeLevel $level,
        ?string $referenceType,
        ?int $referenceId,
        mixed $oldLevelId
    ): string {
        $reference = $referenceType && $referenceId
            ? ($referenceType . ':' . $referenceId)
            : ('manual:' . now()->format('YmdHis') . ':' . uniqid());

        return implode(':', [
            'guarantee_upgrade',
            (int) $user->id,
            (int) ($oldLevelId ?: 0),
            (int) $level->id,
            $reference,
        ]);
    }
}
