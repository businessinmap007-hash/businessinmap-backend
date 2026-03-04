<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CouponController extends Controller
{
    public function discount(Request $request)
    {
        if ($request->codeType != "" && $request->codeType == "couponCode") {
            if (!$coupon = Coupon::whereCode($request->code)->first()):
                return response()->json([
                    'status' => 400,
                    'message' => "Something went wrong!"
                ], 400);
            endif;

            if ($coupon->category != $request->categoryId && $coupon->category != "all"):
                return response()->json([
                    'status' => 400,
                    'message' => "Sorry, this code not available for you department."
                ], 400);
            endif;


            if ($coupon->limit_months != null) {

                if ($request->duration < $coupon->limit_months) {
                    return response()->json([
                        'status' => 400,
                        'message' => "عفواً، اقل مدة محددة لاإستخدام كود الخصم هي ($coupon->limit_months شهر)"
                    ], 400);
                }
            }

            if ($coupon->times > 0):
                return response()->json([
                    'status' => 200,
                    'months' => (int)$coupon->percentage
                ], 200);
            endif;
        } else {


            $message = "";


            $useMonths = true;

            $setting = new \App\Models\Setting;
            $limitDuration = $setting->getBody('limit_months');
            if ($request->duration < $limitDuration) {
                $message = "لا يمكنك إستخدام هذا الكود للإشتراك اقل من $limitDuration اشهر";
                $useMonths = false;
            }

            if (!\App\Models\User::whereCode($request->code)->first()) {
                $message = "كود البروفايل المستخدم غير موجود";
                $useMonths = false;
            }

            if ($request->user()->code == $request->code) {
                $message = "لا يمكنك إستخدام كود البروفايل الخاص بك";
                $useMonths = false;
            }


            if (auth()->user()->type == 'client') {

                return response()->json([
                    'months' => $useMonths == false ? null : giftsAndMonthsAfterRegistrationClient($request->code, auth()->user()->id, $request->duration, $request->categoryId),
                    'message' => $message
                ], 200);
            } else {


                return response()->json([
                    'months' => $useMonths == false ? null : giftsAndMonthsAfterRegistration($request->code, auth()->user()->id, $request->duration),
                    'message' => $message

                ], 200);
            }


        }


    }
}
