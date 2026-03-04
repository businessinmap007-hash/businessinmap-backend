<?php

namespace App\Http\Controllers\AdminV2\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('admin-v2.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required','email'],
            'password' => ['required','string'],
        ]);

        $remember = (bool) $request->boolean('remember');

        if (!Auth::attempt($credentials, $remember)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'بيانات الدخول غير صحيحة']);
        }

        $request->session()->regenerate();

        $user = Auth::user();

        // 1) suspended؟
        if ((bool)($user->is_suspend ?? false)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->withErrors(['email' => 'الحساب موقوف']);
        }

        // 2) Admin V2؟ (Role owner أو type=admin لو موجود)
        if (!$this->isAdminV2($user)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(403, 'Unauthorized');
        }

        // ✅ Redirect dashboard
        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    private function isAdminV2($user): bool
    {
        // لو عندك عمود type = admin (زي اللي ظهر في tinker)
        if (($user->type ?? null) === 'admin') return true;

        // بouncer roles (زي الصورة: owner)
        if (method_exists($user, 'isAn')) {
            if ($user->isAn('owner')) return true;
        }

        return false;
    }
}
