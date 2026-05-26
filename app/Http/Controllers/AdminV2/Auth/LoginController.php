<?php

namespace App\Http\Controllers\AdminV2\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function showLoginForm(Request $request): View|RedirectResponse
    {
        if (Auth::check() && $this->isAdminV2(Auth::user())) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin-v2.auth.login');
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

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            RateLimiter::hit($this->throttleKey($request), 60);

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'بيانات الدخول غير صحيحة.',
                ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        $request->session()->regenerate();

        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            Auth::logout();

            return redirect()
                ->route('admin.login')
                ->withErrors([
                    'email' => 'تعذر تحميل بيانات المستخدم.',
                ]);
        }

        if ($this->isSuspended($user)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('admin.login')
                ->withInput($request->only('email'))
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

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('admin.login')
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
        return Str::lower((string) $request->input('email')) . '|' . $request->ip();
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