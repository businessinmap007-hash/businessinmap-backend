<?php

namespace App\Http\Controllers\Api\V1;

use Validator;

use App\Models\Support;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use Carbon\Carbon;

class SupportsController extends Controller
{


    public $push;
    public $main;


    public function __construct(Request $request, \App\Libraries\PushNotification $push, \App\Libraries\Main $main)
    {
        $language = $request->headers->get('lang') ? $request->headers->get('lang') : 'ar';
        app()->setLocale($language);
        $this->push = $push;
        $this->main = $main;
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @@ POST MESSAGE...
     */
    public function sendMessage(Request $request)
    {
        $api_token = str_replace('Bearer ', '', request()->headers->get('Authorization'));
        $user = User::whereApiToken($api_token)->first();

        $validator = Validator::make($request->all(), [
            'message' => 'required',
//            'type' => 'required',
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

        $support = new Support();

        if (isset($request->type))
            $support->type = $request->type;

        $support->message = $request->message;
        $support->user_id = $user->id;
        if ($support->save()) {

            $users = User::whereHas('roles')->whereIsUser(0)->get();


            $notificationsData = [];
            foreach ($users as $user) {
                $dataString = $this->main->notificationTranslation(11, $user->lang, $user->company_name);

                $data = array(
                    'title' => $dataString['title'],
                    'body' => $dataString['body'],
                    'type' => 11,
                    'href' => url('/' . $user->lang . '/administrator/contactus/' . $support->id),
                    "image" => "http://bdfjade.com/data/out/111/6150983-amazing-pic.jpg"
                );

                $notificationsData[] = array(
                    'user_id' => $user->id,
                    'title' => $data['title'],
                    'body' => $data['body'],
                    'order_id' => null,
                    'type' => 11,
                    'sender_id' => $user->id,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                );

                $devices = [];
                foreach ($user->devices as $device) {
                    $devices[] = $device;
                }

                $webDevices = collect($devices)->where('device_type', 'web')->pluck('device');
                 $this->push->sendPushNotification([], $webDevices, $data['title'], $data['body'], $data);

            }

            Notification::insert($notificationsData);


            return response()->json(
                [
                    'status' => 200,
                    'message' => trans('global.message_was_sent_successfully'),
                ]
            );
        }


    }
}
