<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
//use App\Transformers\Json;
use App\Models\User;
use Illuminate\Http\Request;
use Sms;
use Illuminate\Support\Facades\App;

use Validator;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */


    /**
     * Send a reset link to the given user.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function __construct(Request $request)
    {

        $language = $request->headers->get('lang')  ? $request->headers->get('lang') : 'ar' ;
        App::setLocale($language);



    }


    public function getResetTokens(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'email' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(
                [
                    'status' => 400 ,
                    'errors' => $validator->errors()->all(),
                    'message' => trans('global.some_errors_happen') ,
                ]
            );
        }

            $user = User::whereEmail($request->email)->first();
            if (!$user) {
                return response()->json([
                    'status' => 400,
                    'message' => "لا يوجد حساب مسجل بهذا البريد",
                ]);
            }

            $reset_code = rand(1000, 9999);
            $user->action_code = $user->actionCode($reset_code);
           if( $user->save()){

               \Mail::send("emails.email", ['subject' => __('trans.site_name'), 'content' =>['code' => $user->action_code] ], function ($m) use($user) {
                   $m->to($user->email);
                   $m->subject("إستعادة كلمة المرور - BIM");
                   $m->from('info@businessinmap.com');
                   $m->replyTo("info@businessinmap.com");
               });

               return response()->json([
                   'status' => 200,
                   'message' => "لقد تم إرسال كود لإستعادة كلمة المرور الخاصة بك، في البريد المدخل من قبلكم",
                   'code' => $user->action_code
               ]);
           }


            // Sms::sendActivationCode('Reset code:' . $user->action_code, $request->phone);


    }

//    public function sendSMSWK($userAccount, $passAccount, $numbers, $sender, $msg, $viewResult=1)
//    {
//        global $arraySendMsgWK;
//        $url = "www.mobily.ws/api/msgSend.php";
//        $applicationType = "68";
//        $msg = $msg;
//        $sender = urlencode($sender);
//        $stringToPost = "mobile=".$userAccount."&password=".$passAccount."&numbers=".$numbers."&sender=".$sender."&msg=".$msg."&applicationType=".$applicationType."&lang=3";
//        $ch = curl_init();
//        curl_setopt($ch, CURLOPT_URL, $url);
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//        curl_setopt($ch, CURLOPT_HEADER, 0);
//        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
//        curl_setopt($ch, CURLOPT_POST, 1);
//        curl_setopt($ch, CURLOPT_POSTFIELDS, $stringToPost);
//        $result = curl_exec($ch);
//
//        if($viewResult)
//
//            $result = trim($result);
//        // echo $result;
//        return $result;
//    }


    public function resendResetPasswordCode(Request $request)
    {
        return $this->getResetTokens($request);
    }


}
