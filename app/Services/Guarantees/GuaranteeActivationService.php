<?php

namespace App\Services\Guarantees;

use App\Models\GuaranteeLevel;
use App\Models\GuaranteeTransaction;
use App\Models\User;
use App\Models\UserGuarantee;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GuaranteeActivationService
{
    public function __construct(
        protected GuaranteeScoreService $guaranteeScoreService
    ) {
    }

    public function autoActivate(User $user, GuaranteeLevel $level): UserGuarantee
    {
        if ($level->target_type !== $this->resolveTargetType($user)) {
            throw ValidationException::withMessages([
                'level' => 'مستوى الضمان غير مناسب لنوع المستخدم.',
            ]);
        }

        return DB::transaction(function () use ($user, $level) {
            $wallet = Wallet::query()
                ->where('user_id', (int) $user->id)
                ->lockForUpdate()
                ->first();

            if (! $wallet || (string) $wallet->status !== 'active') {
                throw ValidationException::withMessages([
                    'wallet' => 'المحفظة غير موجودة أو غير مفعلة.',
                ]);
            }

            $required = round((float) $level->required_locked_amount, 2);

            if ((float) $wallet->balance < $required) {
                throw ValidationException::withMessages([
                    'wallet' => 'الرصيد الحر غير كافٍ لتفعيل مستوى الضمان.',
                ]);
            }

            $status = $this->qualifiesForFullCoverage($user, $level)
                ? UserGuarantee::STATUS_ACTIVE
                : UserGuarantee::STATUS_PENDING_OPERATIONS;

            $coverage = $status === UserGuarantee::STATUS_ACTIVE
                ? (float) $level->active_coverage_amount
                : (float) $level->pending_coverage_amount;

            $walletBefore = [
                'balance' => round((float) $wallet->balance, 2),
                'locked' => round((float) $wallet->locked_balance, 2),
            ];

            $wallet->balance = round((float) $wallet->balance - $required, 2);
            $wallet->locked_balance = round((float) $wallet->locked_balance + $required, 2);
            $wallet->save();

            $guarantee = UserGuarantee::updateOrCreate(
                [
                    'user_id' => (int) $user->id,
                    'target_type' => $level->target_type,
                ],
                [
                    'purchased_level_id' => (int) $level->id,
                    'effective_level_id' => $status === UserGuarantee::STATUS_ACTIVE ? (int) $level->id : null,
                    'status' => $status,

                    'locked_amount' => $required,
                    'pending_coverage_amount' => (float) $level->pending_coverage_amount,
                    'active_coverage_amount' => (float) $level->active_coverage_amount,
                    'current_coverage_amount' => $coverage,
                    'used_coverage_amount' => 0,

                    'trust_score' => 0,
                    'activated_at' => $status === UserGuarantee::STATUS_ACTIVE ? now() : null,
                    'cancelled_at' => null,
                    'meta' => [
                        'auto_activated_at' => now()->toDateTimeString(),
                    ],
                ]
            );

            $user->forceFill([
                'guarantee_enabled' => true,
                'rating_enabled' => true,
                'commercial_operations_enabled' => true,
            ])->save();

            // A guarantee can't be a way to dodge service fees: force fee + rating.
            app(\App\Services\ServiceFeeConsentEnforcer::class)->enforce($user, 'تفعيل ضمان تلقائي');

            GuaranteeTransaction::create([
                'user_id' => (int) $user->id,
                'user_guarantee_id' => (int) $guarantee->id,
                'type' => 'lock',
                'amount' => $required,
                'coverage_amount' => $coverage,
                'balance_before' => $walletBefore['balance'],
                'balance_after' => round((float) $wallet->balance, 2),
                'locked_before' => $walletBefore['locked'],
                'locked_after' => round((float) $wallet->locked_balance, 2),
                'reason' => 'Auto guarantee activation',
                'idempotency_key' => 'guarantee_auto_activate_' . $user->id . '_' . $level->id,
                'meta' => [
                    'level_id' => (int) $level->id,
                    'level_code' => (string) $level->code,
                    'target_type' => (string) $level->target_type,
                    'status' => $status,
                ],
            ]);

            return $guarantee->refresh();
        });
    }

    protected function qualifiesForFullCoverage(User $user, GuaranteeLevel $level): bool
    {
        $guarantee = UserGuarantee::query()
            ->where('user_id', (int) $user->id)
            ->where('target_type', $level->target_type)
            ->first();

        if (! $guarantee) {
            return (int) $level->required_completed_operations <= 0
                && (float) $level->required_trust_score <= 0;
        }

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

    protected function resolveTargetType(User $user): string
    {
        return $user->isBusiness()
            ? GuaranteeLevel::TARGET_BUSINESS
            : GuaranteeLevel::TARGET_CLIENT;
    }
}