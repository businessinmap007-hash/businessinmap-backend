<?php

namespace App\Services\Wallet;

use App\Models\WalletTransaction;
use App\Services\WalletLedgerService as BaseWalletLedgerService;

class WalletLedgerService
{
    public function __construct(private readonly BaseWalletLedgerService $ledger)
    {
    }

    public function apply(int $walletId, int $userId, array $op): WalletTransaction
    {
        return $this->ledger->apply($walletId, $userId, $op);
    }

    public function deposit(int $walletId, int $userId, float $amount, array $op = []): WalletTransaction
    {
        return $this->ledger->deposit($walletId, $userId, $amount, $op);
    }

    public function withdraw(int $walletId, int $userId, float $amount, array $op = []): WalletTransaction
    {
        return $this->ledger->withdraw($walletId, $userId, $amount, $op);
    }

    public function hold(int $walletId, int $userId, float $amount, array $op = []): WalletTransaction
    {
        return $this->ledger->hold($walletId, $userId, $amount, $op);
    }
}
