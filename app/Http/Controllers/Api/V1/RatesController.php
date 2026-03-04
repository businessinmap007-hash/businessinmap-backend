<?php

namespace App\Http\Controllers\Api\V1;

use App\Company;
use App\Http\Requests\Rating\RatingFormRequest;
use App\Models\Order;
use App\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Validator;
use willvincent\Rateable\Rating;
use App\Models\Notification;
use Carbon\Carbon;

class RatesController extends Controller
{


    public $main;
    public $push;

    public function __construct(\App\Libraries\Main $main, \App\Libraries\PushNotification $push)
    {

        $language = request()->headers->get('lang') ? request()->headers->get('lang') : 'ar';
        app()->setLocale($language);

        $this->main = $main;
        $this->push = $push;


    }


    function postRate(RatingFormRequest $request)
    {

        $user = User::findOrFail($request->userId);

        if (!$user) {
            return response()->json([
                'status' => 400,
                'message' => __('trans.product_not_found'),
            ]);
        }

        $rating = new Rating();
        $userRatingBefore = $rating->where('rateable_id', $request->userId)->where('user_id', auth()->id())->first();
        if ($userRatingBefore) {
            $userRatingBefore->rating = $request->rate;
            $userRatingBefore->save();
            return response()->json([
                'status' => 200,
                'message' => __('trans.rating_updated'),
            ]);
        }
        $rating->rating = $request->rate;
        $rating->user_id = auth()->id();
        if ($user->ratings()->save($rating))
            return response()->json([
                'status' => 200,
                'message' => __('trans.review_posted_successfully'),
                'avgRating' => $user->averageRating
            ]);
    }


}
