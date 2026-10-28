<?php

namespace App\Providers;

use App\Models\BusinessServicePrice;
use App\Models\Deposit;
use App\Services\Posts\PostSubjectService;
use App\Support\AdminAbility;
use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton on purpose: PostSubjectService caches the subjects a page
        // of posts points at. The controller preloads them and PostResource
        // reads them back — with a fresh instance per resolution the cache
        // would be empty every time and the feed would go back to a query
        // per linked post.
        $this->app->singleton(PostSubjectService::class);

        // the zone an admin chose on «إعدادات المنصة» (a file: it must be known before the DB clock is pinned below)
        \App\Support\PlatformSettings::applyTimezone();

        $this->pinDatabaseClock();
    }

    /**
     * The database session runs on the platform's clock, wherever the database server sits.
     *
     * PHP writes every time as Cairo's digits (config app.timezone). A MySQL server in another country (or a managed
     * one that is simply on UTC) would evaluate its own NOW(), CURRENT_TIMESTAMP defaults and TIMESTAMP columns on ITS
     * clock — three hours off what Laravel wrote, and the offset changes with Egypt's daylight saving. So each
     * connection is told the current offset of the platform's zone when it opens (`SET time_zone`), computed at boot —
     * not frozen in a cached config. DB_TIMEZONE overrides it.
     */
    private function pinDatabaseClock(): void
    {
        foreach (['mysql', 'mariadb'] as $name) {
            if (config("database.connections.{$name}") && ! config("database.connections.{$name}.timezone")) {
                config(["database.connections.{$name}.timezone" => Carbon::now((string) config('app.timezone'))->format('P')]);
            }
        }
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // what one request reads many times is remembered for that request (see RequestMemo); a write drops it
        \App\Support\RequestMemo::watch(\App\Models\Promotion::class, 'promotions');
        \App\Support\RequestMemo::watch(\App\Models\PromotionTarget::class, 'promotions');
        \App\Support\RequestMemo::watch(\App\Models\OfferingPriceChange::class, 'price-history');
        \App\Support\RequestMemo::watch(\App\Models\PlatformServiceItemType::class, 'item-types');
        \App\Support\RequestMemo::watch(\App\Models\BusinessServicePrice::class, 'prices');
        \App\Support\RequestMemo::watch(\App\Models\User::class, 'prices');
        \App\Support\RequestMemo::watch(\App\Models\OfferingOption::class, 'offering-options');
        \App\Support\RequestMemo::watch(\App\Models\BookableItemRoomFeature::class, 'offering-options');
        \App\Support\RequestMemo::watch(\App\Models\BookableItemRoom::class, 'offering-options');
        \App\Support\RequestMemo::watch(\App\Models\HotelSeason::class, 'seasons');

        Carbon::setLocale('ar');

        // Every panel (admin-v2 + business) and the customer views use the a2
        // CSS, not Tailwind — but the paginator's default view is
        // pagination::tailwind, so {{ $rows->links() }} rendered unstyled
        // everywhere. Point it at the a2 paginator view instead.
        Paginator::defaultView('vendor.pagination.a2');

        $this->configureLocale();
        $this->configureUrl();
        $this->registerRouteBindings();
        $this->shareAdminV2Data();
        $this->registerAdminV2ExtraRoutes();

        // Service bookings mirror themselves onto the customer's personal agenda.
        \App\Models\Booking::observe(\App\Observers\BookingAgendaObserver::class);
    }

    private function configureLocale(): void
    {
        if ($this->app->runningInConsole()) {
            app()->setLocale(config('app.locale', 'ar'));
            return;
        }

        $supported = config('app.supported_locales', ['ar', 'en']);
        $segment = request()->segment(1);

        app()->setLocale(
            in_array($segment, $supported, true)
                ? $segment
                : ($supported[0] ?? 'ar')
        );
    }

    private function configureUrl(): void
    {
        $appUrl = config('app.url');

        if (! is_string($appUrl) || trim($appUrl) === '') {
            return;
        }

        // Pin the root URL only where there is no request to derive it from
        // (console commands, queued jobs, scheduled mail) or where APP_URL is
        // the authoritative public origin anyway (production — which also
        // keeps generated links immune to a poisoned Host header).
        //
        // Forcing it on every HTTP request is what broke the panel: with
        // APP_URL=http://127.0.0.1:8000, browsing AdminV2 at
        // http://localhost/testing/public/ still emitted
        // http://127.0.0.1:8000/... for asset() — so the stylesheet and every
        // post image loaded from a second server that is usually not running,
        // and the backend rendered unstyled with blank images.
        if ($this->app->runningInConsole() || $this->app->environment('production')) {
            URL::forceRootUrl($appUrl);
        }

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
        // Counted ONCE per request, not once per admin-v2 view: this composer
        // fires for the layout, every partial and every component, so a page
        // built from a dozen views ran the same COUNT a dozen times for one
        // badge.
        $openDisputes = null;

        View::composer('admin-v2.*', function ($view) use (&$openDisputes) {
            if ($openDisputes === null) {
                try {
                    $openDisputes = (int) Deposit::query()
                        ->where('status', 'dispute')
                        ->count();
                } catch (\Throwable $e) {
                    $openDisputes = 0;
                }
            }

            $view->with('openDisputesCount', $openDisputes);
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

                /*
                 * Retired 2026-08-08. It was a bulk root+children+services
                 * editor — which is exactly what «ربط الخدمات بالتصنيفات
                 * (جماعي)» already is, down to picking item types directly
                 * (CategoryServiceBulkController::resolveAllowedItemTypes takes
                 * explicit keys and only expands a branch when none were
                 * ticked). Two screens, one job. The name is kept so existing
                 * links and bookmarks land somewhere useful instead of 404.
                 */
                Route::get('service-catalog-matrix', function (\Illuminate\Http\Request $request) {
                    return redirect()->to(route('admin.categories.services-bulk.index', array_filter([
                        'root_id' => (int) $request->get('root_id', 0) ?: null,
                    ]), false));
                })
                    ->middleware('can:' . AdminAbility::CATALOG)
                    ->name('service-catalog-matrix.index');

                Route::post('service-catalog-matrix/apply', [\App\Http\Controllers\AdminV2\ServiceCatalogMatrixController::class, 'apply'])
                    ->middleware('can:' . AdminAbility::CATALOG)
                    ->name('service-catalog-matrix.apply');
            });
    }
}
