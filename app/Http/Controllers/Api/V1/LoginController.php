<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Device;
use App\Http\Resources\UserResource;
use App\Http\Helpers\Main;

class LoginController extends Controller
{
    protected $main;

    public function __construct(Main $main, Request $request)
    {
        // تحديد اللغة من الـ headers أو الطلب
        $lang = $request->header('lang', $request->get('lang', 'ar'));
        app()->setLocale($lang);
        $this->main = $main;
    }

    /**
     * تسجيل الدخول وإصدار توكن Sanctum
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // محاولة تسجيل الدخول
        if (!Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            return response()->json([
                'status'  => 401,
                'message' => 'Email or password incorrect.',
            ], 401);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // حذف التوكنات القديمة لتجنب التراكم (اختياري)
        $user->tokens()->delete();

        // إنشاء access token جديد عبر Sanctum
        $token = $user->createToken('api_token')->plainTextToken;

        // إدارة الأجهزة
        $this->updateDevice($request, $user);

        return response()->json([
            'status'  => 200,
            'message' => 'Login successful',
            'data'    => new UserResource($user),
            'token'   => $token,
        ]);
    }

    /**
     * تحديث أو إضافة Device Token
     */
    protected function updateDevice(Request $request, User $user)
    {
        if (!$request->deviceToken) return;

        $device = Device::where('device', $request->deviceToken)->first();

        if ($device) {
            $device->user_id = $user->id;
            $device->device_type = $request->deviceType ?? $device->device_type;
            $device->save();
        } else {
            Device::create([
                'user_id'     => $user->id,
                'device'      => $request->deviceToken,
                'device_type' => $request->deviceType ?? '',
            ]);
        }
    }

    /**
     * تحديث device token (مستقلة)
     */
    public function update_device_token(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'deviceToken' => 'required|string',
            'deviceType'  => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user(); // من Sanctum token

        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'Unauthorized',
            ], 401);
        }

        $this->updateDevice($request, $user);

        return response()->json([
            'status'  => 200,
            'message' => 'Device token updated successfully',
        ]);
    }

    /**
     * تفعيل الحساب برمز التفعيل
     */
    public function postActivationCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone'           => 'required|string',
            'activation_code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::where('phone', $request->phone)->first();

        if (!$user) {
            return response()->json([
                'status'  => 404,
                'message' => __('global.account_not_found'),
            ], 404);
        }

        if ($user->action_code !== $request->activation_code) {
            return response()->json([
                'status'  => 400,
                'message' => __('global.activation_code_not_correct'),
            ]);
        }

        if (!$user->is_active) {
            $user->is_active = 1;
            $user->save();

            return response()->json([
                'status'  => 200,
                'message' => __('global.your_account_was_activated'),
                'data'    => new UserResource($user),
            ]);
        }

        return response()->json([
            'status'  => 200,
            'message' => __('global.your_account_was_activated_before'),
            'data'    => new UserResource($user),
        ]);
    }

    /**
     * إعادة إرسال رمز التفعيل
     */
    public function resendActivationCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::where('phone', $request->phone)->first();

        if (!$user) {
            return response()->json([
                'status'  => 404,
                'message' => __('global.account_not_found'),
            ], 404);
        }

        $code = rand(1000, 9999);
        $user->action_code = $user->actionCode($code);
        $user->save();

        // هنا يمكنك إرسال SMS فعلي
        return response()->json([
            'status'  => 200,
            'message' => __('global.activation_code_sent'),
            'code'    => $user->action_code, // يمكن حذفها في الإنتاج
        ]);
    }
}
