<?php

namespace App\Http\Controllers\Api\V1;

use App\Conversation;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Track;
use App\Models\Size;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use DB;
use App\Models\Notification;
use App\Like;
use App\Company;
use App\Visit;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Validator;
use App\Http\Helpers\Images;
use Hash;

class CompaniesController extends Controller
{

    public $public_path;
    public $apiToken;
    public $main;
    public $push;

    public function __construct( \App\Libraries\Main $main, \App\Libraries\PushNotification $push)
    {
        $this->public_path = 'files/users/drivers/';
        $language = request()->headers->get('lang') ? request()->headers->get('lang') : 'ar';
        app()->setLocale($language);

        $this->apiToken = request()->headers->get('api_token');
        $this->main = $main;
        $this->push = $push;


    }







    public function details(Request $request)
    {
        $currentUser = User::whereApiToken($request->api_token)->first();


        $company = Company::with('membership', 'images')->whereId($request->companyId)->first();

        if (!$company) {
            return response()->json([
                'status' => false,
                'message' => 'عفواً, هذه المنشأة غير موجودة'
            ]);
        }

        if (!$company->user) {
            return response()->json([
                'status' => false,
                'message' => 'عفواً, المستخدم المسجيل هذه المنشأة غير موجود'
            ]);
        }


        $hasConversation = Conversation::whereHas('users', function ($q) use ($company) {
            $q->whereId($company->user->id);
        })->whereHas('users', function ($q) use ($currentUser) {
            $q->whereId($currentUser->id);
        })->first();


        $visit = $company->visits()->where([
            'company_id' => $request->companyId,
            'ip' => $request->playerId
        ])->first();

        if (!$visit && $request->playerId) {
            $view = new Visit;
            $view->ip = $request->playerId;
            $company->visits()->save($view);
        }

//        if(auth()->user())
//        {
        $company->likes = $company->likes()->where('like', 1)->count();
        $company->dislike = $company->likes()->where('like', 0)->count();
        $company->favorites = $company->favorites()->count();


        $company->isFavorite = ($currentUser->favorites()->whereId($company->id)->first()) ? true : false;


        $company->averageRating = ($company->ratings()->count() > 5) ? $company->averageRating : 0;
        $userRate = $company->ratings()->where('user_id', $currentUser->id)->first();
        $company->userRatings = ($userRate) ? $userRate->rating : 0;


        $company->commentsCount = $company->comments()->count();
        $company->hasConversation = ($hasConversation) ? true : false;
        if ($hasConversation)
            $company->conversationId = $hasConversation->id;


//        }
        $company->visits = $company->visits()->count();

        /**
         * Return Data Array
         */
        return response()->json([
            'status' => true,
            'data' => $company
        ]);
    }


    public function updateDriver(Request $request)
    {

        $token = ltrim($request->headers->get('Authorization'), "Bearer ");

        $company = User::whereApiToken($token)->first();
        $user = User::whereIsUser(2)->whereId($request->driverId)->first();


        $validator = Validator::make($request->all(), [
            'phone' => 'required|unique:users,phone,' . $user->id,
            'name' => 'required|unique:users,name,' . $user->id,

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


        if($request->name)
            $user->name = $request->name;

        if($request->phone)
            $user->phone = $request->phone;

        //$user->parent_id = $company->id;

        // $user->password = $request->password;

        if($request->address)
            $user->address = $request->address;

        // $user->api_token = str_random(60);

        if ($request->has('city'))
            $user->city_id = $request->city;



        
        if($request->driving_license)
            $user->driving_license = $request->driving_license;

        if($request->sizeId)
            $user->commercial_registry_no = $request->sizeId;


        if ($request->hasFile('traffic_license') && $request->hasFile('traffic_license') != null):
            $user->traffic_license = $request->root() . '/public/' . $this->public_path . UploadImage::uploadImage($request, 'traffic_license', $this->public_path);
        endif;

        if ($request->hasFile('transporter_image') && $request->hasFile('transporter_image') != null):
            $user->commercial_registry_image = $request->root() . '/public/' . $this->public_path . UploadImage::uploadImage($request, 'transporter_image', $this->public_path);
        endif;


        $actionCode = rand(1000, 9999);
        $actionCode = $user->actionCode($actionCode);
        $user->action_code = $actionCode;

        $user->is_user = 2;
        if ($user->save()) {
            $userInfo = $user->driverUserToArray();
            $cityObj = $user->city;
            if (count((array)$cityObj) != 0)
                $userInfo['city'] = $cityObj;
            //$this->manageDevices($request, $user);
            return response()->json([
                'status' => 200,
                'data' => $userInfo,

            ]);
        }
    }


    public function updateUserPassword(Request $request)
    {


        $api_token = ltrim($request->headers->get('Authorization'), "Bearer ");
        $user = User::whereApiToken($api_token)->first();

        $validator = Validator::make($request->all(), [
            'old_password' => 'required',
            'newpassword' => 'required',
            'confirm_newpassword' => 'required|same:newpassword'
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

        $hashedPassword = $user->password;
        if (Hash::check($request->old_password, $hashedPassword)) {
            //Change the password
            $user->fill([
                'password' => Hash::make($request->newpassword)
            ])->save();


            $userInfo = $user->driverUserToArray();
            $cityObj = $user->city;
            if (count((array)$cityObj) != 0)
                $userInfo['city'] = $cityObj;

            return response()->json([
                'status' => 200,
                'message' => __('global.password_was_edited_successfully'),
                'data' => $userInfo
            ]);

        } else {
            return response()->json([
                'status' => 400,
                'message' => __('global.old_password_is_incorrect'),
            ]);
        }


    }


    public function updateDriverImage(Request $request)
    {


        $api_token = ltrim($request->headers->get('Authorization'), "Bearer ");
        $user = User::whereApiToken($api_token)->first();



        if ($request->hasFile('image')):
            $user->image = $request->root() . '/public/' . $this->public_path . UploadImage::uploadImage($request, 'image', $this->public_path, 1280, 583);
        endif;



        if ($user->save()) {

            return response()->json([
                'status' => 200,
                'message' => __('trans.profile_update_avatar'),
            ]);


        } else {

            return response()->json([
                'status' => 400,
                'message' => __('trans.incorrect_update_profile'),
            ]);

        }
    }

    public function driverDelete(Request $request)
    {


        $api_token = str_replace('Bearer ', '', request()->headers->get('Authorization'));
        $company = User::whereApiToken($api_token)->first();

        $driver = User::whereId($request->driverId)->first();

        if ((int)$company->id == (int)$driver->parent_id) {


            $orders = Order::with('track')->whereHas('track', function ($obj) use ($driver) {
                return $obj->where('driver_id', $driver->id)->where('delivered_at', null);
            })->get();


            if ($orders->count() > 0) {
                return response()->json(['status' => 400, 'message' => 'عفواً, لا يمكنك حذف المسائق نظراً لوجود طلب لديه الان.']);
            } else {

                $driver->is_active = -1;
                if ($driver->save()) {
                    return response()->json(['status' => 200, 'message' => 'لقد تم حذف السائق بنجاح.']);
                }

            }
        } else {
            return response()->json(['status' => 400, 'message' => 'this driver not belongs to this company.']);
        }
    }


    public function cancelOrder(Request $request)
    {

        $api_token = ltrim($request->headers->get('Authorization'), "Bearer ");
        $user = User::whereApiToken($api_token)->first();

        $order = Order::find($request->orderId);
        





        if ($order->user_id == $user->id) {

            if ($order->status == 0) {
                $order->status = -1;
                $order->reason = $request->reason;

                if ($order->save()) {


                    $data = array(
                        "user_id" => $order->company->id,
                        'title' => $order->company->lang == "ar" ? "إلغاء الطلب" : "Cancel Order" ,
                        'body' => $order->company->lang == "ar" ? "لقد تم إلغاء الطلب رقم #$order->id من قبل العميل" : "Order #$order->id has been cancelled by client" ,
                        'order_id' => $request->orderId,
                        'type' => 6,
                        'sender_id' => $user->id,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    );


                    $this->main->insertData(Notification::class, $data);

                    $data['href'] = "";
                    $data['order'] = $order;



                    $companyDevicesAndroid =  $order->company->devices()->where('device_type', 'android')->pluck('device');
                    $companyDevicesIos =  $order->company->devices()->where('device_type', 'ios')->pluck('device');



                  $this->push->sendPushNotification($companyDevicesAndroid, $companyDevicesIos, $data['title'], $data['body'], $data);




                    return response()->json(['status' => 200, 'message' => "لقد تم إلغاء الطلب بنجاح"]);
                }

            } elseif ($order->status != -1) {
                return response()->json(['status' => 400, 'message' => "عفواً, لا يمكنك إلغاء الطلب"]);
            } elseif ($order->status == -1) {
                return response()->json(['status' => 400, 'message' => "عفواً, الطلب ملغي من قبل "]);
            }

        }

    }


    public function cancelOrderByDriver(Request $request)
    {

        $api_token = ltrim($request->headers->get('Authorization'), "Bearer ");
        $user = User::whereApiToken($api_token)->first();

        $order = Order::find($request->orderId);
        
        if(!$order){
            return response()->json(['status' => 400, 'message' => "عفواً, الطلب غير موجود"]);
        }
        
        

        if ($order->track && $order->track->driver_id == $user->id) {
        
       	    $driver = User::whereId($order->track->driver_id)->first();

            if ($order->status == 2) {
                $order->status = -2;
                $order->reason = $request->reason;

                if ($order->save()) {
                
                 	$users = User::whereIn('id', [$order->user_id, $order->company_id])->get();
	                $notifyDataAr = [
	                        "title" => "إلغاء الرحلة",
	                        "body" => "لقد تم إلغاء الرحلة للطلب رقم #($order->id) من قبل السائق ($driver->name)",
	                        'type' => 13,
	                        'senderId' => $driver->id,
	                        'order' => $order
	                    ];
	               	$notifyDataEn = [
	                        "title" => "Trip Started",
	                        "body" => "The trip has been cancelled for order (#$order->id) by driver ($driver->name)",
	                        'type' => 13,
	                        'senderId' => $driver->id,
	                        'order' => $order
	                    ];
	                $this->sendNotificationSingleCompSingleStation($request, $users, $notifyDataAr,$notifyDataEn);
	                    
	                   
                    return response()->json(['status' => 200, 'message' => "لقد تم إلغاء الطلب بنجاح"]);
                }

            } elseif($order->status == -2){
                return response()->json(['status' => 400, 'message' => "عفواً, لقد تم إلغاء الطلب من قبل"]);
            }else {
                return response()->json(['status' => 400, 'message' => "عفواً, لا يمكنك إلغاء الطلب"]);
            }
        }else{
            return response()->json(['status' => 400, 'message' => "عفواً, ليس لديك صالحية لإلغاء الطلب"]);
        }

    }


    public function assignorderToDriver(Request $request)
    {


        $driver = User::find($request->driverId);





        $order = Order::find($request->orderId);

        if (!$order) {
            return response()->json([
                "status" => 400,
                "message" => "عفواً, الطلب غير موجود بالنظام"

            ]);
        }


        if (!$driver) {
            return response()->json([
                "status" => 400,
                "message" => "عفواً,السائق غير موجود فى النظام"

            ]);
        }


        $isExsit = $driver->assignOrders->where('order_id', $request->orderId)->first();

        if ($isExsit) {
            return response()->json([
                "status" => 400,
                "message" => "عفواً, لقد تم إرفاق الطلب من قبل لهذا السائق"

            ]);
        }


        $haveOrderInProgress = $driver->assignOrders->where('delivered_at', null);

        if ($haveOrderInProgress->count() > 0) {

            return response()->json([
                "status" => 400,
                "message" => " عفوا, هذا السائق لديه طلب  لم يكتمل بعد "
            ]);

        }


        $orderIsExsit = Track::whereOrderId($request->orderId)->first();

        if ($orderIsExsit) {
            return response()->json([
                "status" => 400,
                "message" => "عفواً, لقد تم إرفاق الطلب لسائق من قبل "

            ]);
        }


        $track = new Track();
        $track->driver_id = $request->driverId;
        $track->order_id = $request->orderId;


        if ($request->hasFile('orderImage')):
            $track->image = $request->root() . '/public/' . $this->public_path . UploadImage::uploadImage($request, 'orderImage', $this->public_path);
        endif;


        if ($track->save()) {


            $order->status = 1;
            if ($order->save()) {



                $data = array(
                    "user_id" => $driver->id,
                    'title' => $driver->lang == "ar" ? "عندك طالعة ياسطى" : "Andk talaa yasta" ,
                    'body' =>  $driver->lang == "ar" ? "عندك طلعة يا اوسطى هاهاهاها" : "Andk talaa Osta hahahahahaha" ,
                    'order_id' => $request->orderId,
                    'type' =>2,
                    'sender_id' => $driver->company_id,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                );

                $this->main->insertData(Notification::class, $data);

                $data['order'] = $order;

                $driverDevicesAndroid =  $driver->devices()->where('device_type', 'android')->pluck('device');
                $driverDevicesIos =  $driver->devices()->where('device_type', 'ios')->pluck('device');

                $this->push->sendPushNotification($driverDevicesAndroid, $driverDevicesIos, $data['title'], $data['body'], $data);
                return response()->json([
                    "status" => 200,
                    "message" => __("trans.orderAssignTODriverSuccessfully")
                ]);
            }
        }
    }


    public function tripCycle(Request $request)
    {

        $api_token = ltrim($request->headers->get('Authorization'), "Bearer ");
        $driver = User::whereApiToken($api_token)->first();
        $track = Track::whereOrderId($request->orderId)->whereDriverId($driver->id)->first();
        $order = Order::whereId($request->orderId)->first();

        $companyId = $driver->parent_id;
        $stationId = $order->station['id'];
        $users = User::whereIn('id',[$companyId, $stationId])->get();


        if ($request->status && $request->status == "start") {

            $track->started_at = date('Y-m-d H:i:s');
            $track->latitute = $request->latitute;
            $track->longitute = $request->longitute;


            if ($track->save()) {
                $order->status = 2;
                if($order->save()){
                    $notifyDataAr = [
                        "title" => "بدء الرحلة",
                        "body" => "لقد تم بدء الرحلة للطلب رقم #($order->id) من قبل السائق ($driver->name)",
                        'type' => 3,
                        'senderId' => $driver->id,
                        'order' => $order
                    ];
                    $notifyDataEn = [
                        "title" => "Trip Started",
                        "body" => "The trip has been started for order (#$order->id) by driver ($driver->name)",
                        'type' => 3,
                        'senderId' => $driver->id,
                        'order' => $order
                    ];
                    $this->sendNotificationSingleCompSingleStation($request, $users, $notifyDataAr,$notifyDataEn);
                    return response()->json([
                        "status" => 200,
                        "message" => __('trans.tripStartedSuccessfully')
                    ]);
                }
            }
        }


        if ($request->status && $request->status == "delivered") {
            $track->delivered_at = Carbon::now();
            $track->is_okay = $request->isOkay;
            $track->note = $request->note;
            if ($track->save()) {



                $order->status = 3;
                if ($order->save()) {

                    if( $order->payment_type == 0){

                        if($track->is_okay == 0){
                            $notifyTitleAR = 'تسليم الطلب';
                            $notifyTitleEN = 'Order Delivered';
                            $notifyBodyAR = "لقد تم تسليم الطلب رقم # $order->id $track->note ولم يتم إستلام مبلغ مالي";
                            $notifyBodyEN = "لقد تم تسليم الطلب رقم # $order->id $track->note ولم يتم إستلام مبلغ مالي";
                        }else{
                            $notifyTitleAR = 'تسليم الطلب';
                            $notifyTitleEN = 'Order Delivered';
                            $notifyBodyAR = "تم تسليم الطلب رقم # $order->id وتم إستلام المبلغ من المحطة.";
                            $notifyBodyEN = "تم تسليم الطلب رقم # $order->id وتم إستلام المبلغ من المحطة.";
                        }

                        $notifyDataAr = [
                            "title" => $notifyTitleAR,
                            "body" => $notifyBodyAR,
                            'type' => 6,
                            'senderId' => $driver->id,
                            'order' => $order
                        ];
                        $notifyDataEn = [
                            "title" => $notifyTitleEN,
                            "body" => $notifyBodyEN,
                            'type' => 6,
                            'senderId' => $driver->id,
                            'order' => $order
                        ];

                        $this->sendNotificationSingleCompSingleStation($request, $users, $notifyDataAr,$notifyDataEn);

                    }else{

                        $notifyDataAr = [
                            "title" => "إنتهاء الرحلة",
                            "body" => "لقد تم إنهاء الرحلة للطلب رقم #($order->id) من قبل السائق ($driver->name)",
                            'type' => 6,
                            'senderId' => $driver->id,
                            'order' => $order
                        ];
                        $notifyDataEn = [
                            "title" => "Trip Finished",
                            "body" => "The trip has been Finished for order (#$order->id) by driver ($driver->name)",
                            'type' => 6,
                            'senderId' => $driver->id,
                            'order' => $order
                        ];
                        $this->sendNotificationSingleCompSingleStation($request, $users, $notifyDataAr,$notifyDataEn);

                    }
                    return response()->json([
                        "status" => 200,
                        "message" => "لقد تم إكتمال الرحلة وتسليم الطلب بنجاح"
                    ]);



                }


            }
        }

        if ($request->status && $request->status == "track") {
            $track->latitute = $request->latitute;
            $track->longitute = $request->longitute;
            if ($track->save()) {
                return response()->json([
                    "status" => 200,
                    "message" => "لقد تمت بداية الرحلة"
                ]);

            }
        }


    }



    private function sendNotificationSingleCompSingleStation($request, $users, $notifyDataAr,$notifyDataEn){

        $usersAr =  $users->where('lang', 'ar')->values();
        $usersEn =  $users->where('lang', 'en')->values();

        $this->sendNotificationByLang($request, $notifyDataAr, $usersAr);
        $this->sendNotificationByLang($request, $notifyDataEn, $usersEn);

    }



    private function sendNotificationByLang($request, $notifyData, $users)
    {

        $data = array(
            'title' => $notifyData['title'],
            'body' => $notifyData['body'],
            'type' => $notifyData['type'],
            'order' => isset($notifyData['order']) && $notifyData['order'] != null ?  $notifyData['order'] : null
        );

        $notificationData = [];
        $devices = [];

        foreach ($users as $user) {
            $notificationData[] = array(
                'user_id' => $user->id,
                'title' => $data['title'],
                'body' => $data['body'],
                'order_id' =>  isset($notifyData['order']) && $notifyData['order'] != null ?  $notifyData['order']['id'] : null,
                'type' => $data['type'],
                'sender_id' => $notifyData['senderId'] == "" ? null : $notifyData['senderId'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            );

            foreach ($user->devices as $device) {
                $devices[] = $device;
            }
        }

        Notification::insert($notificationData);

        $androidDevice = collect($devices)->where('device_type', 'android')->pluck('device');
        $iosDevice = collect($devices)->where('device_type', 'ios')->pluck('device');
        $this->push->sendPushNotification($androidDevice, $iosDevice, $data['title'], $data['body'], $data);
    }


    public function getLatLng(Request $request)
    {

        $latLng = Track::where('order_id', $request->orderId)->first();

        $order = Order::find($request->orderId);

        $driver = User::whereId($latLng->driver_id)->first();


        if (!$order) {
            return response()->json(['status' => 400, "message" => "Sorry, Order Not Found."]);
        }

        if ($order && !$latLng) {
            return response()->json(['status' => 400, "message" => "Sorry, Order Not Assigned yet."]);
        }

        $data = [
            "driverLat" => $latLng->latitute,
            "driverLng" => $latLng->longitute,
            "stationLat" => $order->latitute,
            "stationLng" => $order->longitute,
            "driverPhone" => $driver->phone
        ];

        return response()->json([
            "status" => 200,
            "message" => "Lat Lng of order",
            "data" => $data

        ]);

    }


    public function companiesList(Request $request)
    {

        /**
         * Set Default Value For Skip Count To Avoid Error In Service.
         * @ Default Value 15...
         */
        if (isset($request->pageSize)):
            $pageSize = $request->pageSize;
        else:
            $pageSize = 15;
        endif;
        /**
         * SkipCount is Number will Skip From Array
         */
        $skipCount = $request->skipCount;
        $itemId = $request->itemId;

        $currentPage = $request->get('page', 1); // Default to 1


        $query = Company::orderBy('created_at', 'desc')->select();


        $query->where('membership_id', '!=', NULL)
            ->whereIsAgree(1);
        /**
         *
         * @@ Get By Name Of Company.
         */
        if ($request->companyName) :
            $query->where('name', 'Like', "%$request->companyName%");
        endif;

        /**
         * @@ Get By Name Of Product Related for Company.
         */
        if ($request->productName) :
            $query->whereHas('products', function ($q) use ($request) {
                $q->where('name', 'like', "%$request->productName%");
            })->get();
        endif;

        /**
         *
         * @@ Get By City Of Company.
         */
        if ($request->city) :
            $query->where('city_id', '=', $request->city);
        endif;


        /**
         * @@ Get By City Of Company.
         */

        if ($request->mainCategory) {

            $categories = Category::whereParentId($request->mainCategory)->get();

            $arrIds = [];

            foreach ($categories as $category):
                $arrIds[] = $category->id;
            endforeach;
            $query->whereIn('category_id', $arrIds);

        }


        /**
         * @@ Get By City Of Company.
         */

        if ($request->subCategory) {


            $query->where('category_id', $request->subCategory);

        }


        /**
         * @ If item Id Exists skipping by it.
         */
        if ($itemId) {
            $query->where('id', '<=', $itemId);
        }


        if (isset($request->filterby) && $request->filterby == 'date') {
            $query->orderBy('created_at', 'desc');
        } elseif (isset($request->filterby) && $request->filterby == 'visits') {
//            $query->whereHas('products', function ($q) use ($request) {
//                $q->where('company_id', $q->id);
//            })->get();
        }


        /**
         * @@ Skip Result Based on SkipCount Number And Pagesize.
         */
        $query->skip($skipCount + (($currentPage - 1) * $pageSize));
        $query->take($pageSize);

        /**
         * @ Get All Data Array
         */


//        if($request->visits){
//            /**
//             * @@ Get By Name Of Product Related for Company.
//             */
//
//            $query->whereHas('visits', function ($q) use ($request) {
//                    $q->orderBy('name', 'like', "%$request->productName%");
//                })->get();
//
//        }


        $companies = $query->get();

        $companies->map(function ($q) use ($request) {

            $q->likes = $q->likes()->where('like', 1)->count();
            $q->dislike = $q->likes()->where('like', 0)->count();
            $q->favorites = $q->favorites()->count();
            $q->ratings = $q->averageRating();
            $q->visits = $q->visits()->count();
            $q->phone = ($user = $this->companyCompleteFromUser($q->id)) ? $user->phone : null;
            $q->city = $this->getCityForCompany($q->id);
            $q->commentsCount = $this->getCountsForCompany($q->id);
            $q->membership = $this->getMembershipForCompany($q->id);

            $q->averageRating = ($q->ratings()->count() > 5) ? $q->averageRating : 0;


            if ($request->api_token) {
                $currentUser = User::whereApiToken($request->api_token)->first();
                $userRate = $q->ratings()->where('user_id', $currentUser->id)->first();
                $q->userRatings = (isset($userRate)) ? $userRate->rating : 0;
                $q->isFavorite = ($currentUser->favorites()->whereId($q->id)->first()) ? true : false;
            }


            $q->userRatings = 0;
        });


        if (isset($request->filterby) && $request->filterby == 'visits') {
            $sorted = $companies->sortByDesc('visits');
            $companies = $sorted->values()->all();
        }


        if (isset($request->filterby) && $request->filterby == 'rate') {
            $sorted = $companies->sortByDesc('ratings');
            $companies = $sorted->values()->all();
        }

        /**
         * Return Data Array
         */

        return response()->json([
            'status' => true,
            'data' => $companies
        ]);
    }


    public function commentList(Request $request)
    {
        /**
         * Set Default Value For Skip Count To Avoid Error In Service.
         * @ Default Value 15...
         */
        if (isset($request->pageSize)):
            $pageSize = $request->pageSize;
        else:
            $pageSize = 15;
        endif;
        /**
         * SkipCount is Number will Skip From Array
         */
        $skipCount = $request->skipCount;
        $itemId = $request->itemId;

        $currentPage = $request->get('page', 1); // Default to 1

        $query = Comment::with('user')
            ->where(['commentable_id' => $request->companyId, 'is_agree' => 1])
            ->orderBy('created_at', 'desc')
            ->select();

        /**
         * @ If item Id Exists skipping by it.
         */
        if ($itemId) {
            $query->where('id', '<=', $itemId);
        }

        if (isset($request->filterby) && $request->filterby == 'date') {
            $query->orderBy('created_at', 'desc');
        }
        /**
         * @@ Skip Result Based on SkipCount Number And Pagesize.
         */
        $query->skip($skipCount + (($currentPage - 1) * $pageSize));
        $query->take($pageSize);

        /**
         * @ Get All Data Array
         */

        $comments = $query->get();

        /**
         * Return Data Array
         */

        return response()->json([
            'status' => true,
            'data' => $comments
        ]);

    }


    /**
     * @param $company
     * @return array|null
     */

    private function getCountsForCompany($company)
    {
        $company = Company::with('comments')->whereId($company)->first();

        return ($company && $company->comments) ? $company->comments->count() : NULL;
    }


    /**
     * @param $company
     * @return array|null
     */

    private function getMembershipForCompany($company)
    {
        $company = Company::with('membership')->whereId($company)->first();
        return ($company && $company->membership) ? [
            'id' => $company->membership->id,
            'name' => $company->membership->name,
            'color' => $company->membership->color
        ] : NULL;
    }

    /**
     * @param $company
     * @return null
     */
    private function getCityForCompany($company)
    {
        $company = Company::with('city')->whereId($company)->first();
        return ($company && $company->city) ? $company->city->name : NULL;
    }


    /**
     * @param $company
     * @return mixed
     */
    private function companyCompleteFromUser($company)
    {
        $company = Company::with('user')->whereId($company)->first();
        return ($company && $company->user) ? $company->user : NULL;
    }


    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     */
    public function likeCompany(Request $request)
    {
        $user = auth()->user();
        $company = Company::whereId($request->companyId)->first();

        if (!$company) {
            return response()->json(['status' => false, 'message' => 'Company Not Found in System']);
        }

        try {
            $isLiked = $company->likes()->where('user_id', $user->id)->first();
            if (is_object($isLiked)) {
                $like = Like::find($isLiked->id);
                $like->like = $request->type;
                $like->user_id = auth()->id();
                if ($company->likes()->save($like)) {
                    $message = ($like->like == 1) ? 'Like' : 'Dislike';
                    return response()->json([
                        'status' => true,
                        'message' => $message,
                        'data' => [
                            'likesCount' => $company->likes()->whereLike(1)->count(),
                            'disLikesCount' => $company->likes()->whereLike(0)->count(),

                        ]
                    ]);
                }
            } else {
                $like = new Like;
                $like->like = $request->type;
                $like->user_id = auth()->id();
                if ($company->likes()->save($like)) {
                    return response()->json([
                        'status' => true,
                        'message' => 'لقد تم الاعجاب',
                        'data' => [
                            'likesCount' => $company->likes()->whereLike(1)->count(),
                            'disLikesCount' => $company->likes()->whereLike(0)->count(),

                        ]
                    ]);
                }
            }

        } catch (QueryException $e) {
            return response()->json([
                'status' => false,
                'message' => 'erroraddtofavorite',
                'data' => []
            ]);

        }
    }


    public function getCompaniesByProducts(Request $request)
    {


//        return $this->apiToken;

        $query = User::whereIsUser(1);

        if ($request->has("cityId") && $request->cityId != "") {
            $query->whereCityId($request->cityId);
        }

        if ($request->has("productId") && $request->productId != "") {
            $query->whereHas('products', function ($obj) use ($request) {
                $obj->whereId($request->productId);
            });
        }

        $companies = $query->get();


        $companiesInfo = [];
        foreach ($companies as $company) {
            $companiesInfo[] = $company->companyUserToArray();
        }

        return response()->json([
            'status' => 200,
            "message" => "Companies List.",
            "data" => $companiesInfo
        ]);
//
//        $companies->map(function ($obj) {
//            $obj->info = $obj->companyUserToArray();
//        });

//        return $companies;
    }


    public function driversList(Request $request)
    {
        $token = ltrim($request->headers->get('Authorization'), "Bearer ");
        $company = User::whereApiToken($token)->first();


        if (isset($request->pageSize)):
            $pageSize = $request->pageSize;
        else:
            $pageSize = 10;
        endif;

        $skipCount = $request->skipCount;

        $currentPage = $request->get('page', 1); // Default to 1

        $query = User::where('is_active', '!=', -1)->whereIsUser(2)->whereParentId($company->id);

        $query->skip($skipCount + (($currentPage - 1) * $pageSize));

        $query->take($pageSize);

        /**
         * @ Get All Data Array
         */

        // Get Orders List Using Skip count pagination.
        $drivers = $query->get();

        $companiesInfo = [];
        foreach ($drivers as $driver) {
            $companiesInfo[] = $driver->driverUserToArray();
        }
        return response()->json([
            'status' => 200,
            "message" => "Drivers List.",
            "data" => $companiesInfo
        ]);
    }


    public function checkIsOrderAvailable(Request $request)
    {


        $token = ltrim($request->headers->get('Authorization'), "Bearer ");


        $station = User::whereApiToken($token)->first();
        $setting = new Setting;
        
      
  


        //return $station;

        $company = User::findOrFail($request->companyId);

        $branchLat = $company->branch->latitute;

        $branchLng = $company->branch->longitute;

        $distence = ($station && $this->getRealDistance("$station->latitute,$station->longitute", "$branchLat,$branchLng")) ? $this->getRealDistance("$station->latitute,$station->longitute", "$branchLat,$branchLng") : $this->distance($station->latitute, $station->longitute, $branchLat, $branchLng, "K");


        $companyOrders = Order::whereCompanyId($company->id)->whereDate('created_at', Carbon::today())->get();

        if ($company->order_per_day <= $companyOrders->count()) {
            return response()->json([
                'status' => 200,
                "message" => "لا توجد طلبات متاحة لليوم",
                "isAvailable" => false
            ]);
        }

        $product = Product::find($request->productId);
        $size = Size::find($request->sizeId);

        $total = 0;


        // GET PRODUCT PRICE
        $total += $productPrice = (int)$product->price_per_liter * (int)$size->name;
        


        $priceCost = 0;

        // GET TRANSPORT PRICE
        if ($station->city_id == $company->branch->city_id) {
            $priceCost = ($company->transport_price_in * $distence) * 2;
            $total += $priceCost;
        } else {
            $priceCost = ($company->transport_price_out * $distence) * 2;
            $total += $priceCost;
        }
        
        
        $priceTransAndProduct = $priceCost + $productPrice;
        
       // $transportPriceAndProductPrice =  ($priceTransAndProduct / 100) * $setting->getBody('app_tax_percentage');

        // CALCULATE TAXES
        $total += ($priceTransAndProduct / 100) * $setting->getBody('app_tax_percentage');
        
        
        
        

        // APP PERCENTAGE PRICE
        $total += ($priceCost / 100) * $setting->getBody('app_profit_percentage');
        
        
        
        $total = number_format($total, 0, '.', '');
        
        return response()->json([
            'status' => 200,
            'total' => $total,
            'productPrice' => $productPrice,
            'transportCost' => $priceCost,
            'taxes' => (int) number_format(($priceTransAndProduct / 100) * $setting->getBody('app_tax_percentage'), 0, '.', ''),
            'appPercentage' => (int) number_format(($priceCost / 100) * $setting->getBody('app_profit_percentage'), 0, '.', ''),
            "message" => "لديك إمكانية إضافة طلب",
            "isAvailable" => true
        ]);
    }


    function getRealDistance($origin, $destination)
    {

        $getdata = http_build_query(
            $fields = array(
                "key" => "AIzaSyCxVoYPtxKQs7adRbj5nyGvFfJLffXfidQ",
                "origin" => $origin,
                "destination" => $destination
            ));

        $opts = array('http' =>
            array(
                'method' => 'GET',
                'header' => 'Content-type: application/x-www-form-urlencoded',

            )
        );

        $context = stream_context_create($opts);

        $results = file_get_contents("https://maps.googleapis.com/maps/api/directions/json?" . $getdata, false, $context);

        $data = json_decode($results, true);


//       return  $data['routes'];


        if (count($data['routes']) > 0) {

            $res = $data['routes'][0];

            $km = 0;

            foreach ($res['legs'] as $leg) {
                $km += $leg['distance']['value'];
            }

            return $km / 1000;

        } else {

            return false;
        }


    }


    function distance($lat1, $lon1, $lat2, $lon2, $unit)
    {

        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        $unit = strtoupper($unit);
        if ($unit == "K") {
            return ($miles * 1.609344);
        } else if ($unit == "N") {
            return ($miles * 0.8684);
        } else {
            return $miles;
        }
    }


}
