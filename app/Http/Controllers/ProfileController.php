<?php

namespace App\Http\Controllers;

use App\Libraries\Main;
use App\Models\User;
use App\Services\LocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Helpers\Images;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    protected Main $main;
    protected string $public_path;

    public function __construct(Main $main)
    {
        $this->main = $main;
        $this->public_path = 'files/uploads/';
    }

    /**
     * Show profile page
     */
    public function profile()
    {
        $user = auth()->user();

        if ($user->type === 'admin') {
            return redirect()->route('admin.home');
        }

        return view('profile.index', compact('user'));
    }

    /**
     * Update profile data + optional GPS location
     */
    public function profileUpdateUser(Request $request)
    {
        $user = auth()->user();

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */
        $validator = Validator::make($request->all(), [
            'email'     => 'required|email|unique:users,email,' . $user->id,
            'phone'     => 'required|unique:users,phone,' . $user->id,
            'image'     => 'nullable|image|max:2048',
            'latitude'  => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'country_id'     => 'nullable|exists:countries,id',
            'governorate_id' => 'nullable|exists:governorates,id',
            'city_id'        => 'nullable|exists:cities,id',
        ], [
            'email.required' => __('trans.field_required'),
            'email.unique'   => __('trans.email_unique'),
            'phone.required' => __('trans.required'),
            'phone.unique'   => __('trans.phone_unique'),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 402,
                'errors' => $validator->errors()->all(),
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Basic fields
        |--------------------------------------------------------------------------
        */
        $inputs = $request->except([
            '_token',
            'latitude',
            'longitude',
            'country_id',
            'governorate_id',
            'city_id',
        ]);

        if ($request->hasFile('image')) {
            $inputs['image'] =
                $this->public_path .
                Images::imageUploader($request->file('image'), $this->public_path);
        }

        /*
        |--------------------------------------------------------------------------
        | Location handling
        |--------------------------------------------------------------------------
        | Priority:
        | 1. GPS (lat/lng)
        | 2. Manual selection (country/governorate/city)
        |--------------------------------------------------------------------------
        */
        if ($request->filled(['latitude', 'longitude'])) {

            $location = LocationService::detect(
                (float) $request->latitude,
                (float) $request->longitude
            );

            if ($location) {
                $inputs = array_merge($inputs, $location, [
                    'latitude'  => $request->latitude,
                    'longitude' => $request->longitude,
                ]);
            }

        } elseif ($request->filled(['country_id', 'governorate_id', 'city_id'])) {

            $inputs = array_merge($inputs, [
                'country_id'     => $request->country_id,
                'governorate_id' => $request->governorate_id,
                'city_id'        => $request->city_id,
            ]);
        }

        $user->update($inputs);

        return response()->json([
            'status'  => 200,
            'message' => __('trans.profile_updated'),
            'url'     => route('profile'),
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'old_password'        => 'required',
            'newpassword'         => 'required|min:6',
            'confirm_newpassword' => 'required|same:newpassword',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 400,
                'errors'  => $validator->errors()->all(),
                'message' => __('global.some_errors_happen'),
            ]);
        }

        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json([
                'status'  => 400,
                'message' => __('global.old_password_is_incorrect'),
            ]);
        }

        $user->update([
            'password' => Hash::make($request->newpassword),
        ]);

        return response()->json([
            'status'  => 200,
            'message' => __('global.password_was_edited_successfully'),
        ]);
    }

    /**
     * Update phone number
     */
    public function updatePhone(Request $request)
    {
        $request->validate([
            'new_phone' => 'required|unique:users,phone',
        ]);

        $user = auth()->user();

        $actionCode = rand(1000, 9999);
        $actionCode = $user->actionCode($actionCode);

        $user->update([
            'phone'       => $request->new_phone,
            'action_code' => $actionCode,
            'is_active'   => 0,
        ]);

        return response()->json([
            'status'  => 200,
            'message' => __('trans.phone_updated_successfully'),
        ]);
    }
}
