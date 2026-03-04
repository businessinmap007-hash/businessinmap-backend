<?php

namespace App\Http\Middleware;

use Closure;

class changeLanguageForApi
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $lang = $request->headers->get('lang');


        \Log::info($lang);
        \Log::info(auth()->user());


        return $next($request);
    }
}
