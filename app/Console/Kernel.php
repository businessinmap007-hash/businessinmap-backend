<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\DetectUnusedControllers::class,
        \App\Console\Commands\DetectUnusedModels::class,
        \App\Console\Commands\SendDueBookingReminders::class,
        \App\Console\Commands\DeleteExpiredSponsors::class,
        \App\Console\Commands\ProcessExpiredGuaranteeGrace::class,
        \App\Console\Commands\ProcessExpiredGuarantees::class,
        \App\Console\Commands\ProcessDisputes::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('sponsors:delete-expired')->everyTenMinutes();
        $schedule->command('bookings:send-due-reminders --limit=100')->everyMinute();
        $schedule->command('guarantees:process-expired-grace --limit=200')->hourly();
        $schedule->command('guarantees:process-expired --limit=200')->hourly();

        // Warnings are scheduled in days and the settlement window in weeks, so
        // hourly is already far finer than either needs.
        $schedule->command('disputes:process --limit=100')
            ->hourly()
            ->withoutOverlapping();

        // Fine appeal windows close on the scale of days; hourly is ample to
        // notice a closed window and capture the frozen hold.
        $schedule->command('fines:process --limit=100')
            ->hourly()
            ->withoutOverlapping();

        // Suspected-fraud flags from the rating graph. Daily and off-peak: the
        // signal moves on the scale of many operations, and it only suggests —
        // an admin still reviews every flag before anyone is fined or banned.
        $schedule->command('fraud:scan --limit=500')
            ->dailyAt('04:00')
            ->withoutOverlapping();

        // Safety net for missed gateway callbacks (Fawry money-in). No-op until
        // gateway credentials are set, so it is safe to schedule now.
        $schedule->command('wallet:reconcile-topups --minutes=15 --limit=200')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        // Same safety net for customer→merchant payments. No-op until creds set.
        $schedule->command('payments:reconcile-merchant --minutes=15 --limit=200')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        // The day-31 sweep (BIM-15.1). Daily and off-peak: the grace window is
        // measured in days, so nothing is gained by running it often, and this
        // is the one job that takes money irreversibly.
        $schedule->command('accounts:finalize-deletions --limit=100')
            ->dailyAt('03:30')
            ->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
