<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

final class WalletLedgerService
{
    /**
     * Apply movement on wallet and write ledger row with before/after snapshot.
     *
     * $op:
     * - direction: 'in'|'out'
     * - type: deposit|withdraw|hold|release|refund|fee ...
     * - amount: float
     * - idempotency_key?: string|null
     * - reference_type?: string|null
     * - reference_id?: string|null
     * - status?: pending|completed|failed|reversed
     * - note_id?: int|null   (FK to wallet_note_templates)
     * - meta?: array|string|null
     * - affect?: 'balance'|'locked'  (default 'balance')
     *
     * Important:
     * - Idempotency is checked per wallet: (wallet_id + idempotency_key)
     * - wallet row is locked FOR UPDATE
     */
    public function apply(int $walletId, int $userId, array $op): WalletTransaction
    {
        $direction = (string)($op['direction'] ?? '');
        $type      = (string)($op['type'] ?? '');
        $amount    = (float)($op['amount'] ?? 0);

        if (!in_array($direction, ['in', 'out'], true)) {
            throw new \InvalidArgumentException('Invalid direction');
        }
        if ($type === '') {
            throw new \InvalidArgumentException('Type is required');
        }
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be > 0');
        }

        $affect = (string)($op['affect'] ?? 'balance'); // balance | locked
        if (!in_array($affect, ['balance', 'locked'], true)) {
            throw new \InvalidArgumentException('Invalid affect');
        }

        return DB::transaction(function () use ($walletId, $userId, $op, $direction, $type, $amount, $affect) {

            // ✅ Idempotency (per wallet)
            $idem = trim((string)($op['idempotency_key'] ?? ''));
            if ($idem !== '') {
                $existing = WalletTransaction::query()
                    ->where('wallet_id', $walletId)
                    ->where('idempotency_key', $idem)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            /** @var Wallet $wallet */
            $wallet = Wallet::query()
                ->whereKey($walletId)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int)$wallet->user_id !== (int)$userId) {
                throw new \RuntimeException('Wallet user mismatch');
            }

            $beforeBalance = (float)($wallet->balance ?? 0);
            $beforeLocked  = (float)($wallet->locked_balance ?? 0);

            $delta = $direction === 'in' ? $amount : -$amount;

            // ✅ Update wallet balance/locked
            $newBalance = $beforeBalance;
            $newLocked  = $beforeLocked;

            if ($affect === 'balance') {
                $newBalance = $beforeBalance + $delta;
                if ($newBalance < 0) {
                    throw new \RuntimeException('Insufficient wallet balance');
                }
            } else { // locked
                $newLocked = $beforeLocked + $delta;
                if ($newLocked < 0) {
                    throw new \RuntimeException('Insufficient locked balance');
                }
            }

            // ✅ Update totals (only when affecting balance)
            $totalIn  = (float)($wallet->total_in ?? 0);
            $totalOut = (float)($wallet->total_out ?? 0);

            if ($affect === 'balance') {
                if ($direction === 'in') $totalIn += $amount;
                else $totalOut += $amount;
            }

            $wallet->balance          = $newBalance;
            $wallet->locked_balance   = $newLocked;
            $wallet->total_in         = $totalIn;
            $wallet->total_out        = $totalOut;
            $wallet->last_activity_at = now();
            $wallet->save();

            $afterBalance = (float)$wallet->balance;
            $afterLocked  = (float)$wallet->locked_balance;

            // ✅ meta normalization (longtext)
            $meta = $op['meta'] ?? null;
            if (is_array($meta) || is_object($meta)) {
                $meta = json_encode($meta, JSON_UNESCAPED_UNICODE);
            }

            return WalletTransaction::query()->create([
                'wallet_id'       => (int)$wallet->id,
                'user_id'         => (int)$userId,
                'status'          => (string)($op['status'] ?? 'completed'),
                'direction'       => $direction,
                'type'            => $type,
                'amount'          => $amount,

                'balance_before'  => $beforeBalance,
                'balance_after'   => $afterBalance,
                'locked_before'   => $beforeLocked,
                'locked_after'    => $afterLocked,

                'reference_type'  => $op['reference_type'] ?? null,
                'reference_id'    => $op['reference_id'] ?? null,
                'idempotency_key' => $idem !== '' ? $idem : null,

                'note_id'         => isset($op['note_id']) ? (int)$op['note_id'] : null,
                'meta'            => $meta,
            ]);
        });
    }

    public function deposit(int $walletId, int $userId, float $amount, array $op = []): WalletTransaction
    {
        return $this->apply($walletId, $userId, array_merge($op, [
            'direction' => 'in',
            'type'      => $op['type'] ?? 'deposit',
            'amount'    => $amount,
            'affect'    => 'balance',
        ]));
    }

    public function withdraw(int $walletId, int $userId, float $amount, array $op = []): WalletTransaction
    {
        return $this->apply($walletId, $userId, array_merge($op, [
            'direction' => 'out',
            'type'      => $op['type'] ?? 'withdraw',
            'amount'    => $amount,
            'affect'    => 'balance',
        ]));
    }

    /**
     * Move from balance -> locked using two ledger rows.
     * Returns the "locked in" transaction.
     */
    public function hold(int $walletId, int $userId, float $amount, array $op = []): WalletTransaction
    {
        return DB::transaction(function () use ($walletId, $userId, $amount, $op) {
            $idemBase = trim((string)($op['idempotency_key'] ?? ''));

            // out from balance
            $this->apply($walletId, $userId, array_merge($op, [
                'direction'       => 'out',
                'type'            => $op['type_out'] ?? 'hold_out',
                'amount'          => $amount,
                'affect'          => 'balance',
                'idempotency_key' => $idemBase !== '' ? ($idemBase . ':out') : null,
            ]));

            // in to locked
            return $this->apply($walletId, $userId, array_merge($op, [
                'direction'       => 'in',
                'type'            => $op['type_in'] ?? 'hold_in',
                'amount'          => $amount,
                'affect'          => 'locked',
                'idempotency_key' => $idemBase !== '' ? ($idemBase . ':in') : null,
            ]));
        });
    }
}