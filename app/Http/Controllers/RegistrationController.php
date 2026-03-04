<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\StoreUsersRequest;
use App\Libraries\PushNotification;
use App\Models\Device;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Validator;
use App\Http\Helpers\Sms;

class RegistrationController extends Controller
{


    public $public_path;
    public $main;
    public $push;

    public function __construct(Request $request, \App\Libraries\Main $main, PushNotification $push)
    {
        $language = $request->headers->get('lang') ? $request->headers->get('lang') : 'ar';
        app()->setLocale($language);

        $this->main = $main;
        $this->push = $push;

    }


    public function showRegister()
    {
        return view('auth.register');
    }


    public function signup(StoreUsersRequest $request)
    {


        $inputs = $request->all();
        $inputs['type'] = $request->has('auth') && $request->get('auth') == 'vendor' ? 'vendor' : "client";
        $user = User::create(array_merge($inputs, array('api_token' => str_random(120))));
        if ($user) {
            if($request->has('auth') && $request->get('auth') == 'vendor' )
                $user->assign(2);
            auth()->loginUsingId($user->id);
            session()->flash('success', 'لقد تم تسجيل المستخدم بنجاح');
            return returnedResponse(200, "لقد تم تسجيل المستخدم بنجاح", null, route('profile'));
        }


        // Get Input
        $postData = [
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
        ];

        // Declare Validation Rules.
        $valRules = [
            'name' => 'required',
            'password' => 'required',
            'email' => 'required|email|unique:users,email',
        ];

        // Declare Validation Messages
        $valMessages = [
            'phone.required' => trans('global.field_required'),
            'password.required' => trans('global.field_required'),
            'email.required' => trans('global.field_required'),
        ];

        // Validate Input
        $valResult = Validator::make($postData, $valRules, $valMessages);

        if ($valResult->passes()) {
            $user = new User;
            $user->name = $request->name;

            $user->email = $request->email;
            $user->phone = $request->phone;
            $user->api_token = str_random(60);
            $user->password = $request->password;
            $user->is_user = 4;

            $user->is_active = 1;
            $user->is_suspend = 0;
            $actionCode = rand(1000, 9999);
            $actionCode = $user->actionCode($actionCode);
            $user->action_code = $actionCode;


            if ($user->save()) {


//                $this->notificationsSender($user->id);

                $inputs = $request->all();

                sendEmail($inputs);

                session()->put('phoneForResend', $user->phone);
                return response()->json([
                    'status' => 200,
                    'message' => __('trans.account_created_success'),
                    'is_active' => $user->is_active,
                    'phone' => $user->phone
                ]);
            }
        } else {
            return response()->json([
                'status' => 402,
                'errors' => $valResult->messages()->all(),

            ]);
        }
    }


    private function notificationsSender($userId)
    {
        $data = [];
        $staticData = [
            'title' => "تسجيل حساب",
            'body' => "تم تسجيل فرد جديد",
            'item_id' => $userId,
            'type' => "signup",
            "url" => generateUrl($userId),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
        foreach (getNotificationManagers() as $userData) {
            $collection = collect($staticData);
            $collection->put('user_id', $userData);
            $data[] = $collection->all();
        }


        if (count($data) > 0) {
            $this->main->insertData(Notification::class, $data);
            $this->push->sendPushNotification([], getAdminDevices(), $staticData['title'], $staticData['body'], $staticData);
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

    public function checkIsColExist(Request $request)
    {
        $user = User::where($request->col, $request->email)->first();
        if ($user) {
            if ($request->col == 'email') {
                $message = "عفواً, هذا البريد مستخدم من قبل مستخدم آخر.";
            } elseif ($request->col == 'phone') {
                $message = "عفواً, هذا الجوال مستخدم من قبل مستخدم آخر.";
            } else if ($request->col == 'username') {
                $message = "عفواً, هذا الاسم مستخدم من قبل مستخدم آخر.";

            }
            return response()->json([
                'status' => true,
                'message' => $message,
                'type' => $request->col
            ]);
        }
    }
}



