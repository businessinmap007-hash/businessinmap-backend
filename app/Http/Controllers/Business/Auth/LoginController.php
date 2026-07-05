<?php

namespace App\Http\Controllers\Business\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Session login for the scoped business-owner panel. Only users of
 * type=business may enter; everyone else is rejected.
 */
class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::check() && Auth::user()?->isBusiness()) {
            return redirect()->route('business.dashboard');
        }

        return view('business.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $this->ensureIsNotRateLimited($request);

        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [], [
            'email' => 'البريد الإلكتروني',
            'password' => 'كلمة المرور',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($request), 60);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'بيانات الدخول غير صحيحة.']);
        }

        RateLimiter::clear($this->throttleKey($request));
        $request->session()->regenerate();

        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->isBusiness()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('business.login')
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'هذه اللوحة مخصصة لحسابات الأنشطة التجارية فقط.']);
        }

        if ((bool) ($user->is_suspend ?? false)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('business.login')
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'الحساب موقوف.']);
        }

        return redirect()->intended(route('business.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('business.login')
            ->with('success', 'تم تسجيل الخروج بنجاح.');
    }

    private function ensureIsNotRateLimited(Request $request): void
    {
        $key = $this->throttleKey($request);

        if (! RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        abort(Response::HTTP_TOO_MANY_REQUESTS, 'محاولات كثيرة. حاول مرة أخرى بعد ' . $seconds . ' ثانية.');
    }

    private function throttleKey(Request $request): string
    {
        return 'business|' . Str::lower((string) $request->input('email')) . '|' . $request->ip();
    }
}
