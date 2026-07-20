<?php

namespace App\Services;

use App\Enums\DepositStatus;
use App\Enums\WalletTransactionType;
use App\Models\Deposit;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DepositsEscrowService
{
    public function __construct(
        protected ServiceFeeService $serviceFeeService,
        protected WalletService $walletService
    ) {}

    /* ==========================================================
     * CREATE (FREEZE)  +  Idempotency (no duplicate per target)
     * ========================================================== */

    public function create(
        int $clientId,
        int $businessId,
        $totalAmount,
        int $clientPercent = 0,
        int $businessPercent = 0,
        ?string $targetType = null,
        ?int $targetId = null,
        ?float $clientAmount = null,
        ?float $businessAmount = null
    ): Deposit {
        $targetType = $targetType ?? 'unknown';
        $targetId   = (int) ($targetId ?? 0);

        $usingDirectAmounts = $clientAmount !== null || $businessAmount !== null;

        if ($usingDirectAmounts) {
            // A single side may legitimately be 0 (e.g. business counter-hold
            // disabled or guarantee-covered). Only the TOTAL must be positive,
            // so tolerate a 0 sub-amount instead of rejecting it here.
            $clientAmount = (float) ($clientAmount ?? 0) > 0 ? $this->normalizeAmount($clientAmount) : '0.00';
            $businessAmount = (float) ($businessAmount ?? 0) > 0 ? $this->normalizeAmount($businessAmount) : '0.00';

            $totalAmount = $this->normalizeAmount((float) $clientAmount + (float) $businessAmount);

            $clientPercent = (float) $totalAmount > 0
                ? (int) round(((float) $clientAmount / (float) $totalAmount) * 100)
                : 0;

            $businessPercent = (float) $totalAmount > 0
                ? (int) round(((float) $businessAmount / (float) $totalAmount) * 100)
                : 0;
        } else {
            $totalAmount = $this->normalizeAmount($totalAmount);

            if ($clientPercent < 0 || $businessPercent < 0 || ($clientPercent + $businessPercent) > 100) {
                throw ValidationException::withMessages([
                    'percent' => 'Invalid percents. Sum must be <= 100.',
                ]);
            }

            $clientAmount   = $this->calcPart($totalAmount, $clientPercent);
            $businessAmount = $this->calcPart($totalAmount, $businessPercent);
        }

        if ($clientId === $businessId) {
            throw ValidationException::withMessages([
                'deposit' => 'Client and Business cannot be the same user.',
            ]);
        }

        if ((float) $totalAmount <= 0) {
            throw ValidationException::withMessages([
                'deposit' => 'Invalid total deposit amount.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Idempotency outside TX
        |--------------------------------------------------------------------------
        */
        if ($targetType !== 'unknown' && $targetId > 0) {
            $existingFrozen = Deposit::query()
                ->where('target_type', $targetType)
                ->where('target_id', $targetId)
                ->where('status', DepositStatus::FROZEN)
                ->orderByDesc('id')
                ->first();

            if ($existingFrozen) {
                return $existingFrozen;
            }
        }

        return DB::transaction(function () use (
            $clientId,
            $businessId,
            $totalAmount,
            $clientPercent,
            $businessPercent,
            $clientAmount,
            $businessAmount,
            $targetType,
            $targetId
        ) {
            /*
            |--------------------------------------------------------------------------
            | Idempotency inside TX
            |--------------------------------------------------------------------------
            */
            if ($targetType !== 'unknown' && (int) $targetId > 0) {
                $existingFrozen = Deposit::query()
                    ->where('target_type', $targetType)
                    ->where('target_id', (int) $targetId)
                    ->where('status', DepositStatus::FROZEN)
                    ->lockForUpdate()
                    ->first();

                if ($existingFrozen) {
                    return $existingFrozen;
                }
            }

            $deposit = Deposit::create([
                'client_id' => $clientId,
                'business_id' => $businessId,
                'target_type' => $targetType,
                'target_id' => (int) ($targetId ?? 0),

                'total_amount' => $totalAmount,
                'client_percent' => $clientPercent,
                'business_percent' => $businessPercent,
                'client_amount' => $clientAmount,
                'business_amount' => $businessAmount,

                'status' => DepositStatus::FROZEN,

                'client_confirmed' => 0,
                'business_confirmed' => 0,
                'client_outside_bim' => 0,
                'business_outside_bim' => 0,

                'released_at' => null,
                'refunded_at' => null,
            ]);

            if ((float) $clientAmount > 0) {
                $this->hold($clientId, $clientAmount, $deposit, 'Hold client deposit');
            }

            if ((float) $businessAmount > 0) {
                $this->hold($businessId, $businessAmount, $deposit, 'Hold business deposit');
            }

            // A deposit can't be a way to dodge service fees: force fee + rating
            // on whichever party actually posted a hold.
            $enforcer = app(\App\Services\ServiceFeeConsentEnforcer::class);
            if ((float) $clientAmount > 0) {
                $enforcer->enforceById((int) $clientId, 'استخدام ديبوزت (عميل)');
            }
            if ((float) $businessAmount > 0) {
                $enforcer->enforceById((int) $businessId, 'استخدام ديبوزت (نشاط)');
            }

            return $deposit;
        });
    }

    /* ==========================================================
     * EXECUTION FEE (BUSINESS PAYS) - Idempotent
     * ========================================================== */

    public function chargeExecutionFee(
        Deposit $deposit,
        ?float $baseAmount = null,
        string $feeCode = 'DEPOSIT_EXECUTION_FEE'
    ): float {
        if ($deposit->status !== DepositStatus::FROZEN) {
            throw ValidationException::withMessages([
                'deposit' => 'Execution fee can be charged only for frozen deposits.',
            ]);
        }

        $baseAmount = $baseAmount ?? (float) $deposit->total_amount;

        return DB::transaction(function () use ($deposit, $baseAmount, $feeCode) {

            // Row lock (see release()): serialize concurrent fee charges on the
            // same deposit and re-validate the status under the lock.
            $deposit = $this->lockDeposit($deposit);

            if ($deposit->status !== DepositStatus::FROZEN) {
                throw ValidationException::withMessages([
                    'deposit' => 'Execution fee can be charged only for frozen deposits.',
                ]);
            }

            // ✅ منع تكرار الخصم لنفس الـ deposit
            $already = WalletTransaction::query()
                ->where('reference_type', 'deposit')
                ->where('reference_id', (string)$deposit->id)
                ->where('type', WalletTransactionType::SERVICE_FEE->value)
                ->where('status', 'completed')
                ->first();

            if ($already) {
                return (float) $already->amount;
            }

            $fee = (float) $this->serviceFeeService->calculate(
                $feeCode,
                (float) $baseAmount,
                [
                    'deposit_id'   => $deposit->id,
                    'target_type'  => $deposit->target_type,
                    'target_id'    => $deposit->target_id,
                    'business_id'  => $deposit->business_id,
                    'client_id'    => $deposit->client_id,
                ]
            );

            if ($fee <= 0) {
                return 0.0;
            }

            $businessId = (int) $deposit->business_id;

            $wallet = $this->getOrCreateWallet($businessId);
            $this->ensureActive($wallet);

            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            if ((float)$wallet->balance < $fee) {
                throw ValidationException::withMessages([
                    'balance' => 'Insufficient balance to pay service fee.',
                ]);
            }

            $balanceBefore = (float) $wallet->balance;
            $lockedBefore  = (float) $wallet->locked_balance;

            $wallet->balance = number_format($balanceBefore - $fee, 2, '.', '');
            $wallet->save();

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $businessId,
                'status' => 'completed',
                'direction' => 'out',
                'type' => WalletTransactionType::SERVICE_FEE->value,
                'amount' => number_format($fee, 2, '.', ''),

                'balance_before' => number_format($balanceBefore, 2, '.', ''),
                'balance_after'  => number_format((float)$wallet->balance, 2, '.', ''),
                'locked_before'  => number_format($lockedBefore, 2, '.', ''),
                'locked_after'   => number_format((float)$wallet->locked_balance, 2, '.', ''),

                'reference_type' => 'deposit',
                'reference_id' => (string)$deposit->id,
                'idempotency_key' => null,
                'note' => 'Service fee on execution (provider pays)',
                'meta' => json_encode([
                    'deposit_id'  => $deposit->id,
                    'fee_code'    => $feeCode,
                    'base_amount' => $baseAmount,
                    'target_type' => $deposit->target_type,
                    'target_id'   => $deposit->target_id,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            return (float) number_format($fee, 2, '.', '');
        });
    }

    /* ==========================================================
     * RELEASE  + Guards + Idempotent internals
     * ========================================================== */

    public function release(Deposit $deposit): Deposit
    {
        return DB::transaction(function () use ($deposit) {

            // Re-read the deposit under a row lock so the status guards below are
            // authoritative: two concurrent release/refund calls on the same
            // deposit serialize here, and the second sees the final status.
            $deposit = $this->lockDeposit($deposit);

            $statusValue = $this->depositStatusValue($deposit);

            // ✅ already released
            if ($deposit->released_at !== null || $statusValue === DepositStatus::RELEASED->value) {
                return $deposit;
            }

            // ✅ do not allow if refunded
            if ($deposit->refunded_at !== null || $statusValue === DepositStatus::REFUNDED->value) {
                throw ValidationException::withMessages([
                    'deposit' => 'Cannot release a refunded deposit.',
                ]);
            }

            // only frozen can be released
            if ($statusValue !== DepositStatus::FROZEN->value) {
                throw ValidationException::withMessages([
                    'deposit' => 'Cannot release a non-frozen deposit.',
                ]);
            }

            if ((float)$deposit->client_amount > 0) {
                $this->releaseLockedToBalance(
                    (int)$deposit->client_id,
                    $deposit->client_amount,
                    $deposit,
                    WalletTransactionType::RELEASE,
                    'Release client deposit'
                );
            }

            if ((float)$deposit->business_amount > 0) {
                $this->releaseLockedToBalance(
                    (int)$deposit->business_id,
                    $deposit->business_amount,
                    $deposit,
                    WalletTransactionType::RELEASE,
                    'Release business deposit'
                );
            }

            $deposit->update([
                'status' => DepositStatus::RELEASED,
                'released_at' => now(),
            ]);

            return $deposit;
        });
    }

    /* ==========================================================
     * REFUND + Guards + Idempotent internals
     * ========================================================== */

    public function refund(Deposit $deposit, bool $refundClient = true, bool $refundBusiness = true): Deposit
    {
        if (!$refundClient && !$refundBusiness) {
            throw ValidationException::withMessages([
                'deposit' => 'Nothing to refund.',
            ]);
        }

        return DB::transaction(function () use ($deposit, $refundClient, $refundBusiness) {

            // Row lock (see release()): serialize concurrent release/refund on
            // the same deposit and make the status guards authoritative.
            $deposit = $this->lockDeposit($deposit);

            $statusValue = $this->depositStatusValue($deposit);

            // ✅ already refunded
            if ($deposit->refunded_at !== null || $statusValue === DepositStatus::REFUNDED->value) {
                return $deposit;
            }

            // ✅ do not allow if released
            if ($deposit->released_at !== null || $statusValue === DepositStatus::RELEASED->value) {
                throw ValidationException::withMessages([
                    'deposit' => 'Cannot refund a released deposit.',
                ]);
            }

            // only frozen can be refunded
            if ($statusValue !== DepositStatus::FROZEN->value) {
                throw ValidationException::withMessages([
                    'deposit' => 'Cannot refund a non-frozen deposit.',
                ]);
            }

            if ($refundClient && (float)$deposit->client_amount > 0) {
                $this->releaseLockedToBalance(
                    (int)$deposit->client_id,
                    $deposit->client_amount,
                    $deposit,
                    WalletTransactionType::REFUND,
                    'Refund client deposit'
                );
            }

            if ($refundBusiness && (float)$deposit->business_amount > 0) {
                $this->releaseLockedToBalance(
                    (int)$deposit->business_id,
                    $deposit->business_amount,
                    $deposit,
                    WalletTransactionType::REFUND,
                    'Refund business deposit'
                );
            }

            $deposit->update([
                'status' => DepositStatus::REFUNDED,
                'refunded_at' => now(),
            ]);

            return $deposit;
        });
    }

    /* ==========================================================
     * SPLIT (arbitration ruling) + Guards + Idempotent internals
     * ========================================================== */

    /**
     * Divide the escrow between the two parties by percentage — the ruling an
     * arbitrator actually reaches for when neither side is wholly at fault.
     *
     * Done in two moves, because money can only leave a wallet it is locked in:
     * first every hold is unlocked back to whoever posted it, then the
     * DIFFERENCE between what a party posted and what they were awarded is
     * transferred across. So a 100 client-side hold split 60/40 unlocks 100 to
     * the client and then transfers 40 to the business — nobody is ever asked
     * to pay out of their own pocket, since the payer is only ever moving money
     * that was just returned to them.
     */
    /**
     * Hand the WHOLE escrow to one side — the winner of a dispute.
     *
     * Deliberately NOT release()/refund(). Those unwind the escrow, returning
     * each hold to whoever posted it, and that is exactly right for the normal
     * booking lifecycle: the operation completed, the guarantee did its job,
     * nobody owes anybody. A ruling is the opposite situation — the loser's
     * hold is supposed to end up with the winner — and quietly changing
     * release() to do that would have moved money on every successful booking
     * in the platform.
     *
     * Same mechanic as split(), because this IS a split of 100/0.
     */
    public function awardTo(Deposit $deposit, string $winnerSide): Deposit
    {
        if (! in_array($winnerSide, ['client', 'business'], true)) {
            throw ValidationException::withMessages([
                'winner' => 'The winning side must be client or business.',
            ]);
        }

        return $this->settle(
            $deposit,
            $winnerSide === 'client' ? 100.0 : 0.0,
            $winnerSide === 'client' ? DepositStatus::REFUNDED : DepositStatus::RELEASED
        );
    }

    public function split(Deposit $deposit, float $clientPercent, float $businessPercent): Deposit
    {
        if ($clientPercent < 0 || $businessPercent < 0) {
            throw ValidationException::withMessages([
                'split' => 'Split percentages cannot be negative.',
            ]);
        }

        if (round($clientPercent + $businessPercent, 2) !== 100.00) {
            throw ValidationException::withMessages([
                'split' => 'Split percentages must total 100%.',
            ]);
        }

        return $this->settle($deposit, $clientPercent, DepositStatus::SPLIT);
    }

    /**
     * The one place escrow is divided between the parties.
     *
     * Money can only leave a wallet it is locked in, so this is two moves:
     * unlock every hold back to whoever posted it, then transfer the DIFFERENCE
     * between what a party posted and what they were awarded. The payer is only
     * ever moving money just returned to them, which is why no funding check is
     * needed.
     */
    private function settle(Deposit $deposit, float $clientPercent, DepositStatus $finalStatus): Deposit
    {
        return DB::transaction(function () use ($deposit, $clientPercent, $finalStatus) {

            // Row lock (see release()): serialize concurrent settlements on the
            // same deposit and make the status guards authoritative.
            $deposit = $this->lockDeposit($deposit);

            $statusValue = $this->depositStatusValue($deposit);

            // Already settled this way — idempotent, not an error.
            if ($statusValue === $finalStatus->value) {
                return $deposit;
            }

            if ($statusValue !== DepositStatus::FROZEN->value) {
                throw ValidationException::withMessages([
                    'deposit' => 'Cannot settle a deposit that is no longer frozen.',
                ]);
            }

            $clientHold = (float) $deposit->client_amount;
            $businessHold = (float) $deposit->business_amount;
            $total = $clientHold + $businessHold;

            // Derive the business share by subtraction, never by a second
            // rounding: two independently rounded halves can miss the total by
            // a cent, and that cent would be created or destroyed out of thin
            // air inside a money transaction.
            $clientShare = round(($total * $clientPercent) / 100.0, 2);

            // Step 1 — unlock each side's own hold back to its owner.
            if ($clientHold > 0) {
                $this->releaseLockedToBalance(
                    (int) $deposit->client_id,
                    $deposit->client_amount,
                    $deposit,
                    WalletTransactionType::REFUND,
                    'Unlock client deposit for ruling'
                );
            }

            if ($businessHold > 0) {
                $this->releaseLockedToBalance(
                    (int) $deposit->business_id,
                    $deposit->business_amount,
                    $deposit,
                    WalletTransactionType::REFUND,
                    'Unlock business deposit for ruling'
                );
            }

            // Step 2 — move only the difference between posted and awarded.
            $delta = round($clientShare - $clientHold, 2);

            if (abs($delta) >= 0.01) {
                $fromUserId = $delta > 0 ? (int) $deposit->business_id : (int) $deposit->client_id;
                $toUserId = $delta > 0 ? (int) $deposit->client_id : (int) $deposit->business_id;

                $this->walletService->transfer(
                    fromUserId: $fromUserId,
                    toUserId: $toUserId,
                    amount: abs($delta),
                    referenceType: 'deposit',
                    referenceId: (string) $deposit->id,
                    note: 'Dispute ruling settlement',
                    idempotencyKey: 'deposit_split_' . $deposit->id,
                    meta: [
                        'deposit_id' => $deposit->id,
                        'target_type' => $deposit->target_type,
                        'target_id' => $deposit->target_id,
                        'client_percent' => $clientPercent,
                        'client_share' => $clientShare,
                        'total' => $total,
                    ]
                );
            }

            // released_at doubles as "escrow settled at" — the status says which
            // way it settled, and leaving it null would show a settled deposit
            // as never having been settled at all.
            $deposit->update([
                'status' => $finalStatus,
                'released_at' => now(),
                'refunded_at' => $finalStatus === DepositStatus::REFUNDED ? now() : null,
            ]);

            return $deposit;
        });
    }

    /* ==========================================================
     * WALLET HELPERS (Idempotent HOLD / RELEASE / REFUND)
     * ========================================================== */

    protected function hold(int $userId, $amount, Deposit $deposit, string $note): void
    {
        $amount = $this->normalizeAmount($amount);

        // ✅ Idempotency: prevent duplicate HOLD (deposit + user)
        $already = WalletTransaction::query()
            ->where('reference_type', 'deposit')
            ->where('reference_id', (string)$deposit->id)
            ->where('type', WalletTransactionType::HOLD->value)
            ->where('status', 'completed')
            ->where('user_id', (int)$userId)
            ->first();

        if ($already) {
            return;
        }

        $wallet = $this->getOrCreateWallet($userId);
        $this->ensureActive($wallet);

        $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

        if ((float)$wallet->balance < (float)$amount) {
            throw ValidationException::withMessages([
                'balance' => 'Insufficient balance to hold deposit.',
            ]);
        }

        $balanceBefore = (float) $wallet->balance;
        $lockedBefore  = (float) $wallet->locked_balance;

        $wallet->balance        = number_format($balanceBefore - (float)$amount, 2, '.', '');
        $wallet->locked_balance = number_format($lockedBefore + (float)$amount, 2, '.', '');
        $wallet->save();

        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $userId,
            'status' => 'completed',
            'direction' => 'out',
            'type' => WalletTransactionType::HOLD->value,
            'amount' => $amount,

            'balance_before' => number_format($balanceBefore, 2, '.', ''),
            'balance_after'  => number_format((float)$wallet->balance, 2, '.', ''),
            'locked_before'  => number_format($lockedBefore, 2, '.', ''),
            'locked_after'   => number_format((float)$wallet->locked_balance, 2, '.', ''),

            'reference_type' => 'deposit',
            'reference_id' => (string)$deposit->id,
            'idempotency_key' => null,
            'note' => $note,
            'meta' => json_encode([
                'deposit_id' => $deposit->id,
                'target_type' => $deposit->target_type,
                'target_id' => $deposit->target_id,
            ], JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Move amount from locked_balance -> balance and create a transaction
     * ✅ Idempotent: prevent duplicate RELEASE/REFUND for same deposit + user + type
     */
    protected function releaseLockedToBalance(
        int $userId,
        $amount,
        Deposit $deposit,
        WalletTransactionType $type,
        string $note
    ): void {
        $amount = $this->normalizeAmount($amount);

        $already = WalletTransaction::query()
            ->where('reference_type', 'deposit')
            ->where('reference_id', (string)$deposit->id)
            ->where('type', $type->value)
            ->where('status', 'completed')
            ->where('user_id', (int)$userId)
            ->first();

        if ($already) {
            return;
        }

        $wallet = $this->getOrCreateWallet($userId);
        $this->ensureActive($wallet);

        $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

        if ((float)$wallet->locked_balance < (float)$amount) {
            throw ValidationException::withMessages([
                'locked_balance' => 'Not enough locked balance to release.',
            ]);
        }

        $balanceBefore = (float) $wallet->balance;
        $lockedBefore  = (float) $wallet->locked_balance;

        $wallet->locked_balance = number_format($lockedBefore - (float)$amount, 2, '.', '');
        $wallet->balance        = number_format($balanceBefore + (float)$amount, 2, '.', '');
        $wallet->save();

        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $userId,
            'status' => 'completed',
            'direction' => 'in',
            'type' => $type->value,
            'amount' => $amount,

            'balance_before' => number_format($balanceBefore, 2, '.', ''),
            'balance_after'  => number_format((float)$wallet->balance, 2, '.', ''),
            'locked_before'  => number_format($lockedBefore, 2, '.', ''),
            'locked_after'   => number_format((float)$wallet->locked_balance, 2, '.', ''),

            'reference_type' => 'deposit',
            'reference_id' => (string)$deposit->id,
            'idempotency_key' => null,
            'note' => $note,
            'meta' => json_encode([
                'deposit_id' => $deposit->id,
                'target_type' => $deposit->target_type,
                'target_id' => $deposit->target_id,
                'tx_type' => $type->value,
            ], JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Re-read a deposit row under a pessimistic lock. Must be called inside a
     * DB transaction. Serializes concurrent release/refund/fee operations on
     * the same deposit so the passed-in (possibly stale) model can't slip past
     * the status guards.
     */
    protected function lockDeposit(Deposit $deposit): Deposit
    {
        return Deposit::query()
            ->whereKey($deposit->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    protected function getOrCreateWallet(int $userId): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $userId],
            [
                'balance' => 0,
                'locked_balance' => 0,
                'total_in' => 0,
                'total_out' => 0,
                'status' => 'active',
            ]
        );
    }

    protected function ensureActive(Wallet $wallet): void
    {
        if ($wallet->status !== 'active') {
            throw ValidationException::withMessages([
                'wallet' => 'Wallet is blocked.',
            ]);
        }
    }

    protected function normalizeAmount($amount): string
    {
        if (!is_numeric($amount) || (float)$amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Invalid amount.',
            ]);
        }

        return number_format((float)$amount, 2, '.', '');
    }

    protected function calcPart(string $total, int $percent): string
    {
        if ($percent <= 0) return '0.00';

        $totalF = (float) $total;
        $pct    = (float) $percent;

        $value = ($totalF * $pct) / 100.0;
        if ($value < 0) $value = 0;

        return number_format($value, 2, '.', '');
    }
    protected function depositStatusValue(?Deposit $deposit): ?string
    {
        if (! $deposit) {
            return null;
        }

        $status = $deposit->status ?? null;

        if ($status instanceof \BackedEnum) {
            return $status->value;
        }

        return $status !== null ? (string) $status : null;
    }
}