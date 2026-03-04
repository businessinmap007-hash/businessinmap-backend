<?php

function queryStringValues($array = [])
{
    $string = "";
    $i = 1;
    foreach ($array as $key => $arr) {
        if (!$key)
            continue;
        if ($i == 1)
            $string = "?$key=$arr";
        else
            $string .= "&$key=$arr";
        $i++;
    }
    return $string;
}


function returnedResponse($status = 200, $message = null, $results = [], $url = null, $additionals = [])
{

    $data = array('status' => $status);

    if ($message)
        $data['message'] = $message;

    if ($url)
        $data['url'] = $url;

    if (count($data) > 0 && $status == 200)
        $data['data'] = $results;
    else
        $data['errors'] = $results;

    if (count($additionals) > 0) {
        $data['additional'] = $additionals;
    }

    return response()->json($data);

}


function anotherLangWhenDefaultNotFound($model, $key)
{

    $language = app()->getLocale();
    if ($model->$key && $model->$key != "") {
        $translate = @$model->getTranslation($language, true)->$key;
        if (!$translate)
            return $model->getTranslation(getAnotherLang(), true)->$key;
        return $translate;
    } else {
        return $model->getTranslation(getAnotherLang(), true)->$key;
    }

}


function getAnotherLang()
{
    $languages = config('translatable.locales');
    $current = app()->getLocale();
    if (count($languages) == 2 && $current == 'ar'):
        $another = "en";
    else:
        $another = "ar";
    endif;
    return $another;
}


function getTextForAnotherLang($model, $key, String $lang)
{


    $anotherLang = $lang == 'ar' ? 'en' : 'ar';
    if (isset($model->getTranslation($lang, true)->$key)) {
        $text = $model->getTranslation($lang, true)->$key;
    } else {
        $text = @$model->getTranslation($anotherLang, true)->$key;
    }
    return $text;

}

function sendEmail($inputs)
{
    return \Mail::send('emails.email', ['subject' => $inputs['name'], 'content' => $inputs], function ($m) use ($inputs) {
        $m->to($inputs['email']);
        $m->subject($inputs['name']);
        $m->from('marakeb@arkabmaana.com');
        $m->replyTo("marakeb@arkabmaana.com");
    });
}


function sendEmailTo($type = "all", $inputs = null)
{

    $subject = $inputs['subject'];
    $emails = returnSubjectAndEmails($type);
//    return $emails;

    $isSent = \Mail::send('emails.requests', ['subject' => $subject, 'content' => $inputs], function ($m) use ($inputs, $type, $subject, $emails) {
        $m->to($emails);
        $m->subject($subject);
        $m->from('marakeb@arkabmaana.com');
        $m->replyTo("marakeb@arkabmaana.com");
    });
    return $isSent;
}

function returnSubjectAndEmails($type)
{

    $emails = [];
    switch ($type):
        case $type == 'all';
            $emails = \App\Models\User::whereIsSuspend(0)->whereIn('is_user', [1, 2, 3])->pluck('email')->toArray();
            break;
        case $type == 'providers';
            $emails = \App\Models\User::whereIsSuspend(0)->whereIsUser(2)->pluck('email')->toArray();
            break;

        case $type == 'campaigns';
            $emails = \App\Models\User::whereIsSuspend(0)->whereIn('is_user', [1, 3])->pluck('email')->toArray();
            break;
        default:
            return $emails;
    endswitch;

    return $emails;


}

function sendGeneralEmail($view, $inputs)
{
    return \Mail::send($view, ['subject' => __('trans.site_name'), 'content' => $inputs], function ($m) use ($inputs) {
        $m->to($inputs['email']);
        $m->subject(__('trans.site_name'));
        $m->from('marakeb@arkabmaana.com');
        $m->replyTo("marakeb@arkabmaana.com");
    });
}


function sendGeneralEmailOrds($view, $inputs)
{
    return \Mail::send($view, ['subject' => __('trans.site_name'), 'content' => $inputs], function ($m) use ($inputs) {
        $m->to($inputs['email_admin']);
        $m->subject(__('trans.site_name'));
        $m->from($inputs['email']);
        $m->replyTo($inputs['email']);
    });
}

function noResults()
{
    $html = '<div class="container pt-5 ">';
    $html .= '<div class="text-center mb-5 pb-5">';
    $html .= '<img src="' . request()->root() . '/public/assets/front/images/icons/paper.png" class="img-fluid my-5 empty-img">';
    $html .= '<h4 class="empty-h">' . __('trans.no_results') . '</h4>';
    $html .= "</div>";
    $html .= "</div>";
    echo $html;
}


function searchBlank()
{

    $html = '<div class="text-center mb-5 pb-5">';
    $html .= '<img src="' . request()->root() . '/public/assets/front/images/icons/paper.png" class="img-fluid my-5 empty-img">';
    $html .= '<h4 class="empty-h">' . __('trans.enter_required_data') . '</h4>';
    $html .= "</div>";
    echo $html;
}


function getAdminDevices()
{


    if (!empty(getNotificationManagers())) {

        $devices = \App\Models\Device::whereIn('user_id', getNotificationManagers())->get();

        $players = [];
        foreach ($devices as $device) {
            $players[] = $device->device;
        }
        return $players;
    } else {
        return [];
    }

}

function getNotificationManagers()
{
    $managersIds = \App\Models\User::whereIsUser(0)->whereHas('roles', function ($role) {
        $role->whereHas('abilities', function ($q) {
            $q->whereIn('name', ['notifications_management', '*']);
        });
    })->pluck('id');

    if (count($managersIds) > 0)
        return $managersIds;
    else
        return [];
}


function generateUrl($userId)
{

    $url = "";
    $user = \App\Models\User::whereId($userId)->first();
    if (!$user)
        return null;
    switch ($user):
        case $user->is_user == 4:
            $url = url('/administrator/get/clients');
            break;
        case $user->is_user == 3:
            $url = url('/administrator/get/agent/' . $user->id . '/details');
            break;

        case $user->is_user == 2:

            $url = url('/administrator/get/provider/' . $user->id . '/details');
            break;

        case $user->is_user == 1:
            $url = url('/administrator/get/campaign/' . $user->id . '/details');
            break;
        default:
            return $url;
    endswitch;

    return $url;
}

function generateUrlOrds($ordId)
{
    $url = "";
    $ord = \App\Models\Ord::whereId($ordId)->first();
    if (!$ord)
        return null;
    switch ($ord):
        case $ord->type == 0:
            $url = url('/administrator/present/offers');
            break;
        case $ord->type == 1:
            $url = url('/administrator/asked/orders');
            break;
        default:
            return $url;
    endswitch;
    return $url;
}


function generateUrlConnection($connectionId)
{
    $url = "";
    $connection = \App\Models\Connection::whereId($connectionId)->first();
    if (!$connection)
        return null;
    switch ($connection):
        case $connection->connection_type == 0:
            $url = url('/administrator/internal/connections');
            break;
        case $connection->connection_type == 1:
            $url = url('/administrator/external/connections');
            break;
        default:
            return $url;
    endswitch;
    return $url;
}


function visitors($action = 'get')
{
    if ($action == 'get') {
        return \App\Models\Tracker::count();
    }
    if ($action == 'create') {
        if (!\App\Models\Tracker::whereIp(request()->ip())->whereDate('date', currentDate())->first()) {
            \App\Models\Tracker::create([
                'ip' => \request()->ip(),
                'date' => date('Y-m-d H:i:s'),
                'visit_time' => date('H:i:s'),
            ]);
        }
    }
    if ($action == 'today') {
        return \App\Models\Tracker::whereDate('date', currentDate())->count();
    }
}


function currentDate()
{
    return \Carbon\Carbon::now()->toDateString();
}


function getRateOfCurrentUser($id)
{

    if (auth()->check()) {
        $rating = new \willvincent\Rateable\Rating();
        $userRatingBefore = $rating->where('rateable_id', $id)->where('user_id', auth()->id())->first();
        return $userRatingBefore->rating;
    } else {
        return 0;
    }

}


function getUserInfo($userId)
{
    $user = \App\Models\User::whereId($userId)->first();
    if (!$user)
        return null;

    return $user;
}

function getTargetsAndFollowersBusiness($token)
{

    $user = \App\Models\User::whereApiToken($token)->first();

    $userTargetFromCategory = \Illuminate\Support\Facades\DB::table('category_target')->where('category_id', $user->category_id)->pluck('user_id');
    $followers = $user->followers()->pluck('follow_id');
    $followersFromCategories = \App\Models\User::whereIn('category_id', $user->categoryFollows->pluck('id'))->pluck('id');
    $targetMe = $user->targetsReverse->pluck('id');
    $collection = new \Illuminate\Support\Collection([$userTargetFromCategory, $followers, $followersFromCategories, $targetMe]);
    return $collection->collapse()->unique()->values()->all();
}


/**
 * Method to find the distance between 2 locations from its coordinates.
 *
 * @param latitude1 LAT from point A
 * @param longitude1 LNG from point A
 * @param latitude2 LAT from point A
 * @param longitude2 LNG from point A
 *
 * @return Float Distance in Kilometers.
 */
function getDistanceBetweenPointsNew($latitude1, $longitude1, $latitude2, $longitude2, $unit = 'Km')
{
    $theta = $longitude1 - $longitude2;
    $distance = sin(deg2rad($latitude1)) * sin(deg2rad($latitude2)) + cos(deg2rad($latitude1)) * cos(deg2rad($latitude2)) * cos(deg2rad($theta));

    $distance = acos($distance);
    $distance = rad2deg($distance);
    $distance = $distance * 60 * 1.1515;

    switch ($unit) {
        case 'Mi':
            break;
        case 'Km' :
            $distance = $distance * 1.609344;
    }

    return (round($distance, 2));
}


function giftsAndMonthsAfterRegistration($profileCode, $userId, $duration)
{

    $setting = new \App\Models\Setting;

    $ownerCode = \App\Models\User::whereCode($profileCode)->first();
    $registeredUser = \App\Models\User::whereId($userId)->first();
    if (!$ownerCode || !$registeredUser)
        return;

    if ($duration == "" || $duration == 0)
        return;

//    $cost = optional($registeredUser->category->parent)->per_month;
//    if ($duration >= 12)
//        $cost = optional($registeredUser->category->parent)->per_year;


    $limitMonths = $setting->getBody('limit_months');
    if ($ownerCode->gifts != null)
        $limitMonths = $ownerCode->gifts->limit_months;

    if ($duration >= $limitMonths)

        $freeMonths = $setting->getBody('free_months');
    if ($ownerCode->gifts != null)
        $freeMonths = $ownerCode->gifts->free_months;

    return $freeMonths;

}


function giftsAndMonthsAfterRegistrationClient($profileCode, $userId, $duration, $categoryId)
{

    $setting = new \App\Models\Setting;

    $ownerCode = \App\Models\User::whereCode($profileCode)->first();
    $registeredUser = \App\Models\User::whereId($userId)->first();
    if (!$ownerCode || !$registeredUser)
        return;

    if ($duration == "" || $duration == 0)
        return;

//    $category = \App\Models\Category::whereId($categoryId)->first();
//    $cost = optional($category->parent)->per_month;
//    if ($duration >= 12)
//        $cost = optional($category->parent)->per_year;

    $limitMonths = $setting->getBody('limit_months');

    if ($ownerCode->gifts != null)
        $limitMonths = $ownerCode->gifts->limit_months;

    if ($duration >= $limitMonths)

        $freeMonths = $setting->getBody('free_months');
    if ($ownerCode->gifts != null)
        $freeMonths = $ownerCode->gifts->free_months;

    return $freeMonths;

}

function getGiftValue($cost, $duration, $userId, \App\Models\User $owner)
{

    $setting = new \App\Models\Setting;

    $commissionMonths = $setting->getBody('commission_months');


    $costPerMonth = $cost;

    if ($duration >= 12)
        $costPerMonth = $cost / 12;

    $ownerCodeCommission = ($costPerMonth * $commissionMonths * ($duration / 12));

    $data = array(
        'status' => 'deposit',
        'price' => $ownerCodeCommission,
        'operation' => 'award',
        'notes' => 'From Registeration By Code Profile - ' . $owner->code,
        'target_id' => $userId
    );

    if ($owner->transactions()->create($data))
        return true;
    return false;


}


function currencyConverter($from, $to, $amount)
{


    $string = $from . "_" . $to;

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://free.currconv.com/api/v7/convert?q=" . $string . "&compact=ultra&apiKey=b0a8dc624b8d81def330",
        CURLOPT_RETURNTRANSFER => 1
    ));

    $response = curl_exec($curl);

    $response = json_decode($response, true);

    $rate = $response[$string];
    $total = $rate * $amount;


    return sprintf("%.2f", $total);

}

?>