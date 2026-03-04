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
        protected ServiceFeeService $serviceFeeService
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
        ?int $targetId = null
    ): Deposit {
        $totalAmount = $this->normalizeAmount($totalAmount);

        if ($clientId === $businessId) {
            throw ValidationException::withMessages([
                'deposit' => 'Client and Business cannot be the same user.',
            ]);
        }

        if ($clientPercent < 0 || $businessPercent < 0 || ($clientPercent + $businessPercent) > 100) {
            throw ValidationException::withMessages([
                'percent' => 'Invalid percents. Sum must be <= 100.',
            ]);
        }

        $clientAmount   = $this->calcPart($totalAmount, $clientPercent);
        $businessAmount = $this->calcPart($totalAmount, $businessPercent);

        // ==========================================
        // ✅ Idempotency (outside TX): prevent duplicate Deposit for same target
        // ==========================================
        $targetType = $targetType ?? 'unknown';
        $targetId   = (int)($targetId ?? 0);

        if ($targetType !== 'unknown' && $targetId > 0) {
            // الأهم: وجود Frozen لنفس الهدف
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
            $clientId, $businessId,
            $totalAmount, $clientPercent, $businessPercent,
            $clientAmount, $businessAmount,
            $targetType, $targetId
        ) {

            // ==========================================
            // ✅ Idempotency (inside TX): race-safe
            // ==========================================
            if ($targetType !== 'unknown' && (int)$targetId > 0) {
                $existingFrozen = Deposit::query()
                    ->where('target_type', $targetType)
                    ->where('target_id', (int)$targetId)
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
                'target_id' => (int)($targetId ?? 0),

                'total_amount' => $totalAmount,
                'client_percent' => $clientPercent,
                'business_percent' => $businessPercent,
                'client_amount' => $clientAmount,
                'business_amount' => $businessAmount,

                // ✅ Enum (Deposit model casts status to DepositStatus)
                'status' => DepositStatus::FROZEN,

                'client_confirmed' => 0,
                'business_confirmed' => 0,
                'client_outside_bim' => 0,
                'business_outside_bim' => 0,

                'released_at' => null,
                'refunded_at' => null,
            ]);

            if ((float)$clientAmount > 0) {
                $this->hold($clientId, $clientAmount, $deposit, 'Hold client deposit');
            }

            if ((float)$businessAmount > 0) {
                $this->hold($businessId, $businessAmount, $deposit, 'Hold business deposit');
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

            $deposit = Deposit::where('id', $deposit->id)->lockForUpdate()->firstOrFail();

            // ✅ already released
            if ($deposit->released_at !== null || (string)$deposit->status === DepositStatus::RELEASED->value) {
                return $deposit;
            }

            // ✅ do not allow if refunded
            if ($deposit->refunded_at !== null || (string)$deposit->status === DepositStatus::REFUNDED->value) {
                throw ValidationException::withMessages([
                    'deposit' => 'Cannot release a refunded deposit.',
                ]);
            }

            // only frozen can be released
            if ($deposit->status !== DepositStatus::FROZEN) {
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

            $deposit = Deposit::where('id', $deposit->id)->lockForUpdate()->firstOrFail();

            // ✅ already refunded
            if ($deposit->refunded_at !== null || (string)$deposit->status === DepositStatus::REFUNDED->value) {
                return $deposit;
            }

            // ✅ do not allow if released
            if ($deposit->released_at !== null || (string)$deposit->status === DepositStatus::RELEASED->value) {
                throw ValidationException::withMessages([
                    'deposit' => 'Cannot refund a released deposit.',
                ]);
            }

            // only frozen can be refunded
            if ($deposit->status !== DepositStatus::FROZEN) {
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
}