<?php

namespace App\Http\Controllers\Api\v1;

use App\Membership;
use App\Sponsor;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Bankaccount;
use App\Paid_ad;
use App\Models\User;
use DB;

class SettingsController extends Controller
{


    public function __construct(Request $request)
    {

        $language = $request->headers->get('lang') ? $request->headers->get('lang') : 'ar';
        app()->setLocale($language);


    }


    public function index()
    {
        return response()->json([
            'status' => 'true',
            'data' => [
                'about_title' => Setting::getBody('about_app_title_' . config('app.locale')),
                'about_desc' => Setting::getBody('about_app_desc_' . config('app.locale')),
                'about_image' => Setting::getBody('about_app_image_' . config('app.locale')),
            ]
        ]);
    }


    public function generalInfo(Request $request)
    {

        $lang = config('app.locale');


        $data = [
            'adCost' => Setting::getBody('ad_cost')
        ];


        return response()->json([
            'status' => 200,
            'data' => $data
        ]);
    }


    public function aboutApp(Request $request)
    {


        return response()->json([
            'status' => 200,
            'data' => [
//                'terms' => Setting::getBody('terms'),
                'aboutus' => htmlspecialchars_decode(strip_tags(Setting::getBody('about_app_desc_' . app()->getLocale()))),
//                'facebook' => Setting::getBody('facebook'),
//                'twitter' => Setting::getBody('twitter'),
//                'instagram' => Setting::getBody('instagram')
            ]
        ]);
    }

    public function socialLinks()
    {
        return response()->json([
            'status' => 200,
            'data' => [
                'facebook' => Setting::getBody('facebook'),
                'twitter' => Setting::getBody('twitter'),
                'instagram' => Setting::getBody('instagram'),
                'google_plus' => Setting::getBody('google_plus')
            ]
        ]);
    }

    public function support(Request $request)
    {

        return response()->json([
            'status' => 200,
            'data' => [
//                'terms' => Setting::getBody('terms'),
                'whatsapp1' => Setting::getBody('whatsapp1'),
                'whatsapp2' => Setting::getBody('whatsapp2'),
                'whatsapp3' => Setting::getBody('whatsapp3'),
                'support_phone' => Setting::getBody('support_phone'),
//                'facebook' => Setting::getBody('facebook'),
//                'twitter' => Setting::getBody('twitter'),
//                'instagram' => Setting::getBody('instagram')
            ]
        ]);
    }


    public function contacts(Request $request)
    {

        return response()->json([
            'status' => 200,
            'data' => [
//                'terms' => Setting::getBody('terms'),
                'phone' => Setting::getBody('contactus_us_phone'),
                'contactus_email' => Setting::getBody('contactus_email'),
                'contactus_twitter' => Setting::getBody('contactus_twitter'),
                'contactus_facebook' => Setting::getBody('contactus_facebook'),
                'contactus_snapchat' => Setting::getBody('contactus_snapchat'),
                'contactus_instgram' => Setting::getBody('contactus_instagram'),
            ]
        ]);
    }


    private function sponsors()
    {
        return Sponsor::get();
    }


    public function countList()
    {
        $user = auth()->user();

        $countMessage = DB::table('conversation_user')->where(['user_id' => $user->id, 'read_at' => null, 'deleted_at' => null])->get()->count();

        $countNotify = $user->unreadNotifications()->where('n_type', 0)->get()->count();

        return response()->json([
            'status' => true,
            'messageCount' => $countMessage,
            'notifyCount' => $countNotify
        ]);


    }

    public function getMemberships()
    {
        $memberships = Membership::get();
        return response()->json([
            'status' => true,
            'data' => $memberships,
        ]);


    }


}
