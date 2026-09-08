<?php

namespace App\Services\Guarantees;

use App\Models\Booking;
use App\Models\Dispute;
use App\Models\GuaranteeLevel;
use App\Models\GuaranteeTransaction;
use App\Models\OperationGuarantor;
use App\Models\User;
use App\Models\UserGuarantee;
use App\Models\Wallet;
use App\Services\DisputeService;
use App\Services\FineService;
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
    public function __construct(private readonly FineService $fines)
    {
    }

    /**
     * A mutual settlement closes the dispute (and releases the operation
     * freeze below) the moment the payee self-confirms receipt — no
     * arbitrator needed, see DisputeSettlementService::confirmReceived(). A
     * settlement fine on the losing side, though, is a SEPARATE admin action
     * that can come after that release. This window keeps unlock blocked for
     * a bit past a mutual resolution so that decision has time to land before
     * the money it would come from goes back to being ordinary free balance.
     */
    private const SETTLEMENT_FINE_WINDOW_HOURS = 48;

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
                throw ValidationException::withMessages(['guarantee' => __('لا يوجد ضمان نشط قابل للفكّ.')]);
            }

            // A granted guarantee is closed: it was never backed by wallet money,
            // so there is nothing to return, and it must never mint any.
            if ((bool) $guarantee->is_granted) {
                throw ValidationException::withMessages([
                    'guarantee' => __('الضمان الممنوح مغلق ولا يمكن إعادته إلى الرصيد.'),
                ]);
            }

            // Any coverage frozen for an active operation blocks the unlock.
            if (round((float) $guarantee->used_coverage_amount, 2) > 0) {
                throw ValidationException::withMessages([
                    'guarantee' => __('لا يمكن فكّ الضمان: جزء منه محجوز لعمليات جارية. أكمل أو أنهِ تلك العمليات أولًا.'),
                ]);
            }

            // Belt-and-suspenders: this user still co-guaranteeing a friend's op.
            $activeAsGuarantor = OperationGuarantor::query()
                ->where('guarantor_user_id', (int) $user->id)
                ->where('status', OperationGuarantor::STATUS_ACCEPTED)
                ->exists();

            if ($activeAsGuarantor) {
                throw ValidationException::withMessages([
                    'guarantee' => __('لا يمكن فكّ الضمان: أنت ضامن لعملية صديق جارية.'),
                ]);
            }

            if ($this->hasPendingSettlementFineDecision($user, $targetType)) {
                throw ValidationException::withMessages([
                    'guarantee' => __(
                        'لديك نزاع أُغلق بالتراضي مؤخرًا. انتظر :hours ساعة من إغلاقه (أو حتى يصدر قرار غرامة التسوية) قبل فكّ الضمان.',
                        ['hours' => self::SETTLEMENT_FINE_WINDOW_HOURS]
                    ),
                ]);
            }

            $amount = round((float) $guarantee->locked_amount, 2);

            $wallet = Wallet::query()->where('user_id', (int) $user->id)->lockForUpdate()->first();

            if (! $wallet || (string) $wallet->status !== 'active') {
                throw ValidationException::withMessages(['wallet' => __('المحفظة غير موجودة أو غير مفعلة.')]);
            }

            $balanceBefore = round((float) $wallet->balance, 2);
            $lockedBefore = round((float) $wallet->locked_balance, 2);

            if ($amount > 0) {
                if ($lockedBefore + 0.001 < $amount) {
                    throw ValidationException::withMessages([
                        'wallet' => __('الرصيد المحجوز في المحفظة لا يكفي لعكس قيمة الضمان.'),
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

    /**
     * True while a booking dispute this user was a party to (on the side this
     * guarantee covers) closed by mutual settlement within the last
     * SETTLEMENT_FINE_WINDOW_HOURS, and no settlement-fine decision has been
     * recorded for it yet. An admin ruling (any other resolution type) never
     * hits this: that path applies its own penalty/fine atomically inside the
     * same resolve() transaction that releases the guarantee, so there is no
     * gap for it to protect against.
     */
    private function hasPendingSettlementFineDecision(User $user, string $targetType): bool
    {
        $bookingColumn = $targetType === GuaranteeLevel::TARGET_BUSINESS ? 'business_id' : 'user_id';

        $recentMutualDisputeIds = Dispute::query()
            ->where('status', Dispute::STATUS_RESOLVED)
            ->where('resolution_type', DisputeService::RESOLUTION_MUTUAL)
            ->where('resolved_at', '>=', now()->subHours(self::SETTLEMENT_FINE_WINDOW_HOURS))
            ->where('disputeable_type', Booking::class)
            ->whereIn('disputeable_id', function ($query) use ($bookingColumn, $user) {
                $query->select('id')->from('bookings')->where($bookingColumn, (int) $user->id);
            })
            ->pluck('id');

        foreach ($recentMutualDisputeIds as $disputeId) {
            if (! $this->fines->settlementFineExistsFor((int) $disputeId)) {
                return true;
            }
        }

        return false;
    }
}
