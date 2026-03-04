<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class WalletLedgerRebuild extends Command
{
    protected $signature = 'wallet:rebuild-ledger {walletId?} {--dry-run}';
    protected $description = 'Rebuild wallet ledger snapshots and balances';

    public function handle(): int
    {
        $walletId = $this->argument('walletId');
        $dry = (bool) $this->option('dry-run');

        $wallets = Wallet::query()
            ->when($walletId, fn($q) => $q->where('id', (int)$walletId))
            ->orderBy('id')
            ->get();

        foreach ($wallets as $wallet) {

            DB::transaction(function () use ($wallet, $dry) {

                $txs = WalletTransaction::query()
                    ->where('wallet_id', $wallet->id)
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $balance = 0.0;
                $locked  = 0.0;
                $totalIn = 0.0;
                $totalOut= 0.0;

                foreach ($txs as $tx) {
                    $beforeB = $balance;
                    $beforeL = $locked;

                    $amount = (float)$tx->amount;
                    $dir    = (string)$tx->direction;
                    $affect = $this->guessAffect($tx); // ← هنا الاستخدام

                    $delta = ($dir === 'in') ? $amount : -$amount;

                    // بما إنك deposit/withdraw فقط
                    $balance += $delta;

                    if ($dir === 'in') $totalIn += $amount;
                    else $totalOut += $amount;

                    if (!$dry) {
                        \DB::table('wallet_transactions')
                            ->where('id', $tx->id)
                            ->update([
                                'balance_before' => $beforeB,
                                'balance_after'  => $balance,
                                'locked_before'  => $beforeL,
                                'locked_after'   => $locked,
                                'updated_at'     => now(),
                            ]);
                    }
                }

                if (!$dry) {
                    \DB::table('wallets')
                        ->where('id', $wallet->id)
                        ->update([
                            'balance'          => $balance,
                            'locked_balance'   => $locked,
                            'total_in'         => $totalIn,
                            'total_out'        => $totalOut,
                            'last_activity_at' => now(),
                            'updated_at'       => now(),
                        ]);
                }
            });
        }

        $this->info($dry ? 'DRY RUN completed.' : 'Rebuild completed.');
        return self::SUCCESS;
    }

    /**
     * ✅ هنا تضع الكود
     */
    private function guessAffect(WalletTransaction $tx): string
    {
        // لأن عندك deposit / withdraw فقط
        return 'balance';
    }
}