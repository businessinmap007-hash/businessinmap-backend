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
        view()->composer('*', function ($view) {
                $helper = new \App\Http\Helpers\Images();
                $main_helper = new \App\Http\Helpers\Main();
                $setting = new Setting();
                $main = new Main();
                $categories = Category::whereParentId(0)->orderBy('created_at', 'asc')->get();
                $menuCategories = Category::whereParentId(0)->orderBy('created_at', 'asc')->limit(6)->get();
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


