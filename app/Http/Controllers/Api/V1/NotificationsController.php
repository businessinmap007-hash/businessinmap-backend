<?php

namespace App\Http\Controllers\Api\V1;

use App\Company;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class NotificationsController extends Controller
{
    public function __construct(Request $request)
    {
        $language = $request->headers->get('lang') ? $request->headers->get('lang') : 'ar';
        app()->setLocale($language);


    }

    public function getUserNotifications(Request $request)
    {
        $token = str_replace('Bearer ', '', request()->headers->get('Authorization'));
        

        $user = User::whereApiToken($token)->first();
        
        


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

        $query = Notification::orderBy('created_at', 'desc')->with('order')->where('user_id', $user->id);


        /**
         * @@ Skip Result Based on SkipCount Number And Pagesize.
         */

        $query->skip($skipCount + (($currentPage - 1) * $pageSize));
        $query->take($pageSize);

        /**
         * @ Get All Data Array
         */

        $notifications = $query->get();
        
        
        
        
        
        $notifications->map(function($q){
            $notifyData = $this->localizationNotification($q->type, $q->order_id);
            $q->type = (int) $q->type;
            $q->senderImage = $q->sender_id  != "" ? $this->getSenderImage($q->sender_id) : "";
            $q->title = $notifyData['title'];
            
        });
        

        /**
         * Return Data Array
         */

        return response()->json([
            'status' => 200,
            'data' => $notifications
        ]);
    }
    
    public function localizationNotification($type, $orderId = null){
        
        
        $type = (int) $type;
        
        $data = [];
        switch($type){
            case $type == 3:
                $data['title'] = __('trans.newOrderTitle', ['id' => $orderId]);
                $data['body'] =  __('trans.newOrderBody');
                break;
                
            default: 
                $data['title'] = "default title";
                $data['body'] = "default body";
        }
        
        return $data;
        
    }




    private function getSenderImage($id)
    {
        $user = User::findOrFail($id);

            if (!$user) 
              return "";
              
              $image = "";
              
              if($user->userType() == 'company'){
                  $image = $user->company_logo;
              }else{
                  
                  $image = $user->image;
              }
              return $image == null ? ""  :  $image;
    }

    public function getCompanyNameByID($id)
    {
        $company = Company::whereId($id)->first();
        return $company->name;
    }


    public function delete(Request $request)
    {

        $user = User::whereApiToken($request->api_token)->first();
        
        $is_deleted = $user->notifications()->where('id', $request->notifyId)->delete();

        if ($is_deleted) {
            return response()->json([
                'status' => true,
                'count' => $user->unreadNotifications->count()
            ]);
        } else {
            return response()->json([
                'status' => false,
            ]);
        }
    }
}
