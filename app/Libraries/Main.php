<?php

namespace App\Libraries;

use App\Battery;
use App\Cover;
use App\Http\Controllers\Api\V1\OrdersController;
use App\Libraries\FirebasePushNotifications\config;
use App\Maintenance;
use App\Models\Cart;
use App\Models\Meal;
use App\Models\Sub;
use App\Models\Subscription;
use App\Order;
use App\Orderaccessory;
use App\Orderbattery;
use App\Ordercover;
use App\Ordermaintenance;
use App\Pricing;
use App\Models\Setting;
use App\Size;
use App\Models\User;
use App\Models\Notification;
use App\City;
use App\Spareparts;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Silber\Bouncer\Database\Role;
use LaravelFCM\Message\OptionsBuilder;
use LaravelFCM\Message\PayloadDataBuilder;
use LaravelFCM\Message\PayloadNotificationBuilder;
use FCM;
use App\Notifications;
use App\Device;
use Illuminate\Http\Request;
use SoapClient;

class Main
{


    /**
     * @return int|mixed|string
     * Get other language.
     */

    public function otherLang()
    {

        // create variable to save default or current language.
        $lang = config('app.locale');

        // get all languages in app.
        // and loop in it then put other (not current in var $lang)
        foreach (config('translatable.locales') as $key => $val) {

            if ($key != config('app.locale')) {

                $lang = $key;
            }
        }
        return $lang;
    }


    public function moveCartToUser()
    {
        $carts = Cart::whereUserId(null)->orWhere('ip_address', request()->ip())->get();

        if (auth()->check()) {
            foreach ($carts as $cart) {
                $cart->user_id = auth()->id();
                $cart->ip_address = null;
                $cart->save();
            }
        }
    }


    public function moveOrderFromDeviceToAccount()
    {


        $orders = \App\Models\Order::where('device_id', \request()->ip())->get();

        if (auth()->check()) {
            foreach ($orders as $order) {
                $order->user_id = auth()->id();
                $order->device_id = null;
                $order->save();
            }
        }
    }


    public function suspendCompanyBy()
    {

        $setting = new Setting;
        $push = new \App\Libraries\PushNotification;
        $companies = User::whereIsUser(1)->get();


        foreach ($companies as $company) {


            if ($company->transactions($company->id) == "") {
                continue;
            }


            $transactions = "Data Form Inner";
            $transactions = $company->transactions($company->id);
            if ($data['companyDues'] > (int)$setting->getBody("max_value_for_skipped_by_transformers")) {

                $company->is_suspend = 1;
                $company->api_token = str_random(60);
                if ($company->save()) {

                    $data = array(
                        "user_id" => $company->id,
                        'title' => "حظر الحساب",
                        'body' => "لقد تم حظر حسابكم لتخطي المبلغ المتفق عليه لحساب التطبيق",
                        'order_id' => null,
                        'type' => 6,
                        'sender_id' => null,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    );


                    $this->insertData(Notification::class, $data);


                    $companyDevicesAndroid = $company->devices()->where('device_type', 'android')->pluck('device');
                    $companyDevicesIos = $company->devices()->where('device_type', 'ios')->pluck('device');

                    $this->push->sendPushNotification($companyDevicesAndroid, $companyDevicesIos, $data['title'], $data['body'], $data);
                }
            }
        }
    }

    public function designDirection()
    {

        $lang = config('app.locale');


        if ($lang == 'ar') {
            $redirect = 'rtl';
        } else {
            $redirect = 'ltr';
        }
        return $redirect;
    }


    public function getName(Role $role, $name)
    {
        $ability = $role->abilities()->whereName($name)->first();

        if ($ability['title'] != '') {
            return $ability['title'];
        } else {
            if ($ability['name'] == '*') {
                return 'كل الصلاحيات';
            } else {
                return $ability['name'];
            }
        }
    }


    public function getRoleTitleByName(Role $role)
    {
        $role = Role::whereName($role->name)->first();
        return $role->title;
    }


    public function isCompanyManager(User $user)
    {
        return ($user->branch_id == 0) ? true : false;
    }


    function getOrdersTypesInfoBy($id)
    {
        $order = Order::whereId($id)->first();
        if (!$order) {
            return 'Order Not Found';
        }
        $order->ordersBattery->load('size', 'battery', 'model.brand');
        $order->ordersMaintenance->load('maintenance', 'model.brand');
        $order->ordersAccessory->load('model.brand');
        $order->ordersCover->load('model.brand', 'cover');
        return $order;
    }


    public function checkOrderStatus()
    {
        $orders = Order::where(['status' => 0])->get();


        foreach ($orders as $order) {
            $original = new \Carbon\Carbon($order->created_at);
            $date = $original->addMinutes(4);
            $date = is_object($date) ? $date->toDateTimeString() : '';
            if (Carbon::now() >= $date) {
                $assignOrders = $this->assignOrderStatus($order->id);
                $assignOrdersPriceInstallation = $this->assignOrderInstallationPrice($order->id);

                if ($assignOrders->count() > 0) {
                    foreach ($assignOrders as $assign) {
                        $pricing = new Pricing();
                        $user = User::whereId($assign->pricing_by)->first();
                        if (!$user->company_id)
                            continue;
                        $pricing->price = $assign->price;
                        $pricing->company_id = $user->company_id;
                        $pricing->order_id = $assign->order_id;
                        $pricing->final_price = $assign->price;
                        if ($assign->shipping_cost > 0) {
                            $pricing->ship_price = $assign->shipping_cost;
                        }
                        if ($assign->work_price > 0) {
                            $pricing->install_price = $assignOrdersPriceInstallation;
                        }

                        if ($assign->tax > 0) {
                            $pricing->tax = $assign->tax;
                        }


                        $pricing->is_payed = 0;
                        $pricing->save();
                    }
                    $order->status = config('constants.order.order_priced');
                    if ($order->save()) {


                        $user = \App\Models\User::whereId($order->user_id)->first();

                        $devices = $user->devices()->where('device_type', 'web')->get();


                        $notifyDevices = $devices->pluck('device');


                        $message = __('web.order_priced_done');
                        $description = __('web.is_priced') . __('web.order_priced_done_no') . " #" . $order->id . " " . __('web.click_to_view_prices');


                        $push = new \App\Libraries\PushNotification();

                        $data = array('href' => 'http://fb.com', 'image' => request()->root() . '/public/push.jpg');

                        $notification = new \App\Notifications();
                        $notification->user_id = $order->user_id;
                        $notification->type = 1;
                        $notification->order_id = $order->id;
                        $notification->message = $message;
                        $notification->description = $description;
                        if ($notification->save()) {
                            $push->sendPushNotification('multi', $notifyDevices, $data, $message, $description);

                            $orderId = $order->id;

                            $title_ar = "تم تسعير طلبك";
                            $title_en = " your order is priced ";
                            $desc_ar = "تم تسعير طلبك رقم #$orderId إضغط لعرض الأسعار.";
                            $desc_en = "Your order #$orderId has been priced  Click to view prices.";

                            $additional = [


                                'message' => $title_ar,
                                'description' => $desc_ar,
                                'message_ar' => $title_ar,
                                'description_ar' => $desc_ar,
                                'message_en' => $title_en,
                                'description_en' => $desc_en,

                            ];


                            $this->fcmNotification($order, config('constants.order.order_priced'), $additional);
                        }
                    }
                } else {

                    $message = __('web.order_unavailable');
                    $description = __('web.sorry_order_no') . " #" . $order->id . " " . __('web.o_unavailable');


                    $order->status = 2;
                    $order->save();


                    $user = \App\Models\User::whereId($order->user_id)->first();

                    $devices = $user->devices()->where('device_type', 'web')->get();


                    $notifyDevices = $devices->pluck('device');


                    $push = new \App\Libraries\PushNotification();


                    $data = array('href' => 'http://fb.com', 'image' => request()->root() . '/public/push.jpg');


                    $notification = new \App\Notifications();
                    $notification->user_id = $order->user_id;
                    $notification->type = 2;
                    $notification->order_id = $order->id;
                    $notification->message = $message;
                    $notification->description = $description;
                    if ($notification->save()) {
                        $push->sendPushNotification('multi', $notifyDevices, $data, $message, $description);


                        $orderId = $order->id;

                        $title_ar = "طلبك غير متوفر";
                        $title_en = "your order is not available ";
                        $desc_ar = "نعتذر منك, طلبك رقم #$orderId غير متوفر";
                        $desc_en = "Sorry , your order number #$orderId is not available ";

                        $additional = [


                            'message' => $title_ar,
                            'description' => $desc_ar,
                            'message_ar' => $title_ar,
                            'description_ar' => $desc_ar,
                            'message_en' => $title_en,
                            'description_en' => $desc_en,

                        ];


                        return $this->fcmNotification($order, 2, $additional);
                    }
                }
            }
        }
        return $orders;
    }



    //$data = array(
    //"user_id" => $user->id,
    //'title' => $user->lang == "ar" ? "title ARABIC" : "title ENGLISH  " ,
    //'body' =>  $user->lang == "ar" ? "body ARABIC" : "body ENGLISH   " ,
    //'order_id' => $orderId, // if found order ID else null
    //'type' =>2, // notification type to control in title and body
    //'sender_id' => auth()->id(), // always should be auth()->id()
    //'created_at' => Carbon::now(),
    //'updated_at' => Carbon::now()
    //);
    //
    //$this->main->insertData(Notification::class, $data);

    //
    //auth()->id() =  $user->devices()->where('device_type', 'web')->pluck('device');
    //
    //$this->push->sendPushNotification([], auth()->id(), $data['title'], $data['body'], $data);


    private function assignOrderStatus($id)
    {


        $myOrder = Order::findOrFail($id);

        if ($myOrder->ordertype_id == config('constants.orderType.maintenance')) {
            $orders = \DB::table('assign_order')
                ->where(['order_id' => $id, 'status' => 1])->orderBy('price', 'asc')->limit(4)->get();
        } else {
            $orders = \DB::table('assign_order')
                ->where(['order_id' => $id, 'status' => 1])->orderBy('price', 'asc')->limit(1)->get();
        }

        return $orders;
    }

    private function assignOrderInstallationPrice($id)
    {


        $orders = \DB::table('assign_order')
            ->where(['order_id' => $id, 'status' => 1, 'pricing_type' => 1])->orderBy('work_price', 'asc')->limit(1)->get();


        return $orders['work_price'];
    }


    function fcmNotification(Order $order, $type, $additional = [])
    {


        // $notification = new Notifications();
        // $notification->user_id = $order->user_id;
        // $notification->type = $type;
        // $notification->order_id = $order->id;
        // $notification->message = $message;
        // $notification->description = $description;
        // $notification->save();

        //for Android devices
        $optionBuilder = new OptionsBuilder();
        $optionBuilder->setTimeToLive(60 * 20);

        $notificationBuilder = new PayloadNotificationBuilder();
        $notificationBuilder->setBody($additional['message'])
            ->setSound('default');
        $order_details = $this->get_order_details($order->id);
        $dataBuilder = new PayloadDataBuilder();


        $pushData = array(
            'order' => $order_details,
            'type' => $type,
            'message' => $additional['message'],
            'description' => $additional['description'],
            'message_ar' => $additional['message_ar'],
            'description_ar' => $additional['description_ar'],
            'message_en' => $additional['message_en'],
            'description_en' => $additional['description_en'],
        );

        if ($type == -1 || $type == -2) :
            $dataBuilder->addData(['type' => $type, "message" => $additional['message'], 'description' => $additional['description']]);
        else :
            $dataBuilder->addData($pushData);
        endif;


        $option = $optionBuilder->build();
        $notification = $notificationBuilder->build();
        $data = $dataBuilder->build();


        $tokens = [];

        //$device_tokens = Device::select('device')->where('user_id', $order->user_id)->get();
        $device_tokens = Device::select('device')->where('user_id', $order->user_id)->where("device_type", "android")->get();


        foreach ($device_tokens as $this_device_token) {
            $tokens[] = $this_device_token->device;
        }


        if (count($tokens) > 0) {
            $downstreamResponse = @FCM::sendTo($tokens, null, null, $data);
            $downstreamResponse->numberSuccess();
            $downstreamResponse->numberFailure();
            $downstreamResponse->numberModification();
        }

        //for IOS devices

        $optionBuilder = new OptionsBuilder();
        $optionBuilder->setTimeToLive(60 * 20);

        $notificationBuilder = new PayloadNotificationBuilder();
        $notificationBuilder->setBody($additional['message'])
            ->setSound('default');

        $dataBuilder = new PayloadDataBuilder();


        $pushData = array(
            'order' => $order_details,
            'type' => $type,
            'message' => $additional['message'],
            'description' => $additional['description'],
            'message_ar' => $additional['message_ar'],
            'description_ar' => $additional['description_ar'],
            'message_en' => $additional['message_en'],
            'description_en' => $additional['description_en'],
        );


        $order_details = $this->get_order_details($order->id);
        if ($type == -1 || $type == -2) :
            $dataBuilder->addData(['type' => $type, "message" => $additional['message'], 'description' => $additional['description']]);
        else :
            $dataBuilder->addData($pushData);
        //$dataBuilder->addData(['order' => $order_details, 'type' => $type, 'message' => $message, 'description' => $description]);
        endif;


        $option = $optionBuilder->build();
        $notification = $notificationBuilder->build();
        $data = $dataBuilder->build();


        $tokens_ios = [];
        $device_tokens_ios = Device::select('device')->where('user_id', $order->user_id)->where('device_type', "ios")->get();
        foreach ($device_tokens_ios as $this_device_token_ios) {
            $tokens_ios[] = $this_device_token_ios->device;
        }


        if (count($tokens_ios) > 0) {
            $downstreamResponse = @FCM::sendTo($tokens_ios, null, $notification, $data);
            $downstreamResponse->numberSuccess();
            $downstreamResponse->numberFailure();
            $downstreamResponse->numberModification();
        }

        return $data;
    }

    public function get_order_details($order_id)
    {


        $order = Order::find($order_id);


        if (!$order) {
            return false;
        }


        $user = auth()->user();
        $q = Order::find($order->id);
        $q['car_data'] = @$this->getCarData($order->id);
        $q['shipping_data'] = @$this->getOrderBasic($order->id, $user);
        if ($q->ordertype_id == 1) {
            $q['battery_order'] = @$this->orderBattery($order->id);
        } elseif ($q->ordertype_id == 4) {
            $q['cover_order'] = @$this->orderCover($order->id);
        } elseif ($q->ordertype_id == 2) {
            $q['maintenance_order'] = @$this->orderMaintenance($order->id);
        } else {
            $q['sparpart_order'] = @$this->orderAccessory($order->id);
        }

        $q->lat = $q->lat ? $q->lat : "";
        $q->lng = $q->lng ? $q->lng : "";
        $q->address = $q->address ? $q->address : "";
        $q->pricing_by = (@$q->pricing_by != null) ? $q->pricing_by : "";
        $q->price = (@$q->price != null) ? $q->price : "";

        $q['user_data'] = [
            'username' => @$user->username,
            'email' => @$user->email,
            'phone' => @$user->phone
        ];

        if ($q->status == 1) {
            $q->prices_count = Pricing::where('order_id', $q->id)->count();
        }

        if ($final_price = Pricing::where('order_id', $q->id)->where('is_payed', 1)->with(['company' => function ($query) {
            $query->select('id', 'name', 'address', 'phone');
        }])->first()) {
            $q['prices'] = $final_price;
        }


        if ($q->status == 0) {
            $q->time_left = (new Carbon(date('Y-m-d H:i:s')))->diff(new Carbon($q->created_at))->format('%h:%I:%s');
        }
        return $q;
    }


    public function orderBattery($id)
    {

        $orderBattery = Orderbattery::whereOrderId($id)->first();
        if ($orderBattery['battery']) {
            $battery = Battery::findOrFail($orderBattery['battery']['id']);
            $size = Size::where('type', 'batteries')->findOrFail($orderBattery['size_id']);
            return [
                'car_data' => [],
                'battery_type' => [
                    'id' => $battery->id,
                    'name' => $battery->name
                ],
                'battery_size' => [
                    'id' => $size->id,
                    'name' => $size->name
                ],
                'battery_image' => $battery->image
            ];
        }
    }

    public function orderCover($id)
    {

        $orderCover = Ordercover::whereOrderId($id)->first();

        if ($orderCover['cover']) {
            $cover = Cover::findOrFail($orderCover['cover']['id']);
            $size = Size::withTranslation()->where('type', 'covers')->whereId($orderCover['cover_size'])->first();
            $junt_size = Size::withTranslation()->where('type', 'jants')->whereId($orderCover['jant_size'])->first();


            return [
                'cover_type' => [
                    'id' => @$cover->id,
                    'name' => @$cover->name
                ],
                'cover_size' => [
                    'id' => @$size->id,
                    'name' => @$size->name
                ],
                'junt_size' => [
                    'id' => @$junt_size->id,
                    'name' => @$junt_size->name
                ],
                'cover_image' => $cover->image
            ];
        }
    }


    public function orderMaintenance($id)
    {
        $orderMaintenance = Ordermaintenance::whereOrderId($id)->first();
        if ($orderMaintenance['maintenance']) {
            $maintenance = Maintenance::findOrFail($orderMaintenance['maintenance']['id']);
            return [
                'damage_model' => [
                    'id' => $maintenance->id,
                    'name' => $maintenance->name
                ],
                'description' => $orderMaintenance['notes'],
                'damage_image' => $orderMaintenance['image_otl'],
                'date_of_end' => $orderMaintenance['date_of_end'],
            ];
        }
    }

    public function orderAccessory($id)
    {
        $orderAccessory = Orderaccessory::whereOrderId($id)->first();
        if ($orderAccessory) {
            $orderAccessoryParts = Spareparts::where('orderaccessories_id', $orderAccessory->id)->get();
            $arr = [];
            foreach ($orderAccessoryParts as $part) {
                $arr[] = [
                    'piece_name' => $part->name,
                    'piece_amount' => $part->amount,
                    'piece_image' => $part->image_piece,
                    'piece_type' => [
                        'id' => @$part->getPiece->id ? @$part->getPiece->id : 0,
                        'name' => @$part->getPiece->name ? @$part->getPiece->name : "",
                    ]
                ];
            }

            return $arr;
        }
    }


    public function getCarData($id)
    {


        $model = '';
        $order = Order::findOrFail($id);
        if ($order != false) {
            if (count($order->ordersBattery) > 0) {
                $model = Orderbattery::whereOrderId($id)->first();
            }
            if (count($order->ordersCover) > 0) {
                $model = Ordercover::whereOrderId($id)->first();
            }
            if (count($order->ordersMaintenance) > 0) {
                $model = Ordermaintenance::whereOrderId($id)->first();
            }

            if (count($order->ordersAccessory) > 0) {
                $model = Orderaccessory::whereOrderId($id)->first();
            }


            if ($model != '') {
                $data = [
                    'car_model' => [
                        'id' => $model['model']['id'],
                        'name' => $model['model']['name'],
                    ],
                    'car_brand' => [
                        'id' => $model['model']['brand']['id'],
                        'name' => $model['model']['brand']['name'],
                    ],
                    'year' => $model['year'],
                    'form_image' => $model['image']
                ];

                if (count($order->ordersAccessory) > 0) {
                    $data['vehicle_number'] = $model['vehicle_number'];
                }
                return $data;
            }
        }
    }


    public function getOrderBasic($id, $user)
    {

        $order = Order::findOrFail($id);

        if (count($order->ordersBattery) > 0) {
            $model = @Orderbattery::whereOrderId($id)->first();
        }
        if (count($order->ordersCover) > 0) {
            $model = @Ordercover::whereOrderId($id)->first();
        }
        if (count($order->ordersMaintenance) > 0) {
            $model = @Ordermaintenance::whereOrderId($id)->first();
        }

        if (count($order->ordersAccessory) > 0) {
            $model = @Orderaccessory::whereOrderId($id)->first();
        }

        $city = $order->city_id ? $order->city_id : $user->city_id;
        $city_data = City::find($city);
        $data = [
            'address_model' => [
                'lat' => $order['lat'] ? $order['lat'] : "",
                'lng' => $order['lng'] ? $order['lng'] : "",
                'address' => $order['address'] ? $order['address'] : ""
            ],
            'installation_price' => (@$model['work_price'] == 1) ? true : false,
            'other_city' => ($order->city_id) ? true : false,
            'city' => [
                'id' => @$city_data->id ? @$city_data->id : 0,
                'name' => @$city_data->name ? @$city_data->name : 0,
            ]
        ];


        return $data;
    }


    public function create_shipping($order)
    {

        $fields = array(
            "order" => [
                "comment" => "delivery instruction",
                "description" => "description instruction",
                "email" => "hassansaeed.es2015@gmail.com",

                "end_location" => [
                    "address" => $order['address'],
                    "address_2" => "",
                    "latitude" => $order["lat"],
                    "longitude" => $order["lng"]
                ],


                "name" => "hassansaeed",
                "pickup_date" => date("Y-m-d"),
                "pickup_hour_text" => date("H:i"),
                "phone" => "+966595838528",
                "recipient_name" => "Hassan Hassaan",
                "recipient_phone" => "+966595838528",
                "recipient_email" => "hassansaeed.es2015@gmail.com",
                "quantity" => "1",
                "payment_method" => "cash_on_recipient",
                "promotion_code" => "LOVEJAK",
                "requested_cab_types" => ["economy"],
                "requested_delivery_items" => ["medium_box"],

                "start_location" => [
                    "address" => "Al Mishal\nRiyadh",
                    "address_2" => "LandMark",
                    "latitude" => "24.601656800592714",
                    "longitude" => "46.89761023968458"
                ],
            ],

        );


        $data = json_encode($fields);

        $url = 'https://api.jakapp.co/corporate/shippings';

        $ch = curl_init($url);


        $time = floor(microtime(true));
        $auth = hash_hmac('sha256', $time, "06aab7f961903c20d5a826cba4f960e1");
        $headers = array(
            'Content-Type: application/json',
            "Authorization: $auth",
            'App-Key: 6d29-c954-32c0-2fe0',
            "App-Stamp: $time"

        );

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            $error_msg = curl_errno($ch);
        } else {
            if ($result) {
                $json = json_decode($result, true);
                return $json;
            }
        }
        curl_close($ch);
        if (isset($error_msg)) {
            return $error_msg;
        }
    }


    function fcmNotificationPublicNotifications($tokens_ios = [], $tokens_android = [], $type, $message = null, $description = null)
    {


        $optionBuilder = new OptionsBuilder();
        $optionBuilder->setTimeToLive(60 * 20);

        $notificationBuilder = new PayloadNotificationBuilder();
        $notificationBuilder->setBody($message)
            ->setSound('default');

        $dataBuilder = new PayloadDataBuilder();
        $dataBuilder->addData(['type' => -1, 'message' => $message, 'description' => $description]);

        $option = $optionBuilder->build();
        $notification = $notificationBuilder->build();
        $data = $dataBuilder->build();


        if (count($tokens_android) > 0) {
            $downstreamResponse = @FCM::sendTo($tokens_android, null, null, $data);
            $downstreamResponse->numberSuccess();
            $downstreamResponse->numberFailure();
            $downstreamResponse->numberModification();
        }

        //for IOS devices

        $optionBuilder = new OptionsBuilder();
        $optionBuilder->setTimeToLive(60 * 20);

        $notificationBuilder = new PayloadNotificationBuilder();
        $notificationBuilder->setBody($message)
            ->setSound('default');

        $dataBuilder = new PayloadDataBuilder();
        $dataBuilder->addData(['type' => -1, 'message' => $message, 'description' => $description]);

        $option = $optionBuilder->build();
        $notification = $notificationBuilder->build();
        $data = $dataBuilder->build();

        if (count($tokens_ios) > 0) {
            $downstreamResponse = @FCM::sendTo($tokens_ios, null, $notification, $data);
            $downstreamResponse->numberSuccess();
            $downstreamResponse->numberFailure();
            $downstreamResponse->numberModification();
        }
    }


    public function notificationTranslation($type, $lang, $sender)
    {
        $data = [];

        switch ($type) {
            case $type == 10:

                if ($lang == "ar") {
                    $data['title'] = "حوالة بنكية";
                    $data['body'] = "($sender) لديك حوالة بنكية من الشركة";
                } elseif ($lang == "en") {
                    $data['title'] = "Bank Transfer";
                    $data['body'] = "you have bank transfer from company ($sender)";
                } else {
                    $data['title'] = "Notification";
                    $data['body'] = "Have new Notification";
                }

                break;

            case $type == 9:

                if ($lang == "ar") {
                    $data['title'] = "مطالبة ماليه";
                    $data['body'] = "($sender) لديك مطالبة مالية من الشركة";
                } elseif ($lang == "en") {
                    $data['title'] = "Bank Transfer";
                    $data['body'] = "you have financial receivables from company ($sender)";
                } else {
                    $data['title'] = "Notification";
                    $data['body'] = "Have new Notification";
                }
                break;


            case $type == 11:

                if ($lang == "ar") {
                    $data['title'] = "رسالة جديدة";
                    $data['body'] = "($sender) لديك رسالة جديدة من ";
                } elseif ($lang == "en") {
                    $data['title'] = "New Message";
                    $data['body'] = "you have a new message from company ($sender)";
                } else {
                    $data['title'] = "Notification";
                    $data['body'] = "Have new Notification";
                }
                break;

            default:
                $data['title'] = "";
                $data['body'] = "";
        }
        return $data;
    }


    public function notificationTranslations($notify)
    {

        $orderId = $notify->order_id;

        $type = $notify->type;

        $title_ar = "";
        $title_en = "";
        $desc_ar = "";
        $desc_en = "";
        $icon = "";


        if ($type == -1) {
            $title_ar = $notify->message;
            $title_en = $notify->message;
            $desc_ar = $notify->description;
            $desc_en = $notify->description;
            $icon = "ionicons ion-flag";
        }


        if ($type == -2) {
            $title_ar = "رد تواصل معنا";
            $title_en = "Contact us reply";
            $desc_ar = "";
            $desc_en = "";
            $icon = "ionicons ion-email";
        }

        if ($type == 0) {
            $title_ar = "الطلب غير متوفر , جارى البحث فى مدينة أخرى";
            $title_en = "Your order is not Available ,  searching in other city";
            $desc_ar = "نعتذر منك , طلبك رقم #$orderId غير متوفر , جارى البحث عن تسعيرة فى مدينة آخري.";
            $desc_en = "Sorry, your order number #$orderId  is not available, searching in another city.";
            $icon = "ionicons ion-android-locate";
        }

        if ($type == 1) {

            $title_ar = "تم تسعير طلبك";
            $title_en = " your order is priced ";
            $desc_ar = "تم تسعير طلبك رقم #$orderId إضغط لعرض الأسعار.";
            $desc_en = "Your order #$orderId has been priced  Click to view prices.";
            $icon = "ionicons ion-social-usd";
        }

        if ($type == 2) {

            $title_ar = "طلبك غير متوفر";
            $title_en = "your order is not available ";
            $desc_ar = "نعتذر منك, طلبك رقم #$orderId غير متوفر";
            $desc_en = "Sorry , your order number #$orderId is not available ";
            $icon = "ionicons ion-android-close";
        }

        if ($type == 3) {

            $title_ar = "تم الدفع بنجاح";
            $title_en = "Payment completed";
            $desc_ar = "تم دفع تكلفة طلبك رقم #$orderId بنجاح.";
            $desc_en = " your order #$orderId is paid successfully ";
            $icon = "ionicons ion-cash";
        }
        if ($type == 5) {

            $title_ar = "طلبك غير مكتمل";
            $title_en = "Your order is not completed";
            $desc_ar = "طلبك رقم #$orderId غير مكتمل , الرجاء إدخال بيانات الطلب بشكل صحيح.";
            $desc_en = " your order number #$orderId is not completed , Please enter your data in successfull way ";
            $icon = "ionicons ion-pie-graph";
        }
        if ($type == 6) {

            $title_ar = "تم شحن الطلب بنجاح";
            $title_en = "your order arrived successfully";
            $desc_ar = "تم توصيل طلبك رقم #$orderId بنجاح , إضغط لتقييم الخدمة.";
            $desc_en = " your order number #$orderId is shipped successfully , click to rate the Service";
            $icon = "ionicons ion-android-car";
        }

        if ($type == 8) {

            $title_ar = "فاتورتك جاهزة";
            $title_en = "your bill is ready";
            $desc_ar = "فاتورة طلب رقم #$orderId جاهزة للعرض , شكراً لإستخدامك تطبيق أطلبها.";
            $desc_en = " the bill of order number #$orderId is ready to view , thanks to use Atlobha app ";
            $icon = "ionicons ion-android-document";
        }


        $data = [
            'message_ar' => $title_ar,
            "description_ar" => $desc_ar,

            "message_en" => $title_en,
            "description_en" => $desc_en,
            "icon" => $icon
        ];

        return $data;
    }


    public function insertData($model = null, $data)
    {
        if (count($data) > 0) {
            $model::insert($data);
        }
    }


    public function daysName($num)
    {


        switch ($num) {
            case $num == "Sat":
                $day = __('trans.Sat');
                break;

            case $num == "Sun":
                $day = __('trans.Sun');
                break;

            case $num == "Mon":
                $day = __('trans.Mon');
                break;

            case $num == "Tue":
                $day = __('trans.Tue');
                break;

            case $num == "Wed":
                $day = __('trans.Won');
                break;

            case $num == "Thu":
                $day = __('trans.Thu');
                break;
            case $num == "Fri":

                $day = __('trans.Fri');
                break;

            default:
                $day = __('');
        }


        return $day;
    }


    public function convertDateStringToDateFormat($string)
    {
        $d = explode(' ', $string);
        $date = date('Y-m-d', strtotime("$d[3]-$d[2]-$d[1]"));
        $ee = Carbon::parse($date);
        return $ee;
    }


    public function getSubscriptionDay($day)
    {


        switch ($day) {
            case $day->format('D') == "Sat":
                $startSubscription = $day->addDay(3);
                break;

            case $day->format('D') == "Sun":
                $startSubscription = $day->addDay(2);
                break;

            case $day->format('D') == "Mon":
                $startSubscription = $day->addDay(1);
                break;

            case $day->format('D') == "Tue":
                $startSubscription = $day->addDay(4);
                break;

            case $day->format('D') == "Wed":
                $startSubscription = $day->addDay(3);
                break;

            case $day->format('D') == "Thu":
                $startSubscription = $day->addDay(2);
                break;

            case $day->format('D') == "Fri":
                $startSubscription = $day->addDay(1);
                break;
        }
        return $startSubscription;
    }


    public function getWeekSubscription($startDay)
    {


        $startWeek2 = $this->getSubscriptionDay($startDay);

        $endWeek2 = $this->getSubscriptionDay($startDay)->addDays(6);

        $datesWeek2 = $this->dateRange($startWeek2, $endWeek2);


        return $datesWeek2;
    }

    function dateRange($first, $last, $step = '+1 day', $format = 'D d m Y')
    {
        $dates = [];
        $current = strtotime($first);
        $last = strtotime($last);

        while ($current <= $last) {

            $dates[] = date($format, $current);
            $current = strtotime($step, $current);
        }

        return $dates;
    }


    public function getAllCaloresForDay($productId, $date, $calory)
    {
        $data = Meal::whereProductId($productId)->where('sub_date', $date)->whereHas('subscription', function ($obj) use ($calory) {
            $obj->where('calory', $calory);
        })->get();
        return count($data);
    }


    public function getProductsForSubscription($userId, $subId, $date)
    {


        $meals = Meal::where(['user_id' => $userId, 'subscription_id' => $subId, 'sub_date' => $date])->get();
        return $meals;
    }


    public function manageSubscriptions()
    {

        $changes = DB::table('subchanges')->get();

        foreach ($changes as $change) {

            if (date('Y-m-d') == date('Y-m-d', strtotime($change->cancel_date)) || date('Y-m-d') > date('Y-m-d', strtotime($change->cancel_date))) {
                $this->cancelSub($change);
            }
        }
    }


    public function cancelSub($change)
    {
        $sub = Sub::whereId($change->old_id)->first();
        if (!$sub)
            return;
        $sub->is_active = 0;
        if ($sub->save()) {
            return $this->createNewSubscription($change);
        }
    }


    public function createNewSubscription($change)
    {

        $sub = new Sub();

        $sub->subscription_id = $change->new_id;
        $sub->is_active = 1;
        $sub->user_id = auth()->id();
        $sub->setCreatedAt(Carbon::parse($change->cancel_date)->addHours(48));

        $sub->save();
    }


    public function paymentAction($amount, $userId, $succes_page = '', $error_page = '', $currency = "USD", $display_text = 'balance', $language = 'en', $name = '', $mobile = '', $email = '', $additions)
    {
        ini_set("soap.wsdl_cache_enabled", "0");
        ini_set('customer_agent', 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:20.0) Gecko/20100101 Firefox/20.0');
        //The above line will add the User-Agent to the header of your request, and the soap library 'SoapClient' will add the Host header name automatically.
        $merchant_id = "business_in_map";
        $encryption_key = "moh.fahem";
        // This value will be posted from the above HTML page
        $session_id = time();

        $testmode = 0;
        // The below Parameters are not required, especially in the default Merchant Checkout Page:
        $txt1 = $userId;
        $txt2 = $amount;
        $txt3 = $additions['actionType'] != 'recharge' ? $additions['codeType'] . "--" . $additions['code'] : "";
        $txt4 = $additions['actionType'];
        $txt5 = $additions['actionType'] != 'recharge' ? $additions['duration'] . '--' . $additions['categoryId'] : "";
        $service_name = "";


        // If using Enhanced Encryption:
        //$token = md5(strtolower($merchant_id) . ':' . $amount . ':' . strtolower($currency) . ':' . strtolower($session_id) . ':' . $encryption_key);

        // If using Default Encryption:
        $token = md5(strtolower($merchant_id) . ':' . $amount . ':' . strtolower($currency) . ':' . $encryption_key);


        //        if($this->sandbox())
        //            $client = "https://sandbox.cashu.com/secure/payment.wsdl";
        //        else

        $client = "https://secure.cashu.com/payment.wsdl";


        $client = new SoapClient($client, array('trace' => true));


        $request = $client->DoPaymentRequest($merchant_id, $token, $display_text, $currency, $amount, $language, $session_id, $txt1, $txt2, $txt3, $txt4, $txt5, $testmode, $service_name);


        $tmp = strstr($request, '=');
        $Transaction_Code = substr($tmp, 1);

        return response()->json([
            'status' => 200,
            'message' => "Success",
            'paymentUrl' => route('cashu.redirect.payment', $Transaction_Code)
        ]);
    }

    public function signRequest($charge)
    {
        // dd($charge);
        $secKey = "f784b6bd-1093-45a9-a4d3-8f91a3ec176e";
        $signString = $charge['merchantCode'] . $charge['merchantRefNum'];
        $signString .= $charge['customerProfileId'] ?? '';
        $signString .= $charge['returnUrl'] ?? '';
        $signString .= $charge['chargeItems'][0]['itemId'] . '';
        $signString .= $charge['chargeItems'][0]['quantity'] . '';
        $signString .= number_format($charge['chargeItems'][0]['price'], 2);
        $signString .= $secKey;
        return hash('sha256', $signString);
    }
    public function fawryPayment($amount, $paymentID, $success_page = '', $error_page = '', $currency = 'USD', $display_text = 'balance', $language = 'en', $name = 'Hassan', $mobile = '01000265675', $email = '', $userRealId = '')
    {
        $url = 'https://atfawry.com/fawrypay-api/api/payments/init';
        //$url = 'https://atfawry.fawrystaging.com/fawrypay-api/api/payments/init';
        //$merchantRefNum = uniqid() . uniqid() . uniqid();
        $merchantRefNum = $paymentID;
        $itemID = uniqid();
        $hash = '';
        $chargeRequest = [
            "merchantCode" => "400000016550",
            "merchantRefNum" => $merchantRefNum,
            "customerMobile" => $mobile,
            "customerEmail" => $email,
            "customerProfileId" => $userRealId,
            "customerName" => "",
            "chargeItems" => [
                [
                    "itemId" => $itemID,
                    "description" => "Bim Balance",
                    "price" => number_format($amount, 2),
                    "quantity" => 1,
                ]
            ],
            "returnUrl" => "https://businessinmap.com/testing/api/v1/fawry-success-payment",
            "authCaptureModePayment" => false            
        ];

        $chargeRequest['signature'] = $this->signRequest($chargeRequest);
        // $final = '{"merchantCode":"siYxylRjSPzwey/eiO8sMw==","merchantRefNum":'.$merchantRefNum.',"customerMobile":"01092929292","customerEmail":"a@example.com","customerProfileId ":"123","customerName":"","chargeItems":[{"itemId":'.$itemID.',"description":"Bim Balance","price":80.00,"quantity":1}],"returnUrl":"https://www.google.com","authCaptureModePayment":false,"secKey":"0aacb642-2a17-42bd-a573-4dfdeed6dd97","signature":'.$this->signRequest($chargeRequest).'}';
        // $url = 'https://atfawry.com/ECommercePlugin/FawryPay.jsp';

        // $amount = number_format($amount, 2);

        // $merchantRefNum = $userId;

        // $hash = hash('sha256', "siYxylRjSPzwey/eiO8sMw==" . $merchantRefNum . $userId . '111' . '1' . $amount . '48' . "0aacb642-2a17-42bd-a573-4dfdeed6dd97");

        // $chargeRequest = '{ "language":"ar-eg", "merchantCode":"siYxylRjSPzwey/eiO8sMw==", "merchantRefNumber":"' . $merchantRefNum . '", "customer":{ "name":"' . $name . '", "mobile":"' . $mobile . '", "email":"' . $email . '", "customerProfileId":"' . $userId . '" }, "order":{ "description":"BIM balance", "expiry":"48", "orderItems":[ { "productSKU":"111", "description":"BIM Balance", "price":"' . $amount . '", "quantity":"1", "width":"", "height":"", "length":"", "weight":"" } ] }, "signature":"' . $hash . '"}';

        return [
            'url' => $url,
            'chargeRequest' => $chargeRequest
        ];
    }


    /**
     * @param User $user
     * @return int
     * @ this funtion calc available balance of user inside app.
     */
    function calculateUserBalance(User $user)
    {
        return (int)$user->transactions->where('status', 'deposit')->sum('price') - (int)$user->transactions->where('status', 'withdrawal')->sum('price');
    }


    /**
     * @param User $user
     * @return int
     * @ this funtion calc available balance of user inside app.
     */
    function calculateUserBalanceType(User $user, $type = 'deposit')
    {
        return (int)$user->transactions->where('status', $type)->sum('price');
    }


    /**
     * @param $userId
     * @return string
     * @ generate new User Id With random int.
     */
    public function addRandomDigitsToUserId($userId)
    {
        $randNo = rand(1000, 9999);
        return ($randNo . $userId);
    }

    /**
     * @param $userId
     * @return string
     */
    public function removeRandomDigitsToUserId($userId)
    {
        // remove first 4 digits from user id;
        return substr($userId, 4);
    }


    public function checkFawryOrders($userId)
    {
        // dd('ji');

        $payments = \App\Models\Payment::whereNull('paid_at')->where('payment_type', 'PAYATFAWRY')->whereUserId($userId)->get();


        if ($payments->count() > 0) :
            foreach ($payments as $payment) :
                $hash = hash('sha256', "siYxylRjSPzwey/eiO8sMw==" . $payment->id . "0aacb642-2a17-42bd-a573-4dfdeed6dd97");

                $ch = curl_init("https://atfawry.com/ECommerceWeb/Fawry/payments/status/v2?merchantCode=siYxylRjSPzwey/eiO8sMw==&merchantRefNumber=$payment->id&signature=$hash");
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=UTF-8'));
                $result = curl_exec($ch);
                $serverData = json_decode($result, true);

                // return response()->json([
                //     'server' => $serverData,
                //     'payment' => $payment
                // ]);

                if (!empty($serverData['paymentMethod']) && $serverData['paymentMethod'] == "PAYATFAWRY" && $serverData['orderStatus'] == "PAID") {
                    $avPayments[] = $serverData;

                    if ($payment->operation_type == "recharge") {
                        $transactionData = [
                            'status' => 'deposit',
                            'price' => $serverData['paymentAmount'],
                            'operation' => 'recharge',
                            'notes' => 'Charge Account by Fawry (ATFAWRY).',
                            'target_id' => null
                        ];

                        $transaction = $payment->user->transactions()->create($transactionData);
                        // $payment->update(['operation_id' => $transaction->id, 'paid_at' => Carbon::parse($serverData['paymentDate'])]);
                        $payment->update(['operation_id' => $transaction->id, 'paid_at' => Carbon::now()]);
                        return response()->json(['status' => 200, 'message' => "Transaction paid success."]);
                    } else {
                        $currentSubscription = Subscription::whereId($payment->operation_id)->first();
                        $month = 0;
                        if ($subscription = $payment->user->subscriptions->where('is_active', 1)->first()) {
                            $month = Carbon::parse($subscription->finished_at)->format('m') - Carbon::now()->format('m');
                            $subscription->update(['is_active' => 0]);
                        }

                        //                        $freeMonths = 0;
                        //                        if ($currentSubscription->code_type != "" && $currentSubscription->code_type == 'profileCode')
                        //                            $freeMonths = giftsAndMonthsAfterRegistration($currentSubscription->code, $payment->user->id, $currentSubscription->duration);
                        //                        $totalMonths = $freeMonths + $month;

                        if ($currentSubscription->code_type != "" && $currentSubscription->code_type == 'profileCode') {

                            $setting = new \App\Models\Setting;

                            $ownerCode = \App\Models\User::whereCode($currentSubscription->coupon_id)->first();

                            if ($ownerCode && $currentSubscription->user->code != $currentSubscription->coupon_id) {
                                $cost = optional($currentSubscription->user->category)->parent->per_month;
                                if ($currentSubscription->duration >= 12)
                                    $cost = optional($currentSubscription->user->category)->parent->per_year;

                                $commissionMonths = $setting->getBody('commission_months');

                                if ($ownerCode->gifts != null)
                                    $commissionMonths = $ownerCode->gifts->commission_months;


                                $costPerMonth = $cost;

                                if ($currentSubscription->duration >= 12)
                                    $costPerMonth = $cost / 12;

                                $ownerCodeCommission = $costPerMonth * $commissionMonths * ($currentSubscription->duration / 12);

                                $dataOwner = array(
                                    'status' => 'deposit',
                                    'price' => sprintf("%.2f", $ownerCodeCommission),
                                    'operation' => 'award',
                                    'notes' => 'From Subscription By Code Profile - ' . $currentSubscription->user->code,
                                    'target_id' => $currentSubscription->user_id
                                );
                                $ownerCode->transactions()->create($dataOwner);
                            }


                            $currentSubscription->finished_at = Carbon::now()->addMonths($currentSubscription->duration + $month)->toDateTimeString();
                            $currentSubscription->is_active = 1;

                            if ($currentSubscription->save()) {
                                $payment->update(['paid_at' => Carbon::now()]);
                                return "Saved";
                            }
                        }
                    }
                }

            endforeach;
        endif;

        //        return $avPayments;

    }
}
