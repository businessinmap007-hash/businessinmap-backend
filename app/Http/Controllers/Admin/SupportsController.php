<?php

namespace App\Http\Controllers\Admin;

use App\Device;
use App\Libraries\Main;
use App\Libraries\PushNotification;
use App\Message;
use App\Notifications;
use App\Models\Support;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Carbon\Carbon;
use App\Models\Notification;

class SupportsController extends Controller
{


    public $push;
    public $main;

    public function __construct(PushNotification $push, Main $main)
    {

        $this->push = $push;
        $this->main = $main;

    }


    public function index()
    {
        $supports = Support::whereParentId(0)->get();
        return view('admin.supports.index')->with(compact('supports'));
    }

    public function show($id)
    {

        $message = Support::with('user')->whereId($id)->first();
        $message->is_read = 1;
        $message->save();

        return view('admin.supports.show')->with(compact('message'));
    }

    public function reply(Request $request, $id)
    {


        $message = Support::findOrFail($id);
        
        $sender = User::whereId($message->user_id)->first();






        if ($request->message == '') {
            return response()->json([
                'status' => false,
                'message' => 'من فضلك ادخل بيانات الرسالة ثم اعد الإرسال'
            ]);
        }


        if ($request->message == '') {
            return response()->json([
                'status' => false,
                'message' => 'من فضلك ادخل نص الرد '
            ]);
        }


        $support = new Support;
        $support->message = $request->message;

        $support->messageType_id = 1; // defined as  a reply ....

        if ($request->email)
            $support->email = $request->email;
        $support->user_id = auth()->id();


        $support->parent_id = $id;

        if ($support->save()) {



            $data = array(
                "user_id" => $sender->id,
                'title' => $sender->lang == "ar" ? "دايت ديش" : "Diet Dish",
                'body' => $sender->lang == "ar" ? "لقد تم الرد علي رسالكتم من قبل إدارة دايت ديش ($message->message)" : "You have a reply for your message from dietdish magagers ($message->message)",
                'order_id' => null,
                'type' => 6,
                'sender_id' => auth()->id(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            );


            $this->main->insertData(Notification::class, $data);
//                    $senderDevicesAndroid =  $user->devices()->where('device_type', 'android')->pluck('device');
//                    $senderDevicesIos =  $user->devices()->where('device_type', 'ios')->pluck('device');
//                    $this->push->sendPushNotification($senderDevicesAndroid, $senderDevicesIos, $data['title'], $data['body'], $data);




            $webDevice = collect($sender->devices)->where('device_type', 'web')->pluck('device');



            $data['href'] = url('/').'/'.$sender->lang.'/notifications';

            $this->push->sendPushNotification([], $webDevice, $data['title'], $data['body'], $data);


            $support->created = $support->created_at->format(' Y/m/d  ||  H:i:s ');
            return response()->json([
                'status' => true,
                'message' => __('web.reply_sent_successfully'),
                'data' => $support

            ], 200);
        } else {
            return response()->json([
                'status' => false,
            ]);
        }
    }


    /**
     * Remove User from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function delete(Request $request)
    {


        if (!Gate::allows('users_manage')) {
            return abort(401);
        }


        $model = Support::findOrFail($request->id);

        if ($model->children->count() > 0)
            $model->children()->delete();


        if ($model->delete()) {
            return response()->json([
                'status' => true,
                'data' => [
                    'id' => $request->id
                ]
            ]);
        }


    }

}
