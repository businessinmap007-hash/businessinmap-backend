<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Orderreceivable;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Images;
use Validator;
use App\Models\Notification;
use Carbon\Carbon;
use App\Libraries\PushNotification;


class FinancialController extends Controller
{

    public $public_path;
      public $push;
    public function __construct(Request $request,PushNotification $push)
    {
          $language = $request->headers->get('lang') ? $request->headers->get('lang') : 'ar';
        app()->setLocale($language);
        $this->public_path = 'files/transfers/';
         $this->push = $push;
    }
    
  

    


    public function financialDuesApp(Request $request)
    {
        $token = ltrim($request->headers->get('Authorization'), "Bearer ");

        $company = User::whereApiToken($token)->first();

        $validator = Validator::make($request->all(), [
            'account' => 'required',
            'ibanNumber' => 'required',
            'senderAccountNumber' => 'required',
            'amount' => 'required',
            'senderName' => 'required',
            'image' => 'required',
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


        $model = new Transfer;

        $model->user_id = $company->id;
        $model->account = $request->account;
        $model->iban_number = $request->ibanNumber;
        $model->sender_name = $request->senderName;
        $model->sender_account_number = $request->senderAccountNumber;
        $model->amount = $request->amount;

        if ($request->hasFile('image')):
            $model->image = $request->root() . '/public/' . $this->public_path . UploadImage::uploadImage($request, 'image', $this->public_path);
        endif;


        if ($model->save()) {
            
            
                   
           $users = User::whereHas('roles')->whereIsUser(0)->get();
           
            $devices = [];
           
            foreach($users as $user){
             $notification = new Notification;
            
            $notification->user_id = $user->id;
            $notification->title = "حوالة بنكية";
            $notification->body = "لديك حوالة بنكية ";
            $notification->order_id = null;
            $notification->type = 10;
            $notification->sender_id = $company->id;
            
            $notification->created_at = Carbon::now();
            $notification->updated_at = Carbon::now();
            
            $notification->save();
            
           foreach($user->devices as $device){
               $devices[] = $device;
           } 
            }
            
             $webDevices =  collect($devices)->where('device_type', 'web')->pluck('device');
           
           
           
           
           $title = "";
           $body = "";
           
           if(config('app.locale') == "ar"){
               $title = "حوالة بنكية";
               $body = "لديك حوالة بنكية  ";
               
           }elseif(config('app.locale') == "en"){
                $title= "New Bank Transfer";
                 $body = "You have a new bank transfer ";
           }
           
           
             $data = array(
                'title' =>"حوالة بنكية",
                'body' =>"لديك حوالة بنكية  ",
                'type' => 9,
                'href' => url('/'.config('app.locale')."/administrator/company/bank/transfer/$model->id/details"),
                "image" => "http://bdfjade.com/data/out/111/6150983-amazing-pic.jpg"
            );
            
            
            $this->push->sendPushNotification([], $webDevices, $data['title'], $data['body'], $data);


            $this->makeTransaction($company->id, 3, $request);
            return response()->json([
                'status' => 200,
                'message' => "لقد تم إرسال الحوالة لإدارة التطبيق بنجاح.",
                'data' => $model
            ]);
        }

    }


    public function financialDuesCompany(Request $request)
    {



        $token = ltrim($request->headers->get('Authorization'), "Bearer ");
        $company = User::whereApiToken($token)->first();


        $validator = Validator::make($request->all(), [
            'amount' => 'required',
            'note' => 'required',
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

        $item = new Orderreceivable();

        $item->amount = $request->amount;
        $item->note = $request->note;
        $item->company_id = $company->id;

        if ($item->save()) {
            
            
            
            
            
            $users = User::whereHas('roles')->whereIsUser(0)->get();
           
            $devices = [];
           
            foreach($users as $user){
             $notification = new Notification;
            
            $notification->user_id = $user->id;
            $notification->title = "مطالبة مالية";
            $notification->body = "لديك مطالبة مالية ";
            $notification->order_id = null;
            $notification->type = 10;
            $notification->sender_id = $company->id;
            
            $notification->created_at = Carbon::now();
            $notification->updated_at = Carbon::now();
            
            $notification->save();
            
           foreach($user->devices as $device){
               $devices[] = $device;
           } 
            }
            
             $webDevices =  collect($devices)->where('device_type', 'web')->pluck('device');
           
           
           
           
           $title = "";
           $body = "";
           
           if(config('app.locale') == "ar"){
               $title = "مطالبة مالية";
               $body = "لديك مطالبة مالية  ";
               
           }elseif(config('app.locale') == "en"){
                $title= "New Bank Transfer";
                 $body = "You have a new bank transfer ";
           }
           
           
             $data = array(
                'title' =>" مطالبة مالية",
                'body' =>"لديك مطالبة مالية   ",
                'type' => 9,
                'href' => url('/'.config('app.locale')."/administrator/financial/dues/company/$item->id/details"),
                "image" => "http://bdfjade.com/data/out/111/6150983-amazing-pic.jpg"
            );
            
            
            $this->push->sendPushNotification([], $webDevices, $data['title'], $data['body'], $data);
            
            
            return response()->json([
                'status' => 200,
                'message' => "لقد تم إرسال الرسالة بنجاح.",
                'data' => $item
            ]);
        }


    }


    public function makeTransaction($companyId, $transactionType, $request = null)
    {
        $transaction = new  Transaction();
        $company = User::where('is_user', 1)->whereId($companyId)->first();
        $transaction->company_id = $company->id;
        $transaction->type = $transactionType;
        $transaction->amount = ($request->amount);
        $transaction->save();
    }


}
