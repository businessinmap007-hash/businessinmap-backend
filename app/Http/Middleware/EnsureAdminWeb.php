<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdminWeb
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check()) {
            return redirect()->route('admin.login');
        }

        $user = auth()->user();

        // حسب نظامك: type=admin أو role=owner/admin
        if ((isset($user->type) && $user->type === 'admin') ||
            (method_exists($user, 'roles') && $user->roles()->whereIn('name', ['owner','admin'])->exists())
        ) {
            return $next($request);
        }

        abort(403);
    }
}
