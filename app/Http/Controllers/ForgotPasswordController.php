<?php

namespace App\Http\Controllers;

use App\Http\Helpers\Sms;
use App\Models\User;
use Illuminate\Http\Request;
use Validator;

class ForgotPasswordController extends Controller
{

    public function sendCode(Request $request)
    {






        $phone = $request->phone;
        $validator = Validator::make($request->all(), [
            'phone' => 'required',
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

        $user = User::wherePhone($phone)->first();


        if($user->is_suspend ==1 ){
            return response()->json([
                'status' => 400,
                'message' => __('trans.user_is_suspend'),
            ]);
        }

        if (!$user) {
            return response()->json([
                'status' => 400,
                'message' => __('global.account_not_found'),
            ]);
        }

        $reset_code = rand(1000, 9999);
        $user->action_code = $user->actionCode($reset_code);
        if($user->save()){
            session()->put([
                'phone' => $user->phone,
                'code' => $user->action_code
            ]);

        }




//
// $msg = urlencode("كود التفعيل في موقع أطلبها هو : " . $user->action_code);
//
//            $this->sendSMSWK("966540000217","sA159951",$phone,"ATLOBHA",$msg);
//        // Sms::sendActivationCode('Reset code:' . $user->action_code, $request->phone);
//


        Sms::sendMessage("DietDish Reset Code:".$user->action_code, $user->phone);

        return response()->json([
            'status' => 200,
            'message' => __('global.activation_code_sent'),
            'code' => $user->action_code,
            'phone' => $user->phone
        ]);


    }


    public function resendResetPasswordCode(Request $request)
    {
        return $this->getResetTokens($request);
    }
    
    
        public function sendSMSWK($userAccount, $passAccount, $numbers, $sender, $msg, $viewResult=1)
    {
        global $arraySendMsgWK;
        $url = "www.mobily.ws/api/msgSend.php";
        $applicationType = "68";
        $msg = $msg;
        $sender = urlencode($sender);
        $stringToPost = "mobile=".$userAccount."&password=".$passAccount."&numbers=".$numbers."&sender=".$sender."&msg=".$msg."&applicationType=".$applicationType."&lang=3";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $stringToPost);
        $result = curl_exec($ch);

        if($viewResult)

            $result = trim($result);
        // echo $result;
        return $result;

    }



}
