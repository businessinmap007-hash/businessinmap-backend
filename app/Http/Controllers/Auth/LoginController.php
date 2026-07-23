<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * A ban must hold on this scaffold login too, not only on the custom
     * /user/auth/login (LoginController@login). Runs after the password checks
     * out: log the account back out and refuse.
     */
    protected function authenticated(Request $request, $user)
    {
        if (method_exists($user, 'isBanned') && $user->isBanned()) {
            $this->guard()->logout();
            $request->session()->invalidate();

            throw ValidationException::withMessages([
                $this->username() => [__('تم إيقاف هذا الحساب نهائيًا.')],
            ]);
        }
    }
}
