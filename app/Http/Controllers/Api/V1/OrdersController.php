<?php

namespace App\Http\Controllers\Api\V1;

use App\Brand;
use App\Cover;
use App\Carmodel;
use App\Libraries\PushNotification;
use App\Models\Notification;
use App\Device;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Size;
use App\Notifications;
use App\Orderaccessory;
use App\Models\Transaction;
use App\Orderbattery;
use App\Ordercover;
use App\Models\Setting;
use App\Pricing;
use App\Spareparts;
use App\Models\User;
use App\Year;
use App\Maintenance;
use App\Models\Order;
use App\OrderTranslation;
use App\Ordermaintenance;
use App\OrdermaintenanceTranslation;
use Carbon\Carbon;
use App\Company;
use Validator;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Images;
use App\Http\Helpers\Main;
use LaravelFCM\Message\OptionsBuilder;
use LaravelFCM\Message\PayloadDataBuilder;
use LaravelFCM\Message\PayloadNotificationBuilder;
use FCM;

class OrdersController extends Controller
{

    // public $public_path;

    // public function __construct(Request $request)
    // {
    //     $this->public_path = 'files/orders/payments/';
    //     $language = $request->headers->get('lang') ? $request->headers->get('lang') : 'ar';
    //     app()->setLocale($language);
    // }
    
    public $public_path;
    public $main;
    public $push;

    public function __construct(Request $request, \App\Libraries\Main $main, PushNotification $push)
    {
        $this->public_path = 'files/orders/payments/';
        $language = $request->headers->get('lang') ? $request->headers->get('lang') : 'ar';
        app()->setLocale($language);

        $this->main = $main;
        $this->push = $push;

    }


    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */


    /**
     * @return string
     */
     
     
     
     
     public function store(Request $request)
    {


        $token = ltrim($request->headers->get('Authorization'), "Bearer ");
        if (!$token) {
            $token = $request->api_token;
        }

        $station = User::whereApiToken($token)->first();
        $company = User::whereId($request->companyId)->first();
        
        

        $station = $station->clientToArray();

        $product = Product::find($request->productId);
        $size = Size::find($request->sizeId);


        $order = new \App\Models\Order();
        $order->user_id = $station['id'];
        $order->company_id = $request->companyId;
        $order->product_id = $request->productId;
        $order->size_id = $request->sizeId;
        $order->price = $request->totalPrice;
        
        $order->total_product_price = $request->productPrice;
        $order->transport_cost = $request->transportCost;
        $order->taxes = $request->taxes;
        $order->app_percentage = $request->appPercentage;
        $order->payment_type = $request->paymentType;

        if ($request->has('latitute'))
            $order->latitute = $request->latitute;

        if ($request->has('longitute'))
            $order->longitute = $request->longitute;

        if ($request->has('address'))
            $order->address = $request->address;


        if ($order->save()) {


            $data = array(
                "user_id" => $company['id'],
                'title' => $company['lang'] == "ar" ? "طلب جديد" : "New Order",
                'body' => $company['lang'] == "ar" ? "لديك طلب جديد #$order->id" : "You have a new order #$order->id",
                'order_id' => $order->id,
                'type' => 1,
                'sender_id' => $station['id'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            );

            $this->main->insertData(Notification::class, $data);

            $data['href'] = "";
            $data['order'] = $order;
            $data['title_key'] = "titleNewOrder";
            $data['body_key'] = "bodyNewOrder";
            
             
           
            $companyDevicesIos =  $company->devices()->where('device_type', 'ios')->pluck('device');
            $companyDevicesAndroid =  $company->devices()->where('device_type', 'android')->pluck('device');
    
        
          $this->push->sendPushNotification($companyDevicesAndroid, $companyDevicesIos, $data['title'], $data['body'], $data);
          
        
          

            if ($request->paymentType == 1) {
                
                $payment = new Payment();
                
                $payment->payment_type = 'online_payment';
                $payment->amount = $request->totalPrice;
                $payment->completed_at = Carbon::now();
                $payment->transaction_id = $request->transaction_id;
                
                if ($order->payment()->save($payment)) {

                    $this->makeTransactionOptimization($station['id'], $order->id, 1, $request);

                }
                
                
            } elseif ($request->paymentType == 2) {

                $payment = new Payment();

                $payment->bank_id = $request->bankId;
                $payment->payment_type = 'bank_transfer';
                $payment->sender_name = $request->senderName;
                $payment->amount = $request->totalPrice;
                $payment->account_number = $request->accountNumber;
                $payment->iban_number = $request->ibanNumber;
                $payment->completed_at = Carbon::now();

                if ($request->hasFile('image')):
                    $payment->image = request()->root() . '/public/' . $this->public_path . UploadImage::uploadImage($request, 'image', $this->public_path);
                endif;

                if ($order->payment()->save($payment)) {
                    
                     $this->makeTransactionOptimization($station['id'], $order->id, 1, $request);
                }
                
                
           


            } else {
                $order->payment_type = $request->paymentType;
                $this->makeTransactionOptimization($station['id'], $order->id, 2, $request);
            }


            // return response
            return response()->json([
                'status' => 200
            ], 200);



        } else {
            return response()->json([
                'status' => 400
            ], 400);
        }


    }
    
    
    
    // public function store(Request $request)
    // {

    //     $token = ltrim($request->headers->get('Authorization'), "Bearer ");
    //     if (!$token) {
    //         $token = $request->api_token;
    //     }

    //     $station = User::whereApiToken($token)->first();

    //     $station = $station->clientToArray();


    //     $product = Product::find($request->productId);
    //     $size = Size::find($request->sizeId);


    //     $order = new \App\Models\Order();
    //     $order->user_id = $station['id'];
    //     $order->company_id = $request->companyId;
    //     $order->product_id = $request->productId;
    //     $order->size_id = $request->sizeId;
    //     $order->price = $request->totalPrice;


    //     $order->payment_type = $request->paymentType;


    //     if ($request->has('latitute'))
    //         $order->latitute = $request->latitute;

    //     if ($request->has('longitute'))
    //         $order->longitute = $request->longitute;

    //     if ($request->has('address'))
    //         $order->address = $request->address;


    //     if ($order->save()) {

    //         if ($request->paymentType == 1) {

    //         } elseif ($request->paymentType == 2) {

    //             $payment = new Payment();

    //             $payment->bank_id = $request->bankId;
    //             $payment->payment_type = 'bank_transfer';
    //             $payment->sender_name = $request->senderName;
    //             $payment->amount = $request->totalPrice;
    //             $payment->account_number = $request->accountNumber;
    //             $payment->iban_number = $request->ibanNumber;
    //             $payment->completed_at = Carbon::now();

    //             if ($request->hasFile('image')):
    //                 $payment->image = request()->root() . '/public/' . $this->public_path . UploadImage::uploadImage($request, 'image', $this->public_path);
    //             endif;


    //             $order->payment()->save($payment);

    //         } else {
    //             $order->payment_type = $request->paymentType;
    //         }


    //         return response()->json([
    //             'status' => 200
    //         ], 200);
    //     } else {
    //         return response()->json([
    //             'status' => 400
    //         ], 400);
    //     }


    // }


    public function getOrderDetails(Request $request)
    {


        $token = ltrim($request->headers->get('Authorization'), "Bearer ");

        $user = User::whereApiToken($token)->first();

        $userType = $user->userType();

        switch ($userType) {

            case $userType == 'company':
                $order = Order::findOrFail($request->orderId);
                break;

            case $userType == 'client':
                $order = Order::findOrFail($request->orderId);
                break;

            case $userType == 'driver':
                $order = Order::findOrFail($request->orderId);
                break;


            default:
                $order = response()->json(['status' => 400, 'message' => 'عفواً, لقد حدث خطأ لعدم معرفة نوع المستخدم']);
                break;


        }

        return response()->json(['status' => 200, 'message' => 'Order Details', 'data' => $order]);


        if (!$order) {
            return response()->json(['status' => 400, 'message' => 'هذا الطلب غير موجود']);
        }


        // if($user->userType() == 'company'){
        //     $query->whereCompanyId($user->id);
        // }

        // if($user->userType() == 'client'){
        //     $query->whereUserId($user->id);
        // }

        // if($user->userType() == 'driver'){
        //     $query->with('track')->whereHas('track', function($obj) use ($user){
        //       return  $obj->where('driver_id', $user->id);
        //     });
        // }


    }


    public function getOrdersForUser(Request $request)
    {
        $token = ltrim($request->headers->get('Authorization'), "Bearer ");

        $user = User::whereApiToken($token)->first();


        if (isset($request->pageSize)):
            $pageSize = $request->pageSize;
        else:
            $pageSize = 10;
        endif;

        $skipCount = $request->skipCount;

        $currentPage = $request->get('page', 1); // Default to 1

        $query = Order::where('status', '!=', -1);

        if ($user->userType() == 'company') {
            $query->whereCompanyId($user->id);
        }

        if ($user->userType() == 'client') {
            $query->whereUserId($user->id);
        }

        if ($user->userType() == 'driver') {
            
            // $tracks = \App\Models\Track::whereDriverId($user->id)->get();
            
            // $ordersIds =  $tracks->pluck('order_id');
            
            // $orders = Order::whereIn('id', $ordersIds)->get();

            
            $query->whereHas('track', function ($obj) use ($user) {
              return $obj->where('driver_id', $user->id);
            })->get();
            
            
            
            // collect($query)->where('driverId', $user->id);
            $query->where('status', '!=', 0);
            
        
        }


        /**
         * @ If item Id Exists skipping by it.
         */

        $query->skip($skipCount + (($currentPage - 1) * $pageSize));

        $query->take($pageSize);

        /**
         * @ Get All Data Array
         */

        // $query->get()->map(function($obj){
        //     $obj->convId = "40000";
        // });

        // Get Orders List Using Skip count pagination.
        $orders = $query->get()->toArray();
        
       
        


        // Filter orders list to remove null or empty string.
        $orders = array_filter($orders, function ($value) {
            return $value !== "" && !is_null($value);
        });
        
        
       


        return response()->json([
            'status' => 200,
            "message" => "Orders list for company" . $user->name,
            "data" => $orders
        ]);
    }


    
    
    
     public function makeTransactionOptimization($stationId = null, $orderId, $transactionType, $request = null)
    {
        
        
        $transaction = new  Transaction();
        
        $company = User::where('is_user', 1)->whereId($request->companyId)->first();
        $station = User::where('is_user', 3)->whereId($stationId)->first();
        
        $order = Order::whereId($orderId)->first();
        
   
        $transaction->company_id = $company->id;
        $transaction->type = $transactionType;

        // $branchLat = $company->branch->latitute;
        // $branchLng = $company->branch->longitute;

        // $stationLat = $station['latitute'];
        // $stationLng = $station['longitute'];
        

        // $distence = ($station && $this->getRealDistance("$stationLat,$stationLng", "$branchLat,$branchLng")) ? $this->getRealDistance("$stationLat,$stationLng", "$branchLat,$branchLng") : $this->distance($stationLat, $stationLng, $branchLat, $branchLng, "K");
        // GET TRANSPORT PRICE
        // if ($station['city']['id'] == $company->branch->city_id) {
        //     $priceCost = ($company->transport_price_in * $distence) * 2;
        // } else {
        //     $priceCost = ($company->transport_price_out * $distence) * 2;
        // }
        // APP PERCENTAGE PRICE
        // $total = ($priceCost / 100) * 10;

        switch ($transactionType) {

            case $transactionType == 1:

                $transaction->amount =  $order->price - $order->app_percentage;

                break;

            case $transactionType == 2:

                $transaction->amount =  $order->app_percentage;

                break;

            default:
                return null;
        }

        $transaction->order_id = $orderId;
        $transaction->save();
    }
    
    


    // public function makeTransaction($stationId = null, $orderId = null, $transactionType, $request = null)
    // {
    //     $transaction = new  Transaction();
        

    //     $company = User::where('is_user', 1)->whereId($request->companyId)->first();
    //     $station = User::where('is_user', 3)->whereId($stationId)->first();
        
   
    //     $transaction->company_id = $company->id;
    //     $transaction->type = $transactionType;

    //     $branchLat = $company->branch->latitute;
    //     $branchLng = $company->branch->longitute;

    //     $stationLat = $station['latitute'];
    //     $stationLng = $station['longitute'];
        

    //     $distence = ($station && $this->getRealDistance("$stationLat,$stationLng", "$branchLat,$branchLng")) ? $this->getRealDistance("$stationLat,$stationLng", "$branchLat,$branchLng") : $this->distance($stationLat, $stationLng, $branchLat, $branchLng, "K");
    //     // GET TRANSPORT PRICE
    //     if ($station['city']['id'] == $company->branch->city_id) {
    //         $priceCost = ($company->transport_price_in * $distence) * 2;
    //     } else {
    //         $priceCost = ($company->transport_price_out * $distence) * 2;
    //     }
    //     // APP PERCENTAGE PRICE
    //     $total = ($priceCost / 100) * 10;

    //     switch ($transactionType) {

    //         case $transactionType == 1:

    //             $transaction->amount =  $request->totalPrice - $total;

    //             break;

    //         case $transactionType == 2:

    //             $transaction->amount =  $total;

    //             break;

    //         default:
    //             return null;
    //     }

    //     $transaction->order_id = $orderId;
    //     $transaction->save();
    // }
    
    
    
    
    
        public function orderCalculation(Request $request){
    
    
        $api_token = ltrim($request->headers->get('Authorization'), "Bearer ");
        $user = User::whereApiToken($api_token)->first();
        
        $total =  $user->orders()->pluck('price')->sum();
        
        
        
        $cash = $user->orders()->where('payment_type', 0)->pluck('price')->sum();
        
        
        $online = $user->orders()->where('payment_type', 1)->pluck('price')->sum();
        
        $bankTransfer = $user->orders()->where('payment_type', 2)->pluck('price')->sum();
        
        
        
      return response()->json([
          
          'status' => 200,
          'data' => [
              

            'total' =>  $total,
            
            'cash'=>$cash,
            'cashPercentage' => $total > 0 ? (int) number_format((100 * $cash) / $total, 0): 0,
            
            'online'=>$online,
            'onlinePercentage' => $total > 0 ? (int) number_format((100 * $online) / $total, 0) : 0,
        
            'bank'=> $bankTransfer,
            'bankPercentage' => $total > 0 ? (int) number_format((100 * $bankTransfer) / $total, 0) : 0,
              
              ]
          
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
