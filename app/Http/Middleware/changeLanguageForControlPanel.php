<?php

namespace App\Http\Middleware;

use App\Libraries\FirebasePushNotifications\config;
use Closure;

class changeLanguageForControlPanel
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

//        if (auth()->check() && config('app.locale') != "") {
//            auth()->user()->lang = config('app.locale');
//            auth()->user()->save();
//        }

        return $next($request);
    }

}
