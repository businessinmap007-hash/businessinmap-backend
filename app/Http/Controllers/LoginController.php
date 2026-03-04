<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Validator;
use App\Models\User;
use Auth;

class LoginController extends Controller
{

    public function showLogin()
    {
        if (auth()->check())
            return redirect()->back();


        $countries = Location::country()->get();
        session()->put('url.intended', url()->previous());
        return view('auth.login', compact('countries'));
    }

    public function login(Request $request)
    {


        // Get Input
        $postData = [
            'email' => $request->email,
            'password' => $request->password,
        ];

        // Declare Validation Rules.
        $valRules = [
            'email' => 'required',
            'password' => 'required',
        ];

        // Declare Validation Messages
        $valMessages = [
            'phone.required' => trans('global.field_required'),
            'password.required' => trans('global.field_required'),
        ];

        // Validate Input
        $valResult = Validator::make($postData, $valRules, $valMessages);

        if ($valResult->passes()) {

            if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {

                if (!auth()->user()->api_token) {
                    auth()->user()->api_token = str_random(60);
                    auth()->user()->save();
                }

                if (!$request->has('from-page') ){
                    $redirectBack = session()->get('url.intended');
                }else{
                    $redirectBack = route('user.home');
                }


                return returnedResponse(200, __('trans.logged_in_success'), null, $redirectBack);


            } else {
                return response()->json([
                    'status' => 400,
                    'message' => __('trans.username_or_password_incorrect'),
                ]);
            }
        } else {
            // Grab Messages From Validator
            return response()->json([
                'status' => 402,
                'errors' => $valResult->messages()->all(),

            ]);
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
        session()->flash('success', __('trans.logout_success'));
        return redirect()->guest(route('user.home'));

    }


}
