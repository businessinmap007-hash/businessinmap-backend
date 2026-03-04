<?php

namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Validator;
class LoginController extends Controller
{


    public function __construct()
    {


    }

    /**
     * @return string
     * @@ return login view
     * @@ access file login.blade.php from views.admin.login
     */


    public function login()
    {

        if (auth()->check() && auth()->user()->hasAnyRoles()) {
            return redirect(route('admin.home'));
            // return view('admin.auth.login');
        }
        return view('admin.auth.login');
    }


    public function postLogin(Request $request)
    {

        //return $request->all();
        
        $postData = [
            'provider' => $request->provider,
            'password' => $request->password,
        ];

        // Declare Validation Rules.
        $valRules = [
            'provider' => 'required',
            'password' => 'required',
        ];

        // Declare Validation Messages
        $valMessages = [
            'provider.required' => __('trans.email_required'),
            'password.required' => __('trans.password_required'),
        ];

        // Validate Input
        $valResult = Validator::make($postData, $valRules, $valMessages);

        // Check Validate
        if ($valResult->passes()) {

        if (Auth::attempt(["email" => $request->provider, 'password' => $request->password])) {
            return redirect()->route('admin.home');
        }

        session()->flash('error',   __('trans.email_or_password_error')  );
        return redirect()->back()->withInput();
        
        
    } else {
            // Grab Messages From Validator
            $valErrors = $valResult->messages();
            // Error, Redirect To User Edit
            return redirect()->back()->withInput()
                ->withErrors($valErrors);
        }
        


    }


    /**
     * Log the user out of the application.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function logout(Request $request)
    {
        Auth::guard()->logout();
        $request->session()->flush();
        $request->session()->regenerate();
        return redirect(url('/administrator'));

    }

}
