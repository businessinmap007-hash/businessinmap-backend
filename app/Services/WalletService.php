<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\WalletPin;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class WalletService
{
    /**
     * Create wallet if missing (safe for first-time users)
     */
    public function getOrCreateWallet(int $userId): Wallet
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

    /**
     * Ensure wallet is active
     */
    protected function ensureActive(Wallet $wallet): void
    {
        if ($wallet->status !== 'active') {
            throw ValidationException::withMessages([
                'wallet' => 'Wallet is blocked.',
            ]);
        }
    }

    /**
     * Validate amount decimal
     */
    protected function normalizeAmount($amount): string
    {
        if (!is_numeric($amount) || $amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Invalid amount.',
            ]);
        }
        return number_format((float)$amount, 2, '.', '');
    }

    /**
     * (Optional) Idempotency guard: if key exists, return existing tx.
     */
    protected function findByIdempotency(Wallet $wallet, ?string $key): ?WalletTransaction
    {
        if (!$key) return null;

        return WalletTransaction::where('wallet_id', $wallet->id)
            ->where('idempotency_key', $key)
            ->first();
    }

    /**
     * Insert ledger record
     */
    protected function logTx(array $data): WalletTransaction
    {
       

        return WalletTransaction::create($data);
    }

    /**
     * Deposit (increase balance)
     */
    public function deposit(
        int $userId,
        $amount,
        ?string $note = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $idempotencyKey = null,
        array $meta = []
    ): WalletTransaction {
        $amount = $this->normalizeAmount($amount);

        return DB::transaction(function () use ($userId, $amount, $note, $referenceType, $referenceId, $idempotencyKey, $meta) {

            $wallet = $this->getOrCreateWallet($userId);
            $this->ensureActive($wallet);

            if ($existing = $this->findByIdempotency($wallet, $idempotencyKey)) {
                return $existing;
            }

            // lock row for concurrent safety
            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            $balanceBefore = $wallet->balance;
            $lockedBefore  = $wallet->locked_balance;

            $wallet->balance = $wallet->balance + $amount;
            $wallet->total_in = $wallet->total_in + $amount;
            $wallet->save();

            return $this->logTx([
                'wallet_id' => $wallet->id,
                'user_id' => $userId,
                'status' => 'completed',
                'direction' => 'in',
                'type' => 'deposit',
                'amount' => $amount,

                'balance_before' => $balanceBefore,
                'balance_after'  => $wallet->balance,
                'locked_before'  => $lockedBefore,
                'locked_after'   => $wallet->locked_balance,

                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'idempotency_key' => $idempotencyKey,
                'note' => $note,
                'meta' => $meta,
            ]);
        });
    }

    /**
     * Withdraw (decrease balance)
     */
    public function withdraw(
        int $userId,
        $amount,
        ?string $note = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $idempotencyKey = null,
        array $meta = []
    ): WalletTransaction {
        $amount = $this->normalizeAmount($amount);

        return DB::transaction(function () use ($userId, $amount, $note, $referenceType, $referenceId, $idempotencyKey, $meta) {

            $wallet = $this->getOrCreateWallet($userId);
            $this->ensureActive($wallet);

            if ($existing = $this->findByIdempotency($wallet, $idempotencyKey)) {
                return $existing;
            }

            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            if ($wallet->balance < $amount) {
                throw ValidationException::withMessages([
                    'balance' => 'Insufficient balance.',
                ]);
            }

            $balanceBefore = $wallet->balance;
            $lockedBefore  = $wallet->locked_balance;

            $wallet->balance = $wallet->balance - $amount;
            $wallet->total_out = $wallet->total_out + $amount;
            $wallet->save();

            return $this->logTx([
                'wallet_id' => $wallet->id,
                'user_id' => $userId,
                'status' => 'completed',
                'direction' => 'out',
                'type' => 'withdraw',
                'amount' => $amount,

                'balance_before' => $balanceBefore,
                'balance_after'  => $wallet->balance,
                'locked_before'  => $lockedBefore,
                'locked_after'   => $wallet->locked_balance,

                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'idempotency_key' => $idempotencyKey,
                'note' => $note,
                'meta' => $meta,
            ]);
        });
    }

    /**
     * Hold amount (move from balance -> locked_balance) : escrow pending
     */
    public function hold(
        int $userId,
        $amount,
        string $referenceType,
        string $referenceId,
        ?string $note = null,
        ?string $idempotencyKey = null,
        array $meta = []
    ): WalletTransaction {
        $amount = $this->normalizeAmount($amount);

        return DB::transaction(function () use ($userId, $amount, $referenceType, $referenceId, $note, $idempotencyKey, $meta) {

            $wallet = $this->getOrCreateWallet($userId);
            $this->ensureActive($wallet);

            if ($existing = $this->findByIdempotency($wallet, $idempotencyKey)) {
                return $existing;
            }

            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            if ($wallet->balance < $amount) {
                throw ValidationException::withMessages([
                    'balance' => 'Insufficient balance to hold.',
                ]);
            }

            $balanceBefore = $wallet->balance;
            $lockedBefore  = $wallet->locked_balance;

            $wallet->balance = $wallet->balance - $amount;
            $wallet->locked_balance = $wallet->locked_balance + $amount;
            $wallet->save();

            return $this->logTx([
                'wallet_id' => $wallet->id,
                'user_id' => $userId,
                'status' => 'completed',
                'direction' => 'out',
                'type' => 'hold',
                'amount' => $amount,

                'balance_before' => $balanceBefore,
                'balance_after'  => $wallet->balance,
                'locked_before'  => $lockedBefore,
                'locked_after'   => $wallet->locked_balance,

                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'idempotency_key' => $idempotencyKey,
                'note' => $note,
                'meta' => $meta,
            ]);
        });
    }

    /**
     * Release amount (move locked_balance -> balance) OR pay out to seller later.
     * Here we implement "back to buyer balance" release.
     * Later for orders: you may move to seller wallet using transfer().
     */
    public function release(
        int $userId,
        $amount,
        string $referenceType,
        string $referenceId,
        ?string $note = null,
        ?string $idempotencyKey = null,
        array $meta = []
    ): WalletTransaction {
        $amount = $this->normalizeAmount($amount);

        return DB::transaction(function () use ($userId, $amount, $referenceType, $referenceId, $note, $idempotencyKey, $meta) {

            $wallet = $this->getOrCreateWallet($userId);
            $this->ensureActive($wallet);

            if ($existing = $this->findByIdempotency($wallet, $idempotencyKey)) {
                return $existing;
            }

            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            if ($wallet->locked_balance < $amount) {
                throw ValidationException::withMessages([
                    'locked_balance' => 'Insufficient locked balance to release.',
                ]);
            }

            $balanceBefore = $wallet->balance;
            $lockedBefore  = $wallet->locked_balance;

            $wallet->locked_balance = $wallet->locked_balance - $amount;
            $wallet->balance = $wallet->balance + $amount;
            $wallet->save();

            return $this->logTx([
                'wallet_id' => $wallet->id,
                'user_id' => $userId,
                'status' => 'completed',
                'direction' => 'in',
                'type' => 'release',
                'amount' => $amount,

                'balance_before' => $balanceBefore,
                'balance_after'  => $wallet->balance,
                'locked_before'  => $lockedBefore,
                'locked_after'   => $wallet->locked_balance,

                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'idempotency_key' => $idempotencyKey,
                'note' => $note,
                'meta' => $meta,
            ]);
        });
    }

    /**
     * Refund: release from locked OR deposit back depending on your flow.
     * This implementation: move locked -> balance (same as release but type refund)
     */
    public function refund(
        int $userId,
        $amount,
        string $referenceType,
        string $referenceId,
        ?string $note = null,
        ?string $idempotencyKey = null,
        array $meta = []
    ): WalletTransaction {
        $amount = $this->normalizeAmount($amount);

        return DB::transaction(function () use ($userId, $amount, $referenceType, $referenceId, $note, $idempotencyKey, $meta) {

            $wallet = $this->getOrCreateWallet($userId);
            $this->ensureActive($wallet);

            if ($existing = $this->findByIdempotency($wallet, $idempotencyKey)) {
                return $existing;
            }

            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            if ($wallet->locked_balance < $amount) {
                throw ValidationException::withMessages([
                    'locked_balance' => 'Insufficient locked balance to refund.',
                ]);
            }

            $balanceBefore = $wallet->balance;
            $lockedBefore  = $wallet->locked_balance;

            $wallet->locked_balance = $wallet->locked_balance - $amount;
            $wallet->balance = $wallet->balance + $amount;
            $wallet->save();

            return $this->logTx([
                'wallet_id' => $wallet->id,
                'user_id' => $userId,
                'status' => 'completed',
                'direction' => 'in',
                'type' => 'refund',
                'amount' => $amount,

                'balance_before' => $balanceBefore,
                'balance_after'  => $wallet->balance,
                'locked_before'  => $lockedBefore,
                'locked_after'   => $wallet->locked_balance,

                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'idempotency_key' => $idempotencyKey,
                'note' => $note,
                'meta' => $meta,
            ]);
        });
    }

    /**
     * Transfer between 2 wallets (buyer -> seller), supports escrow release payout.
     */
    public function transfer(
        int $fromUserId,
        int $toUserId,
        $amount,
        string $referenceType,
        string $referenceId,
        ?string $note = null,
        ?string $idempotencyKey = null,
        array $meta = []
    ): array {
        $amount = $this->normalizeAmount($amount);

        return DB::transaction(function () use ($fromUserId, $toUserId, $amount, $referenceType, $referenceId, $note, $idempotencyKey, $meta) {

            $fromWallet = $this->getOrCreateWallet($fromUserId);
            $toWallet   = $this->getOrCreateWallet($toUserId);

            $this->ensureActive($fromWallet);
            $this->ensureActive($toWallet);

            // Lock both wallets in consistent order to avoid deadlocks
            $walletIds = [$fromWallet->id, $toWallet->id];
            sort($walletIds);

            $lockedWallets = Wallet::whereIn('id', $walletIds)->lockForUpdate()->get()->keyBy('id');
            $fromWallet = $lockedWallets[$fromWallet->id];
            $toWallet   = $lockedWallets[$toWallet->id];

            // idempotency check on FROM wallet
            if ($existing = $this->findByIdempotency($fromWallet, $idempotencyKey)) {
                return ['out' => $existing, 'in' => null];
            }

            if ($fromWallet->balance < $amount) {
                throw ValidationException::withMessages([
                    'balance' => 'Insufficient balance to transfer.',
                ]);
            }

            // FROM: out
            $fromBalanceBefore = $fromWallet->balance;
            $fromLockedBefore  = $fromWallet->locked_balance;

            $fromWallet->balance = $fromWallet->balance - $amount;
            $fromWallet->total_out = $fromWallet->total_out + $amount;
            $fromWallet->save();

            $outTx = $this->logTx([
                'wallet_id' => $fromWallet->id,
                'user_id' => $fromUserId,
                'status' => 'completed',
                'direction' => 'out',
                'type' => 'transfer',
                'amount' => $amount,

                'balance_before' => $fromBalanceBefore,
                'balance_after'  => $fromWallet->balance,
                'locked_before'  => $fromLockedBefore,
                'locked_after'   => $fromWallet->locked_balance,

                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'idempotency_key' => $idempotencyKey,
                'note' => $note,
                'meta' => array_merge($meta, ['to_user_id' => $toUserId]),
            ]);

            // TO: in
            $toBalanceBefore = $toWallet->balance;
            $toLockedBefore  = $toWallet->locked_balance;

            $toWallet->balance = $toWallet->balance + $amount;
            $toWallet->total_in = $toWallet->total_in + $amount;
            $toWallet->save();

            $inTx = $this->logTx([
                'wallet_id' => $toWallet->id,
                'user_id' => $toUserId,
                'status' => 'completed',
                'direction' => 'in',
                'type' => 'transfer',
                'amount' => $amount,

                'balance_before' => $toBalanceBefore,
                'balance_after'  => $toWallet->balance,
                'locked_before'  => $toLockedBefore,
                'locked_after'   => $toWallet->locked_balance,

                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'idempotency_key' => $idempotencyKey ? ($idempotencyKey.'_in') : null,
                'note' => $note,
                'meta' => array_merge($meta, ['from_user_id' => $fromUserId]),
            ]);

            return ['out' => $outTx, 'in' => $inTx];
        });
    }

    /**
     * PIN: set/update
     */
    public function setPin(int $userId, string $pin): void
    {
        if (!preg_match('/^\d{4,6}$/', $pin)) {
            throw ValidationException::withMessages([
                'pin' => 'PIN must be 4 to 6 digits.',
            ]);
        }

        WalletPin::updateOrCreate(
            ['user_id' => $userId],
            [
                'pin_hash' => Hash::make($pin),
                'attempts' => 0,
                'locked_until' => null,
            ]
        );
    }

    /**
     * PIN verify
     */
    public function verifyPin(int $userId, string $pin): bool
    {
        $row = WalletPin::where('user_id', $userId)->first();

        if (!$row) {
            throw ValidationException::withMessages([
                'pin' => 'PIN not set.',
            ]);
        }

        if ($row->locked_until && now()->lt($row->locked_until)) {
            throw ValidationException::withMessages([
                'pin' => 'PIN is temporarily locked.',
            ]);
        }

        if (!Hash::check($pin, $row->pin_hash)) {
            $row->attempts = $row->attempts + 1;

            // lock after 5 attempts for 15 minutes
            if ($row->attempts >= 5) {
                $row->locked_until = now()->addMinutes(15);
                $row->attempts = 0;
            }

            $row->save();
            return false;
        }

        // success
        $row->attempts = 0;
        $row->locked_until = null;
        $row->save();

        return true;
    }
    /**
 * Capture from locked_balance (final charge from hold)
        * locked_balance -> total_out (no balance change)
        */
        public function captureLocked(
            int $userId,
            $amount,
            string $referenceType,
            string $referenceId,
            ?string $note = null,
            ?string $idempotencyKey = null,
            array $meta = []
        ): WalletTransaction {
            $amount = $this->normalizeAmount($amount);

            return DB::transaction(function () use ($userId, $amount, $referenceType, $referenceId, $note, $idempotencyKey, $meta) {

                $wallet = $this->getOrCreateWallet($userId);
                $this->ensureActive($wallet);

                if ($existing = $this->findByIdempotency($wallet, $idempotencyKey)) {
                    return $existing;
                }

                $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

                if ($wallet->locked_balance < $amount) {
                    throw ValidationException::withMessages([
                        'locked_balance' => 'Insufficient locked balance to capture.',
                    ]);
                }

                $balanceBefore = $wallet->balance;
                $lockedBefore  = $wallet->locked_balance;

                // Capture: reduce locked, increase total_out
                $wallet->locked_balance = $wallet->locked_balance - $amount;
                $wallet->total_out = $wallet->total_out + $amount;
                $wallet->save();

                return $this->logTx([
                    'wallet_id' => $wallet->id,
                    'user_id' => $userId,
                    'status' => 'completed',
                    'direction' => 'out',
                    'type' => 'withdraw', // لا نضيف enum جديد الآن
                    'amount' => $amount,

                    'balance_before' => $balanceBefore,
                    'balance_after'  => $wallet->balance, // unchanged
                    'locked_before'  => $lockedBefore,
                    'locked_after'   => $wallet->locked_balance,

                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'idempotency_key' => $idempotencyKey,
                    'note' => $note,
                    'meta' => array_merge($meta, ['source' => 'locked_capture']),
                ]);
            });
        }

}
