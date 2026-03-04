<?php

namespace App\Http\Middleware;

use App\Agency;
use Closure;

class InstitutionAuthenticationMiddleware
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

        if (auth()->check() && auth()->user()->hasVendorRole())

            return $next($request);
        else
            return redirect(route('login'));
    }
}
