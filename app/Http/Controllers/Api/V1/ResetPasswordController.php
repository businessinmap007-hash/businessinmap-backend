<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
//use App\Transformers\Json;
use App\Models\User;

use Validator;
use Illuminate\Support\Facades\App;

/**
 * ⚠️ UNROUTED AND UNSAFE — DO NOT REGISTER THIS AGAIN (BIM-14.0, 2026-07-16).
 *
 * reset() below takes an email and a password and sets that password WITHOUT
 * verifying any code — knowing an email address was enough to seize the account,
 * including admin accounts. It was publicly routed until 2026-07-16.
 *
 * The routes were removed from routes/api.php; the file is kept only because v1
 * is a porting reference. Use Api\V2\PasswordResetController
 * (/api/v2/auth/password/*) — hashed code, expiry, attempt lock, no enumeration.
 *
 * V1PasswordRoutesRemovedTest fails if these routes ever come back.
 */
class ResetPasswordController extends Controller
{
    public function __construct(Request $request)
    {

        $language = $request->headers->get('lang') ? $request->headers->get('lang') : 'ar';
        App::setLocale($language);


    }


    public function reset(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'email' => 'required',
            'password' => 'required',
            'password_confirmation' => 'required|same:password',
        ]);

        if ($validator->fails()) {
            return response()->json(
                [
                    'status' => 400,
                    'errors' => $validator->errors()->all(),
                    'message' => trans('global.some_errors_happen'),
                ]
            );
        }


        $user = User::where('email', $request->email)->first();


        if (!$user) {
            return response()->json([
                'status' => 400,
                'message' => __('global.user_not_found')
            ]);
        }


        if ($user) {
            $user->password = bcrypt($request->password);
            $user->save();
            return response()->json([
                'status' => 200,
                'data' => UserResource::make($user),
                'message' => __('global.passsord_reset_successfully')
            ]);
        } else {
            return response()->json([
                'status' => 400,
                'message' => __('global.reset_code_invalid')
            ]);
        }


    }


    public function check(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'code' => 'required',
            'email' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(
                [
                    'status' => 402,
                    'errors' => $validator->errors()->all(),
                    'message' => trans('global.some_errors_happen'),
                ]
            );
        }

        // Validate Input

        $user = User::where(['action_code' => $request->code, 'email' => $request->email])->first();

        if ($user) {
            return response()->json([
                'status' => 200,
                'message' => 'success'
            ]);
        } else {
            return response()->json([
                'status' => 400,
                'message' => 'error',
            ]);
        }
    }


    public function checkCode(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'activation_code' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => 'كود التفعيل مطلوب'
            ]);
        }

        $user = User::where(['action_code' => $request->activation_code])->first();

        if ($user) {

            $user->phone = $request->phone;
            $user->save();
            return response()->json([
                'status' => true,
                'message' => 'code and phone correct',
                'data' => $user
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'code or phone incorrect'
            ]);
        }
    }

}
