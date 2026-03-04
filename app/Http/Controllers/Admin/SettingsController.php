<?php

namespace App\Http\Controllers\Admin;

use App\Commercetype;
use App\Models\Category;
use App\Comment;
use App\Company;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\PostsRequests;
use App\Http\Controllers\Controller;
use Auth;
use Config;
use Image;
use Session;
use App\Http\Helpers\Images;
use willvincent\Rateable\Rating;
use Illuminate\Support\Facades\Gate;

class SettingsController extends Controller
{

    /**
     * @var string
     * @ public variable to save path.
     */
    public $public_path;

    function __construct()
    {
        $this->public_path = 'files/settings/';
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()

    {
        if (!Gate::allows('app_general_settings_management')) {
            return abort(401);
        }

        $settings = Setting::all();
        return view('admin.settings.setting')->withSettings($settings);
    }


    public function commission()
    {
        $settings = Setting::all();
        return view('admin.settings.commission')->withSettings($settings);
    }

    public function homeSettings()
    {
        $settings = Setting::all();
        return view('admin.settings.home-setting')->withSettings($settings);
    }

    public function discountAndGifts()
    {
        $settings = Setting::all();
        return view('admin.settings.discounts-gifts')->withSettings($settings);
    }

    public function visionAndMission()
    {
        $settings = Setting::all();
        return view('admin.settings.vision-mission')->withSettings($settings);
    }


    public function goals()
    {
        $settings = Setting::all();
        return view('admin.settings.goals')->withSettings($settings);
    }


    public function commentsProjects()
    {
        $settings = Setting::all();
        $companies = Company::where('is_active', 1)->get();
        return view('admin.settings.projects')->withSettings($settings)->withCompanies($companies);
    }


    public function commentsProjectsSettings(Request $request)
    {
        foreach ($request->all() as $key => $value) {
            if ($key != '_token' && $key != 'companies'):
                Setting::updateOrCreate(['key' => $key], ['body' => $value]);
            endif;
        }


        if ($request->comment_setting == 2) {

            $companies = Company::where('is_active', 1)->get();
            foreach ($companies as $company) {
                $company->is_comment = 1;
                $company->save();
            }


            if ($request->companies != '') {

                foreach ($request->companies as $id) {
                    $company = Company::find($id);
                    $company->is_comment = 0;
                    $company->save();
                }

            }
        }


        return response()->json([
            'status' => true,
            'message' => 'لقد حفظ تعديلات بيانات االتعليقات بنجاح.'
        ]);

    }


    public function ratingProjectsSettings(Request $request)
    {

        foreach ($request->all() as $key => $value) {
            if ($key != '_token' && $key != 'companies'):
                Setting::updateOrCreate(['key' => $key], ['body' => $value]);
            endif;
        }


        if ($request->rate_setting == 2) {

            $companies = Company::where('is_active', 1)->get();
            foreach ($companies as $company) {
                $company->is_rate = 1;
                $company->save();
            }

            if ($request->companies != '') {

                foreach ($request->companies as $id) {
                    $company = Company::find($id);
                    $company->is_rate = 0;
                    $company->save();
                }

            }
        }


        return response()->json([
            'status' => true,
            'message' => 'لقد حفظ تعديلات بيانات التقييمات بنجاح.'
        ]);
    }


    public function aboutus()
    {
        if (!Gate::allows('content_management')) {
            return abort(401);
        }

        $settings = Setting::all();
        return view('admin.settings.aboutus')->withSettings($settings);
    }


    public function hajLinkPage()
    {
        $settings = Setting::all();
        return view('admin.settings.haj-link')->withSettings($settings);
    }



    public function statistics()
    {
        $settings = Setting::all();
        return view('admin.settings.statistics')->withSettings($settings);
    }



    public function umrahExpectedPage()
    {
//        if (!Gate::allows('app_general_settings_management')) {
//            return abort(401);
//        }

        $settings = Setting::all();
        return view('admin.settings.umrah-expected')->withSettings($settings);
    }

    public function appGeneralSettings()
    {
        if (!Gate::allows('app_general_settings_management')) {
            return abort(401);
        }
        return view('admin.settings.general-setting');
    }


    public function socialLinks()
    {
        $settings = Setting::all();
        return view('admin.settings.socials')->withSettings($settings);
    }


    public function terms()
    {
        $settings = Setting::all();
        return view('admin.settings.terms')->withSettings($settings);
    }


    public function privacy()
    {
        $settings = Setting::all();
        return view('admin.settings.privacy')->withSettings($settings);
    }

    public function support()
    {
        $settings = Setting::all();
        return view('admin.settings.supports')->withSettings($settings);
    }

    public function contactus()
    {
        $settings = Setting::all();
        return view('admin.settings.contactus')->withSettings($settings);
    }


    public function prohibitedgoods()
    {
        $settings = Setting::all();
        return view('admin.settings.prohibitedgoods')->withSettings($settings);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {

        return view('admin.settings.index');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {


        foreach ($request->all() as $key => $value) {
            if ($key != '_token' && $key != 'about_app_image_old' && $key != "haj_link_image_old"):
                if (is_array($value))
                    $value = serialize($value);
                Setting::updateOrCreate(['key' => $key], ['body' => $value]);
            endif;
        }

        if ($request->hasFile('about_app_image')):
            Setting::updateOrCreate(['key' => 'about_app_image'], ['body' => '/public/' . $this->public_path . UploadImage::uploadMainImage($request, 'about_app_image', $this->public_path)]);
            if ($request->about_app_image_old) {
                if (\File::exists(public_path($request->about_app_image_old))):
                    \File::delete(public_path($request->about_app_image_old));
                endif;
            }
        endif;


        if ($request->hasFile('haj_link_image')):
            Setting::updateOrCreate(['key' => 'haj_link_image'], ['body' => '/public/' . $this->public_path . UploadImage::uploadMainImage($request, 'haj_link_image', $this->public_path)]);
            if ($request->haj_link_image_old) {
                if (\File::exists(public_path($request->haj_link_image_old))):
                    \File::delete(public_path($request->haj_link_image_old));
                endif;
            }
        endif;

        if ($request->ajax()) {
            return response()->json([
                'status' => true,
                'message' => "لقد تم حفظ بيانات الإعدادات بنجاح",

            ]);
        } else {
            Session::flash('success', trans('trans.setting_success'));
            return redirect()->back();
        }


    }


    public function commentStatus(Request $request)
    {
        $comment = Comment::whereId($request->commentId)->first();
        $comment->is_approve = $request->type;

        if ($comment->save()) {
            if ($comment->is_approve == 1) {
                $message = "لقد تم تفعيل التعليق بنجاح";
            } elseif ($comment->is_approve == 0) {
                $message = "لقد تم إلغاء التفعيل على التعليق بنجاح";
            }


            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => [
                    'id' => $comment->id,
                    'type' => $comment->is_approve
                ]
            ]);
        }
    }

    public function rateStatus(Request $request)
    {


        $rate = Rating::whereId($request->rateId)->first();
        $rate->is_agree = $request->type;

        if ($rate->save()) {
            if ($rate->is_agree == 1) {
                $message = "لقد تم تفعيل التقييم بنجاح";
            } elseif ($rate->is_agree == 0) {
                $message = "لقد تم إلغاء التفعيل على التقييم بنجاح";
            }


            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => [
                    'id' => $rate->id,
                    'type' => $rate->is_agree
                ]
            ]);
        }
    }


    public function rateDelete(Request $request)
    {
        $model = Rating::whereId($request->id)->first();
        if (!$model) {
            return response()->json([
                'status' => false,
                'message' => 'عفواً, هذا التقييم غير موجود او ربما تم حذفه'
            ]);
        }

        if ($model->delete()) {
            return response()->json([
                'status' => true,
                'message' => 'لقد تم حذف التقييم بنجاح'
            ]);
        }
    }


    public function getCommerceTypes()
    {

        $types = Commercetype::get();

        return view('admin.commerceTypes.index')->with(compact('types'));
    }


    public function createCommerceTypes()
    {

        return view('admin.commerceTypes.create');
    }


    public function storeCommerceTypes(Request $request)
    {

        $model = new Commercetype();

        $model->{'name:ar'} = $request->name;
        $model->{'name:en'} = $request->name;

        if ($model->save()) {
            return redirect(route('spareparts.commerceType'))->with('success', 'لقد تمت عملية الإضافة بنجاح.');
        }


    }

    public function editCommerceTypes(Request $request, $id)
    {

        $type = Commercetype::find($id);
        return view('admin.commerceTypes.edit')->with(compact('type'));


    }

    public function deleteCommerceTypes(Request $request, $id)
    {
        $model = Commercetype::findOrFail($id);
        if ($model->translations()->delete() && $model->delete()) {
            return response()->json([
                'status' => true,
                'data' => 'لقد تمت عملية الحذف بنجاح'
            ]);
        }
    }

    public function updateCommerceTypes(Request $request, $id)
    {

        $model = Commercetype::find($id);

        $model->{'name:ar'} = $request->name;
        $model->{'name:en'} = $request->name;

        if ($model->save()) {
            return redirect(route('spareparts.commerceType'))->with('success', 'لقد تم تعديل البيانات بنجاح.');
        }


    }


}