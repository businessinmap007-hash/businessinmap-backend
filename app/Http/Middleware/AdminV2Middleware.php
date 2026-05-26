<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminV2Middleware
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('admin.login');
        }

        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('admin.login')
                ->withErrors([
                    'email' => 'يرجى تسجيل الدخول مرة أخرى.',
                ]);
        }

        if ($this->isSuspended($user)) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('admin.login')
                ->withErrors([
                    'email' => 'الحساب موقوف.',
                ]);
        }

        if (! $this->isAdminV2($user)) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('admin.login')
                ->withErrors([
                    'email' => 'هذا الحساب لا يملك صلاحية دخول لوحة الإدارة.',
                ]);
        }

        return $next($request);
    }

    private function isSuspended(User $user): bool
    {
        return (bool) ($user->is_suspend ?? false);
    }

    private function isAdminV2(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (($user->type ?? null) === User::TYPE_ADMIN || ($user->type ?? null) === 'admin') {
            return true;
        }

        if (method_exists($user, 'isAdmin')) {
            try {
                if ($user->isAdmin()) {
                    return true;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if (method_exists($user, 'isAn')) {
            try {
                if ($user->isAn('owner') || $user->isAn('admin')) {
                    return true;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if (method_exists($user, 'roles')) {
            try {
                return $user->roles()
                    ->whereIn('name', ['owner', 'admin'])
                    ->exists();
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return false;
    }
}