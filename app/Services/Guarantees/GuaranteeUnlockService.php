<?php

namespace App\Services\Guarantees;

use App\Models\GuaranteeLevel;
use App\Models\GuaranteeTransaction;
use App\Models\OperationGuarantor;
use App\Models\User;
use App\Models\UserGuarantee;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Unlock a purchased guarantee and return its backing money to the wallet.
 *
 * Activation moved `required` from wallet.balance → wallet.locked_balance and
 * recorded it as user_guarantees.locked_amount. Unlocking reverses that
 * (locked_balance → balance) — never minting money — but only when NONE of the
 * guarantee's coverage is currently frozen for an operation (its own or as a
 * friend co-guarantor). Any reservation blocks the unlock.
 */
class GuaranteeUnlockService
{
    /**
     * @return array{guarantee: UserGuarantee, amount: float, wallet: Wallet}
     */
    public function unlockToBalance(User $user, ?string $targetType = null): array
    {
        $targetType = $targetType ?: GuaranteeLevel::TARGET_CLIENT;

        return DB::transaction(function () use ($user, $targetType) {
            $guarantee = UserGuarantee::query()
                ->where('user_id', (int) $user->id)
                ->where('target_type', $targetType)
                ->whereIn('status', [
                    UserGuarantee::STATUS_ACTIVE,
                    UserGuarantee::STATUS_PENDING_OPERATIONS,
                    UserGuarantee::STATUS_UNDERFUNDED,
                ])
                ->lockForUpdate()
                ->first();

            if (! $guarantee) {
                throw ValidationException::withMessages(['guarantee' => 'لا يوجد ضمان نشط قابل للفكّ.']);
            }

            // Any coverage frozen for an active operation blocks the unlock.
            if (round((float) $guarantee->used_coverage_amount, 2) > 0) {
                throw ValidationException::withMessages([
                    'guarantee' => 'لا يمكن فكّ الضمان: جزء منه محجوز لعمليات جارية. أكمل أو أنهِ تلك العمليات أولًا.',
                ]);
            }

            // Belt-and-suspenders: this user still co-guaranteeing a friend's op.
            $activeAsGuarantor = OperationGuarantor::query()
                ->where('guarantor_user_id', (int) $user->id)
                ->where('status', OperationGuarantor::STATUS_ACCEPTED)
                ->exists();

            if ($activeAsGuarantor) {
                throw ValidationException::withMessages([
                    'guarantee' => 'لا يمكن فكّ الضمان: أنت ضامن لعملية صديق جارية.',
                ]);
            }

            $amount = round((float) $guarantee->locked_amount, 2);

            $wallet = Wallet::query()->where('user_id', (int) $user->id)->lockForUpdate()->first();

            if (! $wallet || (string) $wallet->status !== 'active') {
                throw ValidationException::withMessages(['wallet' => 'المحفظة غير موجودة أو غير مفعلة.']);
            }

            $balanceBefore = round((float) $wallet->balance, 2);
            $lockedBefore = round((float) $wallet->locked_balance, 2);

            if ($amount > 0) {
                if ($lockedBefore + 0.001 < $amount) {
                    throw ValidationException::withMessages([
                        'wallet' => 'الرصيد المحجوز في المحفظة لا يكفي لعكس قيمة الضمان.',
                    ]);
                }

                // Reverse of activation: return the backing money to free balance.
                $wallet->locked_balance = round($lockedBefore - $amount, 2);
                $wallet->balance = round($balanceBefore + $amount, 2);
                $wallet->save();
            }

            // Cancel the guarantee and clear its coverage.
            $guarantee->status = UserGuarantee::STATUS_CANCELLED;
            $guarantee->effective_level_id = null;
            $guarantee->locked_amount = 0;
            $guarantee->current_coverage_amount = 0;
            $guarantee->active_coverage_amount = 0;
            $guarantee->pending_coverage_amount = 0;
            $guarantee->used_coverage_amount = 0;
            $guarantee->cancelled_at = now();
            $guarantee->meta = array_merge(
                is_array($guarantee->meta ?? null) ? $guarantee->meta : [],
                ['unlocked_to_balance_at' => now()->toDateTimeString(), 'unlocked_amount' => $amount]
            );
            $guarantee->save();

            GuaranteeTransaction::create([
                'user_id' => (int) $user->id,
                'user_guarantee_id' => (int) $guarantee->id,
                'type' => 'unlock',
                'amount' => $amount,
                'coverage_amount' => 0,
                'balance_before' => $balanceBefore,
                'balance_after' => round((float) $wallet->balance, 2),
                'locked_before' => $lockedBefore,
                'locked_after' => round((float) $wallet->locked_balance, 2),
                'reason' => 'Guarantee unlocked to wallet balance',
                'idempotency_key' => 'guarantee_unlock_' . (int) $guarantee->id,
                'meta' => ['unlocked_amount' => $amount],
            ]);

            return ['guarantee' => $guarantee->refresh(), 'amount' => $amount, 'wallet' => $wallet->refresh()];
        });
    }
}
