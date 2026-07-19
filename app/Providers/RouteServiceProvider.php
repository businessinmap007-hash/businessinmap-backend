<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/home';

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            // api_v2.php was loaded via a `require` at the tail of the now-deleted
            // routes/api.php (which held the v1 surface); it is loaded directly here.
            Route::middleware('api')->prefix('api')->group(base_path('routes/api_v2.php'));
            Route::middleware('web')->group(base_path('routes/web.php'));
            // routes/admin.php (legacy admin panel) deleted — AdminV2 replaces it.
            Route::middleware('web')->group(base_path('routes/admin_v2.php'));
            Route::middleware('web')->group(base_path('routes/business.php'));
        });
    }
}
