<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class AdminV2Middleware
{
    public function handle($request, Closure $next)
    {
        if (!Auth::check()) {
            return redirect()->route('admin.login');
        }

        $user = Auth::user();

        // Suspended
        if ((bool)($user->is_suspend ?? false)) {
            Auth::logout();
            return redirect()->route('admin.login')->withErrors('الحساب موقوف');
        }

        // Admin check (type=admin OR role owner)
        $isAdmin = (($user->type ?? null) === 'admin')
            || (method_exists($user, 'isAn') && $user->isAn('owner'));

        if (!$isAdmin) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
