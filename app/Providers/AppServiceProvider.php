<?php

namespace App\Providers;

use App\Models\BusinessServicePrice;
use App\Models\Deposit;
use App\Support\AdminAbility;
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
        $this->registerRouteBindings();
        $this->shareAdminV2Data();
        $this->registerAdminV2ExtraRoutes();
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

    private function registerRouteBindings(): void
    {
        Route::model('business_service_price', BusinessServicePrice::class);
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

    /**
     * Three AdminV2 routes registered here rather than in routes/admin_v2.php.
     * Found by AdminAbilityCoverageTest (BIM-14.1), which walks the router
     * rather than the route file — precisely so a route hiding somewhere like a
     * service provider cannot skip the ability checks.
     */
    private function registerAdminV2ExtraRoutes(): void
    {
        Route::middleware(['web', 'admin.v2'])
            ->prefix('admin')
            ->name('admin.')
            ->group(function () {
                Route::get('bookings/protection-preview', [\App\Http\Controllers\AdminV2\BookingProtectionController::class, 'preview'])
                    ->middleware('can:' . AdminAbility::OPERATIONS)
                    ->name('bookings.protectionPreview');

                Route::get('service-catalog-matrix', [\App\Http\Controllers\AdminV2\ServiceCatalogMatrixController::class, 'index'])
                    ->middleware('can:' . AdminAbility::CATALOG)
                    ->name('service-catalog-matrix.index');

                Route::post('service-catalog-matrix/apply', [\App\Http\Controllers\AdminV2\ServiceCatalogMatrixController::class, 'apply'])
                    ->middleware('can:' . AdminAbility::CATALOG)
                    ->name('service-catalog-matrix.apply');
            });
    }
}
