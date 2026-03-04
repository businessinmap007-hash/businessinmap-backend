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
use App\Libraries\Main;


class FinancialController extends Controller
{

    public $public_path;
    public $push;
    public $main;

    public function __construct(Request $request, PushNotification $push, Main $main)
    {
        $language = $request->headers->get('lang') ? $request->headers->get('lang') : 'ar';
        app()->setLocale($language);
        $this->public_path = 'files/transfers/';
        $this->push = $push;
        $this->main= $main;
    }


    public function financialDuesApp(Request $request)
    {
        $token = ltrim($request->headers->get('Authorization'), "Bearer ");

        $company = User::whereApiToken($token)->first();
        
        
        
        $transaction =  $company->companyUserToArray();
        
        
        if($transaction['transactions']['appDues'] <= 0){
            
             return response()->json(
                [
                    'status' => 400,
                    'message' => trans('trans.noReservedDuesApp'),
                ]
            );
            
        }
        
        
         if($transaction['transactions']['appDues'] > 0 && $request->amount){
            
             return response()->json(
                [
                    'status' => 400,
                    'message' => trans('trans.amountMoreThanReserved'),
                ]
            );
            
        }

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

            $notificationsData = [];
            foreach ($users as $user) {
                
                $dataString = $this->main->notificationTranslation(10, $user->lang,$company->company_name);

                $data = array(
                    'title' =>  $dataString['title'],
                    'body' => $dataString['body'],
                    'type' => 10,
                    'href' => url('/' . $user->lang . "/administrator/company/bank/transfer/$model->id/details"),
                    "image" => "http://bdfjade.com/data/out/111/6150983-amazing-pic.jpg"
                );

                $notificationsData[] = array(
                    'user_id' => $user->id,
                    'title' => $data['title'],
                    'body' => $data['body'],
                    'order_id' => null,
                    'type' => 10,
                    'sender_id' => $company->id,
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

            $notificationsData = [];

            foreach ($users as $user) {

                $dataString = $this->main->notificationTranslation(9, $user->lang,$company->company_name);
                $data = array(
                    'title' =>  $dataString['title'],
                    'body' => $dataString['body'],
                    'type' => 9,
                    'href' => url('/' . $user->lang . "/administrator/financial/dues/company/$item->id/details"),
                    "image" => "http://bdfjade.com/data/out/111/6150983-amazing-pic.jpg"
                );

                $notificationsData[] = array(
                    'user_id' => $user->id,
                    'title' => $data['title'],
                    'body' => $data['body'],
                    'order_id' => null,
                    'type' => 9,
                    'sender_id' => $company->id,
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
