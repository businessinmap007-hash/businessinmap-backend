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
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('sponsors:delete-expired')->everyTenMinutes();
        $schedule->command('bookings:send-due-reminders --limit=100')->everyMinute();
        $schedule->command('guarantees:process-expired-grace --limit=200')->hourly();
        $schedule->command('guarantees:process-expired --limit=200')->hourly();

        // Safety net for missed gateway callbacks (Fawry money-in). No-op until
        // gateway credentials are set, so it is safe to schedule now.
        $schedule->command('wallet:reconcile-topups --minutes=15 --limit=200')
            ->everyFiveMinutes()
            ->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
