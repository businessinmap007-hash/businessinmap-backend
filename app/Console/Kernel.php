<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * ملاحظة:
     * - نضع هنا الأوامر التي نريد التأكد من تسجيلها دائمًا.
     * - ونترك load() أيضًا لتحميل أي أوامر أخرى داخل app/Console/Commands.
     */
    protected $commands = [
        \App\Console\Commands\DetectUnusedControllers::class,
        \App\Console\Commands\DetectUnusedModels::class,

        // ✅ حذف الإعلانات المنتهية (Sponsors)
        \App\Console\Commands\DeleteExpiredSponsors::class,

        // لاحقًا يمكن إضافة:
        // \App\Console\Commands\DetectUnusedTables::class,
        // \App\Console\Commands\DetectBrokenRoutes::class,
        // \App\Console\Commands\DetectTableRelations::class,
        // \App\Console\Commands\DetectUnusedViews::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // ✅ تشغيل يومي (مناسب جدًا للمشاريع على shared hosting)
        $schedule->command('sponsors:delete-expired')->everyTenMinutes();

        // لو تحب تنظيف أسرع (اختياري):
        // $schedule->command('sponsors:delete-expired')->hourly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        // تحميل أي Commands موجودة داخل app/Console/Commands
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
