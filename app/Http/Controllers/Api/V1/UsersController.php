<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Hash;
use Validator;
use App\Http\Helpers\Images;
use App\Models\Category;
use DB;
use Illuminate\Support\Facades\App;

class UsersController extends Controller
{

    public $public_path;
    public $public_path_user;

    public function __construct(Request $request)
    {
        $this->public_path = 'files/companies/';
        $this->public_path_user = 'files/users/';
        $language = $request->headers->get('lang')  ? $request->headers->get('lang') : 'ar' ;
        App::setLocale($language);
    }


    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function profile(Request $request)
    {
        $api_token = ltrim($request->headers->get('Authorization') ,"Bearer ") ;
        $user = User::with('city')->whereApiToken($api_token)->first();
        return response()->json([
            'status' => 200,
            'data' => $user
        ]);

    }



    public function profileUpdateLang(Request $request){

        $api_token = ltrim($request->headers->get('Authorization') ,"Bearer ") ;
        $user = User::whereApiToken($api_token)->first();

        $validator = Validator::make($request->all(), [
            'lang' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(
                [
                    'status' => 400 ,
                    'errors' => $validator->errors()->all(),
                    'message' => trans('global.some_errors_happen') ,
                ]
            );
        }

        // Get Input

        $user->lang = $request->lang;

        $user->load('city');

        $user->save();

        return response()->json([
            'status' => 200,
            'message' => __('global.updated_successfully'),

        ]);



    }


    public function profileUpdateNotification(Request $request){
        $api_token = ltrim($request->headers->get('Authorization') ,"Bearer ") ;
        $user = User::whereApiToken($api_token)->first();

        $validator = Validator::make($request->all(), [
            'notification' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(
                [
                    'status' => 400 ,
                    'errors' => $validator->errors()->all(),
                    'message' => trans('global.some_errors_happen') ,
                ]
            );
        }



        // Get Input

        $user->notification = $request->notification == 1 ? 1  : 0;

        $user->load('city');

        $user->save();
        if($user->notification == 1){
            $message = __('global.you_turned_on_notification');
        }else{
            $message = __('global.you_turned_off_notification');
        }


        return response()->json([
            'status' => 200,
            'message' => $message,

        ]);



    }




    public function profileUpdate(Request $request)
    {
        $api_token = ltrim($request->headers->get('Authorization') ,"Bearer ") ;
        $user = User::whereApiToken($api_token)->first();

        $validator = Validator::make($request->all(), [
            'username' => 'required|unique:users,username,' . $user->id,
            'phone' => 'required|unique:users,phone,' . $user->id,
            'cityId' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(
                [
                    'status' => 400 ,
                    'errors' => $validator->errors()->all(),
                    'message' => trans('global.some_errors_happen') ,
                ]
            );
        }



        // Get Input

            $user->username = $request->username;
            $user->email = $request->email ? $request->email : "" ;
            $user->phone = $request->phone;
            $user->city_id = $request->cityId;
            $user->lat = $request->lat;
            $user->lng = $request->lng;
            $user->address = $request->address;

            if ($request->hasFile('userImage')):
                $user->image = $request->root() . '/' . $this->public_path_user . UploadImage::uploadImage($request, 'userImage', $this->public_path_user);
            endif;

            $user->load('city');

            if ($user->save()) {


                return response()->json([
                    'status' => 200,
                    'data' => $user,

                ]);

            } else {
                return response()->json([
                    'status' => 400,
                    'data' => $user,

                ]);
            }



    }

    private function getCategoryForCompany($id)
    {
        $category = Category::with('parent')->whereId($id)->first();
        return $category;
    }


    public function changePassword(Request $request)
    {

        $api_token = ltrim($request->headers->get('Authorization') ,"Bearer ") ;
        $user = User::whereApiToken($api_token)->first();

        $validator = Validator::make($request->all(), [
            'old_password' => 'required',
            'newpassword' => 'required',
            'confirm_newpassword' => 'required|same:newpassword'
        ]);

        if ($validator->fails()) {
            return response()->json(
                [
                    'status' => 400 ,
                    'errors' => $validator->errors()->all(),
                    'message' => trans('global.some_errors_happen') ,
                ]
            );
        }

            $hashedPassword = $user->password;
            if (Hash::check($request->old_password, $hashedPassword)) {
                //Change the password
                $user->fill([
                    'password' => Hash::make($request->newpassword)
                ])->save();

                $user->load('city');
                return response()->json([
                    'status' => 200,
                    'message' => __('global.password_was_edited_successfully'),
                    'data' => $user
                ]);
            } else {
                return response()->json([
                    'status' => 400,
                    'message' => __('global.old_password_is_incorrect'),
                ]);
            }
            



    }


    public function logout(Request $request)
    {
        // Get User By Api Token
        $user = User::where('api_token', $request->api_token)->first();

        // Check if user exist and have devices

        //@@ then delete device Received from Resquest (PLAYESID)
        if ($user && $user->devices):
            $user->devices()->where('device', $request->device_token)->delete();
        endif;

        return response()->json([
            'status' => 200,
            'message' =>'logged out successfully .'
        ]);
    }


    function countNotifications()
    {
        $user = auth()->user();

        $countMessage = DB::table('conversation_user')->where(['user_id' => $user->id, 'read_at' => null, 'deleted_at' => null])->get()->count();

        $countNotify = $user->unreadNotifications()->where('notify_type', 0)->get()->count();

        return response()->json([
            'status' => true,
            'messageCount' => $countMessage,
            'notifyCount' => $countNotify
        ]);

    }


    /**
     * @param $request
     * @return array
     */
    private function postData($request)
    {
        return [
            'username' => $request->username,
            'phone' => $request->phone,
            'cityId' => $request->cityId,
        ];
    }

    /**
     * @return array
     */
    private function valRules($id)
    {
        return [
            'username' => 'required|unique:users,username,' . $id,
            'phone' => 'required|unique:users,phone,' . $id,
            'cityId' => 'required',
        ];
    }

    /**
     * @return array
     */
    private function valMessages()
    {
        return [
            'username.required' => trans('global.field_required'),
            'username.unique' => trans('global.unique_phone'),
            'phone.required' => trans('global.field_required'),
            'phone.unique' => trans('global.unique_phone'),
            'cityId.required' => trans('global.field_required'),

        ];
    }

    public function deleteUser(Request $request)
    {
        $api_token = ltrim($request->headers->get('Authorization'), "Bearer ");
        $user = User::whereApiToken($api_token)->first();
        if ($user) { 
            $user->delete();
        }
        return response()->json([
            'status' => 200,
        ]);
    }

}