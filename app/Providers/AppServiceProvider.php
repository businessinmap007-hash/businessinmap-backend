<?php

namespace App\Providers;

use App\Models\Deposit; // ✅ مهم
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

// ✅ imports مهمة جدًا
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator as PaginatorContract;

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

        if (! $this->app->runningInConsole()) {
            $segment = request()->segment(1);
            app()->setLocale(in_array($segment, ['ar', 'en'], true) ? $segment : 'ar');
        }

        $appUrl = config('app.url');
        if (is_string($appUrl) && $appUrl !== '') {
            URL::forceRootUrl($appUrl);

            if ($this->app->environment('production') && str_starts_with($appUrl, 'https://')) {
                URL::forceScheme('https');
            }
        }

        // ✅ Disputes counter for Admin-V2 layouts
           View::share('openDisputesCount', (int)\App\Models\Deposit::where('status', 'dispute')->count());

        // ✅ Admin V2 Menu
        View::composer('admin-v2.*', function ($view) {
            $user = auth()->user();
           
        });

        // ✅ Admin V2 Auto Paginator (يحقن $__a2_paginator لكل صفحات admin-v2.*)
        View::composer('admin-v2.*', function ($view) {
            $p = null;

            foreach ($view->getData() as $v) {
                if ($v instanceof LengthAwarePaginator || $v instanceof PaginatorContract) {
                    $p = $v;
                    break;
                }
            }

            $view->with('__a2_paginator', $p);
        });
    }
}