<?php
/**
 * Created by PhpStorm.
 * User: Hassan Saeed
 * Date: 11/16/2017
 * Time: 9:29 AM
 */

namespace App\Providers;

use App\Models\Category;

use App\Models\Bus;
use App\Models\Hiringbus;
use App\Models\Hotel;
use App\Libraries\Main;
use App\Models\Maintenance;
use App\Models\Setting;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{

    /**
     * Bootstrap the application services.
     * @return void
     */
    public function boot()
    {
        /*
         * This composer runs for EVERY view — layout, partial, component, each
         * row-partial in a loop — and it issued two Category queries every
         * time. A page rendering fourteen views paid twenty-eight queries for
         * one unchanging list, and the admin panel does not read either
         * variable: they are here for the legacy v1 storefront.
         *
         * Memoised for the request. Not cached across requests: a category the
         * owner adds must show up on his next page load, not in five minutes.
         */
        $roots = null;
        $menuRoots = null;

        view()->composer('*', function ($view) use (&$roots, &$menuRoots) {
                $helper = new \App\Http\Helpers\Images();
                $main_helper = new \App\Http\Helpers\Main();
                $setting = new Setting();
                $main = new Main();

                $roots ??= Category::whereParentId(0)->orderBy('created_at', 'asc')->get();
                // Its own query before, for the first six of a list already in
                // hand — the slice costs nothing.
                $menuRoots ??= $roots->take(6);

                $categories = $roots;
                $menuCategories = $menuRoots;

                $view->with(compact('helper', 'main', 'setting', 'main_helper', 'categories', 'menuCategories'));
        });
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

}


