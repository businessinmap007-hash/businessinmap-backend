<?php

namespace App\Services;

use App\Models\GuaranteeLevel;
use App\Models\GuaranteeTransaction;
use App\Models\User;
use App\Models\UserGuarantee;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserGuaranteeService
{
    public function subscribe(
        User $user,
        GuaranteeLevel $level,
        ?string $targetType = null,
        ?string $reason = null
    ): UserGuarantee {
        $targetType = $targetType ?: $this->resolveTargetType($user);

        if ($level->target_type !== $targetType) {
            throw ValidationException::withMessages([
                'level' => 'مستوى الضمان غير مناسب لنوع المستخدم.',
            ]);
        }

        return DB::transaction(function () use ($user, $level, $targetType, $reason) {
            $wallet = $this->lockWallet($user, (float) $level->required_locked_amount);

            $status = $this->qualifiesForActive($user, $level)
                ? UserGuarantee::STATUS_ACTIVE
                : UserGuarantee::STATUS_PENDING_OPERATIONS;

            $coverage = $status === UserGuarantee::STATUS_ACTIVE
                ? (float) $level->active_coverage_amount
                : (float) $level->pending_coverage_amount;

            $guarantee = UserGuarantee::updateOrCreate(
                [
                    'user_id' => (int) $user->id,
                    'target_type' => $targetType,
                ],
                [
                    'purchased_level_id' => (int) $level->id,
                    'effective_level_id' => $status === UserGuarantee::STATUS_ACTIVE ? (int) $level->id : null,
                    'status' => $status,

                    'locked_amount' => (float) $level->required_locked_amount,
                    'pending_coverage_amount' => (float) $level->pending_coverage_amount,
                    'active_coverage_amount' => (float) $level->active_coverage_amount,
                    'current_coverage_amount' => $coverage,
                    'used_coverage_amount' => 0,

                    'completed_operations_count' => 0,
                    'cancelled_operations_count' => 0,
                    'late_cancellations_count' => 0,
                    'disputes_opened_count' => 0,
                    'disputes_lost_count' => 0,
                    'trust_score' => 0,

                    'activated_at' => $status === UserGuarantee::STATUS_ACTIVE ? now() : null,
                    'cancelled_at' => null,
                    'meta' => [
                        'subscribed_at' => now()->toDateTimeString(),
                        'reason' => $reason,
                    ],
                ]
            );

            $this->enableTrustFlags($user);

            $this->recordTransaction(
                guarantee: $guarantee,
                type: 'lock',
                amount: (float) $level->required_locked_amount,
                coverageAmount: $coverage,
                wallet: $wallet,
                reason: $reason ?: 'Guarantee subscription'
            );

            return $guarantee->refresh();
        });
    }

    public function covers(User $user, float $amount, ?string $targetType = null): bool
    {
        $guarantee = $this->activeGuarantee($user, $targetType);

        return $guarantee
            && $guarantee->isUsable()
            && $guarantee->covers($amount);
    }

    public function activeGuarantee(User $user, ?string $targetType = null): ?UserGuarantee
    {
        $targetType = $targetType ?: $this->resolveTargetType($user);

        return UserGuarantee::query()
            ->where('user_id', (int) $user->id)
            ->where('target_type', $targetType)
            ->whereIn('status', [
                UserGuarantee::STATUS_ACTIVE,
                UserGuarantee::STATUS_PENDING_OPERATIONS,
                UserGuarantee::STATUS_UNDERFUNDED,
            ])
            ->orderByDesc('id')
            ->first();
    }

    public function applyPenalty(
        User $user,
        float $amount,
        ?string $targetType = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $reason = null
    ): UserGuarantee {
        $amount = round(max($amount, 0), 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'قيمة الخصم غير صالحة.',
            ]);
        }

        $targetType = $targetType ?: $this->resolveTargetType($user);

        return DB::transaction(function () use ($user, $amount, $targetType, $referenceType, $referenceId, $reason) {
            $guarantee = $this->activeGuarantee($user, $targetType);

            if (! $guarantee) {
                throw ValidationException::withMessages([
                    'guarantee' => 'لا يوجد ضمان نشط لهذا المستخدم.',
                ]);
            }

            $wallet = Wallet::query()
                ->where('user_id', (int) $user->id)
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                throw ValidationException::withMessages([
                    'wallet' => 'لا توجد محفظة لهذا المستخدم.',
                ]);
            }

            $remaining = $amount;
            $balanceBefore = (float) $wallet->balance;
            $lockedBefore = (float) $wallet->locked_balance;

            if ((float) $wallet->balance > 0) {
                $fromBalance = min((float) $wallet->balance, $remaining);
                $wallet->balance = round((float) $wallet->balance - $fromBalance, 2);
                $remaining = round($remaining - $fromBalance, 2);
            }

            if ($remaining > 0) {
                if ((float) $wallet->locked_balance < $remaining) {
                    throw ValidationException::withMessages([
                        'locked_balance' => 'رصيد الضمان المجمد غير كافٍ لتنفيذ الخصم.',
                    ]);
                }

                $wallet->locked_balance = round((float) $wallet->locked_balance - $remaining, 2);
                $guarantee->locked_amount = round((float) $guarantee->locked_amount - $remaining, 2);
            }

            $wallet->save();

            $guarantee->used_coverage_amount = round((float) $guarantee->used_coverage_amount + $amount, 2);

            if ((float) $guarantee->locked_amount < (float) $guarantee->purchasedLevel->required_locked_amount) {
                $guarantee->status = UserGuarantee::STATUS_UNDERFUNDED;
                $guarantee->grace_until = now()->addDays(7);
            }

            $guarantee->save();

            $this->recordTransaction(
                guarantee: $guarantee,
                type: 'penalty',
                amount: $amount,
                coverageAmount: (float) $guarantee->current_coverage_amount,
                wallet: $wallet,
                referenceType: $referenceType,
                referenceId: $referenceId,
                reason: $reason ?: 'Guarantee penalty',
                meta: [
                    'balance_before' => $balanceBefore,
                    'locked_before' => $lockedBefore,
                    'balance_after' => (float) $wallet->balance,
                    'locked_after' => (float) $wallet->locked_balance,
                ]
            );

            return $guarantee->refresh();
        });
    }

    public function recordOperationResult(
        User $user,
        string $result,
        ?string $targetType = null,
        array $context = []
    ): ?UserGuarantee {
        $guarantee = $this->activeGuarantee($user, $targetType);

        if (! $guarantee) {
            return null;
        }

        match ($result) {
            'completed' => $guarantee->completed_operations_count++,
            'cancelled' => $guarantee->cancelled_operations_count++,
            'late_cancelled' => $guarantee->late_cancellations_count++,
            'dispute_opened' => $guarantee->disputes_opened_count++,
            'dispute_lost' => $guarantee->disputes_lost_count++,
            default => null,
        };

        $guarantee->trust_score = $this->calculateTrustScore($guarantee);
        $this->maybeActivatePurchasedLevel($guarantee);
        $guarantee->save();

        return $guarantee->refresh();
    }

    public function ensureFreeBalanceForFee(User $user, float $requiredAmount): void
    {
        $requiredAmount = round(max($requiredAmount, 0), 2);

        if ($requiredAmount <= 0) {
            return;
        }

        $wallet = Wallet::query()->where('user_id', (int) $user->id)->first();

        if (! $wallet || (float) $wallet->balance < $requiredAmount) {
            throw ValidationException::withMessages([
                'wallet' => 'يجب وجود رصيد حر كافٍ لخصم رسوم المنصة. الضمان لا يستخدم لدفع رسوم التشغيل.',
            ]);
        }
    }

    protected function lockWallet(User $user, float $amount): Wallet
    {
        $amount = round(max($amount, 0), 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'قيمة الضمان غير صالحة.',
            ]);
        }

        $wallet = Wallet::firstOrCreate(
            ['user_id' => (int) $user->id],
            [
                'balance' => 0,
                'locked_balance' => 0,
                'total_in' => 0,
                'total_out' => 0,
                'status' => 'active',
            ]
        );

        $wallet = Wallet::query()->where('id', (int) $wallet->id)->lockForUpdate()->first();

        if ((string) $wallet->status !== 'active') {
            throw ValidationException::withMessages([
                'wallet' => 'المحفظة غير مفعلة.',
            ]);
        }

        if ((float) $wallet->balance < $amount) {
            throw ValidationException::withMessages([
                'wallet' => 'الرصيد الحر غير كافٍ لتفعيل مستوى الضمان.',
            ]);
        }

        $wallet->balance = round((float) $wallet->balance - $amount, 2);
        $wallet->locked_balance = round((float) $wallet->locked_balance + $amount, 2);
        $wallet->save();

        return $wallet;
    }

    protected function enableTrustFlags(User $user): void
    {
        $user->forceFill([
            'guarantee_enabled' => true,
            'rating_enabled' => true,
            'commercial_operations_enabled' => true,
        ])->save();

        // A guarantee can't be a way to dodge service fees: force fee + rating.
        app(ServiceFeeConsentEnforcer::class)->enforce($user, 'شراء/تفعيل ضمان');
    }

    protected function resolveTargetType(User $user): string
    {
        return $user->isBusiness()
            ? GuaranteeLevel::TARGET_BUSINESS
            : GuaranteeLevel::TARGET_CLIENT;
    }

    protected function qualifiesForActive(User $user, GuaranteeLevel $level): bool
    {
        return (int) $level->required_completed_operations <= 0
            && (float) $level->required_trust_score <= 0;
    }

    protected function maybeActivatePurchasedLevel(UserGuarantee $guarantee): void
    {
        $level = $guarantee->purchasedLevel;

        if (! $level) {
            return;
        }

        $completedOk = (int) $guarantee->completed_operations_count >= (int) $level->required_completed_operations;
        $scoreOk = (float) $guarantee->trust_score >= (float) $level->required_trust_score;
        $lostDisputesOk = $level->max_lost_disputes === null
            || (int) $guarantee->disputes_lost_count <= (int) $level->max_lost_disputes;
        $lateCancelOk = $level->max_late_cancellations === null
            || (int) $guarantee->late_cancellations_count <= (int) $level->max_late_cancellations;

        if ($completedOk && $scoreOk && $lostDisputesOk && $lateCancelOk) {
            $guarantee->effective_level_id = (int) $level->id;
            $guarantee->status = UserGuarantee::STATUS_ACTIVE;
            $guarantee->current_coverage_amount = (float) $level->active_coverage_amount;
            $guarantee->activated_at = $guarantee->activated_at ?: now();
        } else {
            $guarantee->effective_level_id = null;
            $guarantee->status = UserGuarantee::STATUS_PENDING_OPERATIONS;
            $guarantee->current_coverage_amount = (float) $level->pending_coverage_amount;
        }
    }

    protected function calculateTrustScore(UserGuarantee $guarantee): float
    {
        $score = 50;

        $score += min((int) $guarantee->completed_operations_count * 2, 40);
        $score -= min((int) $guarantee->cancelled_operations_count * 3, 20);
        $score -= min((int) $guarantee->late_cancellations_count * 5, 25);
        $score -= min((int) $guarantee->disputes_lost_count * 15, 40);
        $score -= min((int) $guarantee->disputes_opened_count * 2, 15);

        return round(max(min($score, 100), 0), 2);
    }

    protected function recordTransaction(
        UserGuarantee $guarantee,
        string $type,
        float $amount = 0,
        float $coverageAmount = 0,
        ?Wallet $wallet = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $reason = null,
        array $meta = []
    ): GuaranteeTransaction {
        return GuaranteeTransaction::create([
            'user_id' => (int) $guarantee->user_id,
            'user_guarantee_id' => (int) $guarantee->id,
            'type' => $type,
            'amount' => round($amount, 2),
            'coverage_amount' => round($coverageAmount, 2),
            'balance_before' => $meta['balance_before'] ?? null,
            'balance_after' => $meta['balance_after'] ?? ($wallet ? (float) $wallet->balance : null),
            'locked_before' => $meta['locked_before'] ?? null,
            'locked_after' => $meta['locked_after'] ?? ($wallet ? (float) $wallet->locked_balance : null),
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reason' => $reason,
            'idempotency_key' => $meta['idempotency_key'] ?? null,
            'meta' => $meta,
        ]);
    }
}