<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the scoped business-owner panel.
 *
 * Only an authenticated, non-suspended user of type=business may enter. The
 * panel itself always scopes data to the logged-in owner's own id
 * (business_id === auth id), so owners never see another business's data.
 */
class BusinessPanelMiddleware
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('business.login');
        }

        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->isBusiness()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('business.login')
                ->withErrors(['email' => 'هذه اللوحة مخصصة لحسابات الأنشطة التجارية فقط.']);
        }

        if ((bool) ($user->is_suspend ?? false)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('business.login')
                ->withErrors(['email' => 'الحساب موقوف حاليًا.']);
        }

        return $next($request);
    }
}
