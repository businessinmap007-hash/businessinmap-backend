<?php

namespace App\Providers;

use App\Models\Deposit;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        Carbon::setLocale('ar');

        $this->configureLocale();
        $this->configureUrl();
        $this->shareAdminV2Data();
        $this->loadAdminV2GuaranteeRoutes();
    }

    private function configureLocale(): void
    {
        if ($this->app->runningInConsole()) {
            app()->setLocale(config('app.locale', 'ar'));
            return;
        }

        $segment = request()->segment(1);

        app()->setLocale(
            in_array($segment, ['ar', 'en'], true)
                ? $segment
                : 'ar'
        );
    }

    private function configureUrl(): void
    {
        $appUrl = config('app.url');

        if (! is_string($appUrl) || trim($appUrl) === '') {
            return;
        }

        URL::forceRootUrl($appUrl);

        if (
            $this->app->environment('production') &&
            str_starts_with($appUrl, 'https://')
        ) {
            URL::forceScheme('https');
        }
    }

    private function shareAdminV2Data(): void
    {
        View::composer('admin-v2.*', function ($view) {
            $openDisputesCount = 0;

            try {
                $openDisputesCount = (int) Deposit::query()
                    ->where('status', 'dispute')
                    ->count();
            } catch (\Throwable $e) {
                $openDisputesCount = 0;
            }

            $view->with('openDisputesCount', $openDisputesCount);
        });
    }

    private function loadAdminV2GuaranteeRoutes(): void
    {
        $path = base_path('routes/admin_v2_guarantees.php');

        if (file_exists($path)) {
            Route::group([], $path);
        }
    }
}
