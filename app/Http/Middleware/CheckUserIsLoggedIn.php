<?php

namespace App\Http\Middleware;

use Closure;

class CheckUserIsLoggedIn
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
        if (auth()->check()) {


//            auth()->user()->lang = config('app.locale');
//            auth()->user()->save();

            return $next($request);

        } else {

            return redirect(route('user.home') . '?try-access=yes');

        }
    }
}
