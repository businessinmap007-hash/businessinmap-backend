<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncUserBalanceAdvanced extends Command
{
    /**
     * اسم الأمر مع خيارات
     */
    protected $signature = 'wallet:sync-advanced 
                            {--dry-run : Run command without saving changes}
                            {--notify : Notify users about balance changes}';

    /**
     * وصف الأمر
     */
    protected $description = 'Advanced sync for user balances with logging, dry run mode & notifications';

    /**
     * تنفيذ الأمر
     */
    public function handle()
    {
        $dryRun  = $this->option('dry-run');
        $notify  = $this->option('notify');

        $this->info("===== Starting Advanced User Balance Sync =====");
        if ($dryRun) $this->warn("⚠ Dry Run Mode Enabled: No changes will be saved!");

        $updatedCount = 0;
        $logFile = 'wallet_sync_' . Carbon::now()->format('Y_m_d_His') . '.log';

        $users = User::all();
        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        foreach ($users as $user) {

            // حساب الرصيد
            $deposit = Transaction::where('user_id', $user->id)
                ->where('status', 'deposit')
                ->sum('price');

            $withdrawal = Transaction::where('user_id', $user->id)
                ->where('status', 'withdrawal')
                ->sum('price');

            $newBalance = $deposit - $withdrawal;
            $oldBalance = $user->balance ?? 0;

            // فقط إذا تغيّر الرصيد
            if ($newBalance != $oldBalance) {

                // كتابة اللوج
                Log::channel('single')->info("Balance Sync", [
                    'user_id'    => $user->id,
                    'old_balance' => $oldBalance,
                    'new_balance' => $newBalance,
                    'changed_at' => Carbon::now()->toDateTimeString(),
                    'dry_run' => $dryRun
                ]);

                if (!$dryRun) {
                    $user->balance = $newBalance;
                    $user->save();
                }

                if ($notify && !$dryRun) {
                    try {
                        $user->notify(new \App\Notifications\BalanceUpdatedNotification($oldBalance, $newBalance));
                    } catch (\Exception $e) {
                        Log::error("Notification Error for user {$user->id}: " . $e->getMessage());
                    }
                }

                $updatedCount++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("===== Advanced Sync Completed =====");
        $this->info("Updated Users: {$updatedCount}");
        $this->info("Log File: storage/logs/{$logFile}");

        if ($dryRun) {
            $this->warn("⚠ No changes saved because Dry Run mode was enabled.");
        }

        if ($notify) {
            $this->info("🔔 Notifications were sent to updated users.");
        }

        return Command::SUCCESS;
    }
}
