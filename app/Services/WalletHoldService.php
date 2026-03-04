<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\WalletHold;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalletHoldService
{
    /**
     * Hold amount from a user wallet (balance -> locked_balance)
     */
    public function hold(
        int $userId,
        float $amount,
        string $context,
        ?string $referenceType = null,
        ?int $referenceId = null,
        array $meta = []
    ): WalletHold {
        $amount = round((float)$amount, 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be > 0']);
        }

        return DB::transaction(function () use ($userId, $amount, $context, $referenceType, $referenceId, $meta) {
            $wallet = $this->getOrCreateWalletForUpdate($userId);

            // Check wallet status if exists
            if (($wallet->status ?? 'active') !== 'active') {
                throw ValidationException::withMessages(['wallet' => 'Wallet is not active']);
            }

            $balance = (float)$wallet->balance;
            if ($balance < $amount) {
                throw ValidationException::withMessages(['wallet' => 'Insufficient balance']);
            }

            // Move balance -> locked
            $wallet->balance = number_format($balance - $amount, 2, '.', '');
            $wallet->locked_balance = number_format(((float)$wallet->locked_balance) + $amount, 2, '.', '');
            $wallet->total_out = number_format(((float)$wallet->total_out) + $amount, 2, '.', '');
            $wallet->last_activity_at = now();
            $wallet->save();

            $hold = WalletHold::create([
                'wallet_id' => $wallet->id,
                'user_id' => $userId,
                'amount' => $amount,
                'status' => WalletHold::STATUS_HELD,
                'context' => $context,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'meta' => $meta ?: null,
            ]);

            return $hold;
        });
    }

    /**
     * Release a hold (locked_balance -> balance)
     */
    public function release(int $holdId, array $metaAppend = []): WalletHold
    {
        return DB::transaction(function () use ($holdId, $metaAppend) {
            /** @var WalletHold $hold */
            $hold = WalletHold::query()->lockForUpdate()->findOrFail($holdId);

            if ($hold->status !== WalletHold::STATUS_HELD && $hold->status !== WalletHold::STATUS_DISPUTED) {
                return $hold; // already released/void
            }

            $wallet = Wallet::query()->where('id', $hold->wallet_id)->lockForUpdate()->firstOrFail();

            $amount = (float)$hold->amount;

            // locked -> balance
            $wallet->locked_balance = number_format(((float)$wallet->locked_balance) - $amount, 2, '.', '');
            $wallet->balance = number_format(((float)$wallet->balance) + $amount, 2, '.', '');
            $wallet->total_in = number_format(((float)$wallet->total_in) + $amount, 2, '.', '');
            $wallet->last_activity_at = now();
            $wallet->save();

            // update meta
            $meta = is_array($hold->meta) ? $hold->meta : [];
            if ($metaAppend) $meta = array_merge($meta, $metaAppend);

            $hold->status = WalletHold::STATUS_RELEASED;
            $hold->meta = $meta ?: null;
            $hold->save();

            return $hold;
        });
    }

    public function disputeByReference(string $context, string $referenceType, int $referenceId): int
    {
        return WalletHold::query()
            ->where('context', $context)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('status', WalletHold::STATUS_HELD)
            ->update(['status' => WalletHold::STATUS_DISPUTED]);
    }

    public function releaseByReference(string $context, string $referenceType, int $referenceId): int
    {
        $holds = WalletHold::query()
            ->where('context', $context)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->whereIn('status', [WalletHold::STATUS_HELD, WalletHold::STATUS_DISPUTED])
            ->pluck('id')
            ->all();

        $count = 0;
        foreach ($holds as $id) {
            $this->release((int)$id, ['released_at' => now()->toDateTimeString()]);
            $count++;
        }
        return $count;
    }

    /**
     * Ensure wallet row exists, lock it FOR UPDATE.
     * Works whether you have unique wallet per user or not.
     */
    private function getOrCreateWalletForUpdate(int $userId): Wallet
    {
        $wallet = Wallet::query()
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();

        if ($wallet) return $wallet;

        // Create then lock it again inside same txn
        Wallet::create([
            'user_id' => $userId,
            'balance' => 0,
            'locked_balance' => 0,
            'total_in' => 0,
            'total_out' => 0,
            'status' => 'active',
        ]);

        return Wallet::query()
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->firstOrFail();
    }
}