<?php

namespace App\Http\Controllers;

use App\Http\Helpers\Sms;
use App\Models\User;
use Illuminate\Http\Request;
use Validator;


class ResetPasswordController extends Controller
{


    public function reset(Request $request)
    {

        if (isset($request->password)) {


            $user = User::where('phone', session()->get('phone'))->where('action_code', session()->get('code'))->first();
            if ($user) {
                $user->password = bcrypt($request->password);
                $user->save();
                return response()->json([
                    'status' => 200,
                    'data' => $user,
                    'message' => __('global.passsord_reset_successfully')
                ]);
            } else {
                return response()->json([
                    'status' => 400,
                    'message' => __('global.reset_code_invalid')
                ]);
            }

        }
    }


    public function checkCode(Request $request)
    {

        $code = $request->activation_code;
        $user = User::where(['action_code' => $code])->first();

        if ($user) {

            return response()->json([
                'status' => 200,
                'message' => 'لقد تم التحقق من كود التفعيل',
            ]);
        } else {
            return response()->json([
                'status' => 400,
                'message' => 'الكود المرسل غير صحيح'
            ]);
        }
    }


    public function resendActivationCode(Request $request)
    {


        $user = User::wherePhone($request->phone)->first();



        $actionCode = rand(1000, 9999);
        $actionCode = $user->actionCode($actionCode);
        $user->action_code = $actionCode;






        if ($user->save()) {


            Sms::sendMessage("DietDish Activation Code:".$user->action_code, $user->phone);

            //$phone = "966" . ltrim($user->phone, 0);
            //$msg = urlencode("كود التفعيل في موقع أطلبها هو : " .  $user->action_code);

            //$this->sendSMSWK("966540000217", "sA159951", $phone, "ATLOBHA", $msg);

            return response()->json([
                'status' => 200,
                'message' => __('trans.activationCodeSent'),

                'code' => $user->action_code
            ]);
        }
    }

    public function sendSMSWK($userAccount, $passAccount, $numbers, $sender, $msg, $viewResult = 1)
    {
        global $arraySendMsgWK;
        $url = "www.mobily.ws/api/msgSend.php";
        $applicationType = "68";
        $msg = $msg;
        $sender = urlencode($sender);
        $stringToPost = "mobile=" . $userAccount . "&password=" . $passAccount . "&numbers=" . $numbers . "&sender=" . $sender . "&msg=" . $msg . "&applicationType=" . $applicationType . "&lang=3";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $stringToPost);
        $result = curl_exec($ch);

        if ($viewResult)

            $result = trim($result);
        // echo $result;
        return $result;

    }





}
