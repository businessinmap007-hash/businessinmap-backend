<?php


namespace App\Http\Helpers;

use App\Models\Meal;
use App\Models\User;
use Carbon\Carbon;

class Main
{


    function html_rate_icons($rate)
    {
        $html = '';

        $flooredRate = floor($rate);
        $hasHalfStar = $flooredRate < $rate;
        $emptyStars = 5 - round($rate);

        if ($rate > 0) {
            foreach (range(1, $flooredRate) as $star) {
                $html .= '<i class="fa fa-star text-warning" style="font-size: 25px; color: #f39c12 !important"></i>';
            }
            if ($hasHalfStar) {
                $html .= '<i class="ionicons ion-ios-star-half fa-flip-horizontal text-warning" style="font-size: 25px; color: #f39c12 !important"></i>';
            }
        }

        if ($emptyStars > 0) {
            foreach (range(1, $emptyStars) as $star) {
                $html .= '<i class="fa fa-star text-warning" style="font-size: 25px; color: #ebeff2  !important; "></i >';
            }
        }

        return $html;
    }


    function success_response($data, $msg, $additional)
    {
        return response()->json([
            'status' => true,
            'message' => $msg,
            'data' => $data,
            'additional' => $additional
        ], 200);
    }


    public function userType($id)
    {

        $user = User::find($id);


        if (!$user) {
            return $type = '--';
        }

        switch ($user->is_user) {
            case $user->is_user = 0 :
                $type = __('maincp.o_individuals');
                break;
            case $user->is_user = 1;
                $type = __('maincp.retails');
                break;

            case $user->is_user = 2;
                $type = __('maincp.wholesale');
                break;

            case $user->is_user = 3;
                $type = __('maincp.agencies');
                break;

            case $user->is_user = 4;
                $type = __('maincp.insurances');
                break;

            case $user->is_user = 5;
                $type = __('maincp.maintenance_centers');
                break;

            default:
                $type = __('maincp.visitor');
        }
        return $type;


    }


    function getOrderStatus()
    {
        return view('institution.general.orderStatus');
    }


    public function convertDateStringToDateFormat($string)
    {
        $d = explode(' ', $string);
        $date = date('Y-m-d', strtotime("$d[3]-$d[2]-$d[1]"));
        $ee = Carbon::parse($date);
        return $ee;
    }


    public function checkIsDateHasMealAndReturn($week, $productId, $mealDate)
    {


        $currentInfoSubscription = auth()->user()->subs()->with('subscription.products')->whereIsActive(1)->first();

        $IsExist = Meal::where([
            'user_id' => auth()->id(),
            'subscription_id' => $currentInfoSubscription->id,
            'week' => $week,
            'sub_date' => $mealDate,
            'product_id' => $productId])->first();
//        $query = Meal::whereUserId(auth()->id())->whereSubscriptionId($currentInfoSubscription->subscription_id)->whereWeek($week)->whereProdcutId($productId);


        if (!empty($IsExist)) {
            return true;
        }
        return false;

    }
    
    
    
        function arabicDate($time, $type)
    {
        $months = ["Jan" => "يناير", "Feb" => "فبراير", "Mar" => "مارس", "Apr" => "أبريل", "May" => "مايو", "Jun" => "يونيو", "Jul" => "يوليو", "Aug" => "أغسطس", "Sep" => "سبتمبر", "Oct" => "أكتوبر", "Nov" => "نوفمبر", "Dec" => "ديسمبر"];
        $days = ["Sat" => "السبت", "Sun" => "الأحد", "Mon" => "الإثنين", "Tue" => "الثلاثاء", "Wed" => "الأربعاء", "Thu" => "الخميس", "Fri" => "الجمعة"];
        $am_pm = ['AM' => 'ص', 'PM' => 'م'];

        $day = $days[date('D', $time)];
        $month = $months[date('M', $time)];
        $am_pm = $am_pm[date('A', $time)];

        if ($type == 'complete'):
            $date = date('h:i', $time) . ' ' . $am_pm .' - '.date('d', $time) . '  ' . $month . '  ' . date('Y', $time) ;
        elseif ($type == 'monthAndYear'):
            $date =   $month . ' - ' . date('Y', $time);
        elseif ($type == "withouttime"):
            $date = date('d', $time) . '  ' . $month . '  ' . date('Y', $time) ;
        elseif ($type == "day"):
            $date = $day;
        endif;


        $numbers_ar = ["٠", "١", "٢", "٣", "٤", "٥", "٦", "٧", "٨", "٩"];
        $numbers_en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($numbers_en, $numbers_ar, $date);
    }
    
    


}

