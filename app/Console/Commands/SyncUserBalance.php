<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Transaction;

class SyncUserBalance extends Command
{
    /**
     * اسم الأمر في التيرمنال
     */
    protected $signature = 'wallet:sync-balances';

    /**
     * وصف الأمر
     */
    protected $description = 'Sync all user balances from transactions table into users.balance column';

    /**
     * تنفيذ الأمر
     */
    public function handle()
    {
        $this->info("===== Starting User Balance Sync =====");

        $users = User::all();
        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        foreach ($users as $user) {
            $deposit = Transaction::where('user_id', $user->id)
                ->where('status', 'deposit')
                ->sum('price');

            $withdraw = Transaction::where('user_id', $user->id)
                ->where('status', 'withdrawal')
                ->sum('price');

            $balance = $deposit - $withdraw;

            // تحديث الرصيد في جدول users
            $user->balance = $balance;
            $user->save();

            $bar->advance();
        }

        $bar->finish();

        $this->newLine(2);
        $this->info("===== User balances synced successfully! =====");

        return Command::SUCCESS;
    }
}
