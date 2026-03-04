<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Auth;


class AdminAuthenticationMiddleware
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
        $setting = new Setting;
        if (auth()->check() && auth()->user()->hasAnyRoles()) {

            if (auth()->user()->is_suspend == 1) {

                $reason = auth()->user()->suspend_reason;

                Auth::logout();

                session()->flash('errorSuspend', __('trans.suspendBecause', ['phone' => $setting->getBody('app_contact_phone'), "reason" => $reason]));

                return redirect(route('admin.login'));

            }
            return $next($request);
        } else {

            return redirect(route('admin.login'));
        }
    }
}
