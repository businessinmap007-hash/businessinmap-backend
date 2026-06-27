<?php

namespace App\Services\Guarantees;

use App\Models\GuaranteeTransaction;
use App\Models\User;
use App\Models\UserGuarantee;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GuaranteePenaltyService
{
    public function __construct(
        protected GuaranteeCoverageService $guaranteeCoverageService
    ) {
    }

    public function applyPenalty(
        User $user,
        float $amount,
        ?string $targetType = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $reason = null,
        array $meta = []
    ): UserGuarantee {
        $amount = round(max($amount, 0), 2);
        $idempotencyKey = $meta['idempotency_key'] ?? null;

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'قيمة الخصم غير صالحة.',
            ]);
        }

        return DB::transaction(function () use (
            $user,
            $amount,
            $targetType,
            $referenceType,
            $referenceId,
            $reason,
            $meta,
            $idempotencyKey
        ) {
            if ($idempotencyKey) {
                $existingTx = GuaranteeTransaction::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existingTx) {
                    return UserGuarantee::query()
                        ->where('id', (int) $existingTx->user_guarantee_id)
                        ->firstOrFail();
                }
            }

            $guarantee = $this->guaranteeCoverageService->activeGuarantee($user, $targetType);

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

            if ((string) $wallet->status !== 'active') {
                throw ValidationException::withMessages([
                    'wallet' => 'المحفظة غير مفعلة.',
                ]);
            }

            $balanceBefore = round((float) $wallet->balance, 2);
            $lockedBefore = round((float) $wallet->locked_balance, 2);

            $remaining = $amount;
            $fromBalance = 0.0;
            $fromGuarantee = 0.0;

            if ($balanceBefore > 0) {
                $fromBalance = min($balanceBefore, $remaining);
                $wallet->balance = round($balanceBefore - $fromBalance, 2);
                $remaining = round($remaining - $fromBalance, 2);
            }

            if ($remaining > 0) {
                if ($lockedBefore < $remaining) {
                    throw ValidationException::withMessages([
                        'locked_balance' => 'رصيد الضمان المجمد غير كافٍ لتنفيذ الخصم.',
                    ]);
                }

                $fromGuarantee = $remaining;
                $wallet->locked_balance = round((float) $wallet->locked_balance - $fromGuarantee, 2);
                $guarantee->locked_amount = round((float) $guarantee->locked_amount - $fromGuarantee, 2);
                $remaining = 0.0;
            }

            $wallet->save();

            $requiredLocked = $guarantee->purchasedLevel
                ? round((float) $guarantee->purchasedLevel->required_locked_amount, 2)
                : round((float) $guarantee->locked_amount, 2);

            if ((float) $guarantee->locked_amount < $requiredLocked) {
                $guarantee->status = UserGuarantee::STATUS_UNDERFUNDED;
                $guarantee->grace_until = now()->addDays(7);
            }

            $guarantee->used_coverage_amount = round((float) $guarantee->used_coverage_amount + $amount, 2);
            $guarantee->save();

            GuaranteeTransaction::create([
                'user_id' => (int) $user->id,
                'user_guarantee_id' => (int) $guarantee->id,
                'type' => 'penalty',
                'amount' => $amount,
                'coverage_amount' => (float) $guarantee->current_coverage_amount,
                'balance_before' => $balanceBefore,
                'balance_after' => round((float) $wallet->balance, 2),
                'locked_before' => $lockedBefore,
                'locked_after' => round((float) $wallet->locked_balance, 2),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reason' => $reason ?: 'Guarantee penalty',
                'idempotency_key' => $idempotencyKey,
                'meta' => array_merge($meta, [
                    'from_balance' => $fromBalance,
                    'from_guarantee' => $fromGuarantee,
                ]),
            ]);

            return $guarantee->refresh();
        });
    }
}