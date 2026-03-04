<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Conversation;
use App\Events\AddNewComment;
use App\Events\AddNewMessage;
use App\Libraries\PushNotification;
use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\DB;
use Notification;
use Psy\Test\Exception\RuntimeExceptionTest;

class ConversationsController extends Controller
{

    public $push;
    public $main;

    function __construct(PushNotification $push, \App\Libraries\Main $main)
    {

        $this->push = $push;
        $this->main = $main;
    }


//    public function postMessageConversation(Request $request)
//    {
//
//
//        //$user = User::whereApiToken($request->api_token)->first();
//
//
//        return $this->checkUsersAndOrderIsAvaliable($request);
//
//    }
//
//
//    private function checkUsersAndOrderIsAvaliable($request)
//    {
//
////        return $request->
//
//    }


    public function sendMessage(Request $request)
    {

        $token = ltrim($request->headers->get('Authorization'), "Bearer ");
        $user = User::whereApiToken($token)->first();


        if ($request->convId) {
            $havePreviousConversation = Conversation::whereId($request->convId)->first();
            $receiver = $havePreviousConversation->users()->where('id', '!=', $user->id)->first();
        }









        if (count((array)$havePreviousConversation) == 0) {
            $conversation = new Conversation;
            $conversation->order_id = $request->orderId;
            if ($conversation->save()) {
                $conversation->users()->attach([$user->id, $receiver->id]);

                if (isset($request->message)) {
                    $message = new Message;
                    $message->message = $request->message;

                    $message->conversation_id = $conversation->id;
                    $message->user_id = $user->id;

                    $message->load('user');
                    if ($message->save()) {

//                        $data = array(
//                            "user_id" => $receiver->id,
//                            'title' => "رسالة جديدة",
//                            'body' => "رسالة جديدة $message->message",
//                            'order_id' => $order->id,
//                            'type' => 7,
//                            'sender_id' => $user->id,
//                            'created_at' => Carbon::now(),
//                            'updated_at' => Carbon::now()
//                        );
//
//
//                        //$this->main->insertData("App\Models\Notification", $data);
//
//                        $data['href'] = "";
//                        $data['order'] = $order;
//
//                        $data['convId'] = $conversation->id;

                        // $userDevicesAndroid =  $receiver->devices()->where('device_type', 'android')->pluck('device');
                        // $userDevicesIos =  $receiver->devices()->where('device_type', 'ios')->pluck('device');
                        // $this->push->sendPushNotification($userDevicesAndroid, $userDevicesIos, $data['title'], $data['body'], $data);

                    }

                    $message->dateDiff = 0;

                    return response()->json([
                        'status' => 200,
                        'data' => $message
                    ]);

                }

            } else {
                return response()->json([
                    'status' => 400
                ]);
            }
        } else {


            $to = $havePreviousConversation->users()->where('id', '!=', $user->id)->first();
            //$isHaveConversationWithAdv->adv_id = $request->advId;
            //$isHaveConversationWithAdv->users()->attach($user->id);
            $message = new Message;
            $message->message = $request->message;

            $message->conversation_id = $havePreviousConversation->id;
            $message->user_id = $user->id;
            $message->load('user');

            if ($message->save()) {
                $message->dateDiff = 0;

                $data = array(
                    "user_id" => $message->user_id,
                    'title' => "رسالة جديدة",
                    'body' => "$message->message",
                    'type' => 7,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                );

//
//                $this->main->insertData("App\Models\Notification", $data);
//
//                $data['href'] = "";
                $data['convId'] = $havePreviousConversation->id;
                $data['messageId'] = $message->id;
//                $data['order'] = $order;
//
//
                $userDevicesAndroid = $to->devices()->where('device_type', 'android')->pluck('device');
                $userDevicesIos = $to->devices()->where('device_type', 'ios')->pluck('device');
//

                $this->push->sendPushNotification($userDevicesAndroid, [], "Message Notification Title", $message->message, $data);


            }

//            DB::table('conversation_user')
//                ->where(['user_id' => $to->id, 'conversation_id' => $request->convId])
//                ->update(['read_at' => null]);
//
//
//            DB::table('conversation_user')
//                ->where(['user_id' => $user->id, 'conversation_id' => $request->convId])
//                ->update(['read_at' => date('Y-m-d H:i:s')]);
//
//
//            if ($request->convId) {
//                $conversation = Conversation::where('id', $request->convId)->first();
//                $conversation->updated_at = date('Y-m-d H:i:s');
//                $conversation->update();
//            }

            return response()->json([
                'status' => 200,
                'data' => $message,
                //'data' => $havePreviousConversation
            ]);

        }

    }


    public function getListOfConversations(Request $request, $pageSize = 15)
    {

        $token = ltrim($request->headers->get('Authorization'), "Bearer ");

        $user = User::whereApiToken($token)->first();

        $arr = [];
        foreach ($user->conversations as $row) {
            $arr[] = $row->id;
        }


        $pageSize = $request->pageSize;


        $skipCount = $request->skipCount;
        $page = $request->page;

        $currentPage = $request->get('page', $page); // Default to 1


        $query = Conversation::orderBy('updated_at', 'DESC')->select();
        $query->whereIn('id', $arr);
//        $query->with('orders');


        $convs = $query->get();


        $convs->map(function ($q) use ($user) {


//            if ($q->order_id != "")
//                $q->orderStatus = (int)Order::whereId($q->order_id)->first()->status;


            $q->read_at = DB::table('conversation_user')->where(['user_id' => $user->id, 'conversation_id' => $q->id])->first()->read_at;
//            $q->deleted_at = DB::table('conversation_user')->where(['user_id' => $user->id, 'conversation_id' => $q->id])->first()->deleted_at;
            $q->deleted_at = DB::table('conversation_user')->where(['user_id' => $user->id, 'conversation_id' => $q->id])->first() ? DB::table('conversation_user')->where(['user_id' => $user->id, 'conversation_id' => $q->id])->first()->deleted_at : null;

            $q->lastMessage = is_object($q->messages()->orderBy('created_at', 'desc')->first()) ? $q->messages()->orderBy('created_at', 'desc')->first()->created_at->toDateTimeString() : null;
            $q->lastmsg = is_object($q->messages()->orderBy('created_at', 'desc')->where('conversation_id', $q->id)->first()) ? $q->messages()->orderBy('created_at', 'desc')->where('conversation_id', $q->id)->first()->message : null;
            $q->user = $q->users()->where('id', '!=', $user->id)->first();
        });


        $data = $convs->filter(function ($q) {
            return date('Y-m-d H:i:s', strtotime($q->lastMessage)) > date('Y-m-d H:i:s', strtotime($q->deleted_at));
        })->slice($skipCount + (($currentPage - 1) * $pageSize))
            ->take($pageSize)
            ->values();

        return response()->json([
            'status' => 200,
            'data' => $data
        ]);


    }


    public function getAllMessages(Request $request)
    {


        $token = ltrim($request->headers->get('Authorization'), "Bearer ");

        $authUserId = User::whereApiToken($token)->first()->id;


        $pageSize = $request->pageSize;
        $skipCount = $request->skipCount;
        $itemId = $request->itemId;
        $page = $request->page;

        $currentPage = $request->get('page', 1); // Default to 1
        // API authenticated user is considered the sender in this case.
//        $authUserId = auth()->user()->id;


        DB::table('conversation_user')
            ->where(['user_id' => $authUserId, 'conversation_id' => $request->convId])
            ->update(['is_online' => 1]);


        $conversation = Conversation::with(['users', 'messages'])->find($request->convId);


        $sender = collect($conversation->users)->reject(function ($user) use ($authUserId) {
            return $user->id != $authUserId;
        })->first();


        $senderConversationDeletedAt = $sender->pivot->deleted_at ?: null;

        $messages = collect($conversation->messages)->reject(function ($message) use ($senderConversationDeletedAt) {
            return $message->created_at < $senderConversationDeletedAt;
        })->slice($skipCount + (($currentPage - 1) * $pageSize))
            ->take($pageSize)
            ->values();

        $messages->map(function ($q) {
            $q->id = (string)$q->id;
            $q->text = $q->message;
            $q->conversation_id = (string)$q->conversation_id;
            $q->user = $this->getUserInfo($q->user_id);
            $q->user_id = (int)$q->user_id;
            $q->dateDiff = strtotime(Carbon::now()) - strtotime($q->created_at);
            return $q;

        });

        if ($messages->count() > 0) {
            return response()->json([
                'status' => 200,
                'data' => $messages->reverse()->values()
            ]);

        } else {
            return response()->json([
                'status' => 200,
                'data' => []
            ]);
        }
    }


    public function getUserInfo($id)
    {


        $user = User::whereId($id)->first();

        if ($user) {

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'api_token' => $user->api_token,
                'image' => $user->image
            ];

        } else {

            return [
                'username' => 'غير موجود',
                'image' => 'notfound'
            ];

        }


    }


    public function markConversationAsRead(Request $request)
    {
        $user = User::whereApiToken($request->api_token)->first();
        DB::table('conversation_user')
            ->where(['user_id' => $user->id, 'conversation_id' => $request->convId])
            ->update(['read_at' => date('Y-m-d H:i:s')]);


        foreach ($user->unreadNotifications()->where('n_type', 1)->get() as $notification) {
            $notification->markAsRead();
        }

        return response()->json([
            'status' => 200
        ]);

    }


    public function pushNotification($message, $user, $type, $id)
    {

        $content = array(
            "en" => $message
        );


        $devices = [$user->device];


//        foreach ($users as $user) {
//
//            if ($user->id == $current) {
//                continue;
//            }
//
//            if ($user->device && $user->device != NULL)
//                $devices[] = $user->device;
//
//        }


        $data = [
            'status' => 'public',
            'type' => $type,
            'created_at' => date('Y-m-d H:i:s'),
            'convId' => $id
        ];

        $fields = array(
            'app_id' => "960a09cd-5d50-45f8-b96c-87cc85e58506",
            'include_player_ids' => $devices,
            'contents' => $content,
            'data' => $data,
            'android_group' => 'harag',
            'ios_badgeType' => 'Increase',
            'ios_badgeCount' => 1,

        );


        $fields = json_encode($fields);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8',
            'Authorization: Basic ZDQ2MzEzZDctMzI3Ny00NTNjLWJmMDQtMTMxYjg1OGIzZWNj'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        curl_close($ch);
        $response;


    }


    public function deleteConversation(Request $request)
    {
        // get authenticated user
        $user = User::whereApiToken($request->api_token)->first();

        // get conversation want to be delete.
        $coversation = Conversation::whereId($request->convId)->first();

        // get conversation for auth user
        $sender = collect($coversation->users)->reject(function ($user1) use ($user) {
            return $user1->id != $user->id;
        })->first();

        // set delete conversation at NOW
        $sender->pivot->deleted_at = date('Y-m-d H:i:s');

        /**
         * @return response json after delete message.
         */

        if ($sender->pivot->save()) {
            return response()->json([
                'status' => 200,
                'message' => 'لقد تم حذف المحادثة بنجاح'
            ]);
        }
    }


    public function makeUserConversationOffline(Request $request)
    {
        // get authenticated user
        $user = User::whereApiToken($request->api_token)->first();

        $offline = DB::table('conversation_user')
            ->where(['user_id' => $user->id, 'conversation_id' => $request->convId])
            ->update(['is_online' => 0]);

        return response()->json([
            'status' => 200,
            'message' => 'user offline'
        ]);
    }


    public function deleteUserDevices(Request $request)
    {
        $user = User::whereApiToken($request->api_token)->first();
        if ($user) {
            $device = \App\Device::where(['device' => $request->playerId, 'user_id' => $user->id])->first();
            if ($device && $device->delete()) {

                return response()->json([
                    'status' => 200,
                    'data' => null,

                ]);
            } else {

                return response()->json([
                    'status' => 200,
                    'data' => null,

                ]);
            }
        }
    }


    public function checkUserHasConversation(Request $request)
    {

        $token = ltrim($request->headers->get('Authorization'), "Bearer ");

        $authUser = User::whereApiToken($token)->first();

        $conversation = $authUser->conversations()->whereOrderId($request->orderId)->first();

        if (!$conversation) {
            return response()->json([
                'status' => 400,
                'message' => 'undefinded',

            ]);
        }


        $conversation->read_at = DB::table('conversation_user')->where(['user_id' => $authUser->id, 'conversation_id' => $conversation->id])->first()->read_at;
        $conversation->deleted_at = DB::table('conversation_user')->where(['user_id' => $authUser->id, 'conversation_id' => $conversation->id])->first()->deleted_at;

        //  $conversation->deleted_at = DB::table('conversation_user')->where(['user_id' => $user->id, 'conversation_id' => $q->id])->first() ? DB::table('conversation_user')->where(['user_id' => $user->id, 'conversation_id' => $q->id])->first()->deleted_at : null;

        $conversation->lastMessage = is_object($conversation->messages()->orderBy('created_at', 'desc')->first()) ? $conversation->messages()->orderBy('created_at', 'desc')->first()->created_at->toDateTimeString() : null;
        $conversation->lastmsg = is_object($conversation->messages()->orderBy('created_at', 'desc')->where('conversation_id', $conversation->id)->first()) ? $conversation->messages()->orderBy('created_at', 'desc')->where('conversation_id', $conversation->id)->first()->message : null;


        $userInfo = $authUser->generalUserInfo();

        if ($authUser->userType() == 'company' && $authUser->is_completed == 0) {
            $conversation->user = $authUser->companyUserToArray();
        } elseif ($authUser->userType() == 'driver') {
            $conversation->user = $authUser->driverUserToArray();
        }

        // $cityObj = $authUser->city;
        // if (count((array)$cityObj) != 0)
        //     $userInfo['city'] = $cityObj;


        $conversation->user = $userInfo;


        return response()->json([
            'status' => 200,
            'data' => $conversation

        ]);


        //Conversation::whereOrderId($request->orderId)->first();

    }

}
