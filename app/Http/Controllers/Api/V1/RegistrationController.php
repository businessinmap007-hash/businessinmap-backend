<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\RegisterRequestForm;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Device;
use App\Models\Social;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RegistrationController extends Controller
{
    protected $public_path = 'files/uploads/';

    public function __construct(Request $request)
    {
        // تحديد اللغة
        $lang = $request->header('lang', $request->get('lang', 'ar'));
        app()->setLocale($lang);
    }

    /**
     * ✅ تسجيل مستخدم جديد وإرجاع توكن Sanctum
     */
    public function store(RegisterRequestForm $request)
    {
        try {
            DB::beginTransaction();

            $inputs = $request->except(['api_token', 'device_token', 'device_type']);

            // إنشاء المستخدم
            $inputs['action_code'] = $this->generateActionCode();
            $inputs['code'] = $this->generateProfileCode();

            /** @var \App\Models\User $user */
            $user = User::create($inputs);

            // العلاقات التابعة
            if ($request->filled('businessOptions')) {
                $options = explode(',', $request->businessOptions);
                $user->options()->attach($options);
            }

            // حسابات التواصل الاجتماعي
            $user->social()->create(
                $request->only(['facebook', 'twitter', 'linkedin', 'youtube', 'instagram'])
            );

            // الاشتراكات الافتراضية
            $user->subscriptions()->create([
                'is_active'   => 1,
                'duration'    => 1,
                'price'       => 0,
                'finished_at' => $user->type === 'business'
                    ? Carbon::now()->addMonth()
                    : null,
            ]);

            // إدارة الجهاز (Device)
            $this->manageDevice($request, $user);

            // إنشاء توكن جديد عبر Sanctum
            $token = $user->createToken('api_token')->plainTextToken;

            DB::commit();

            return response()->json([
                'status'  => 200,
                'message' => 'Registration successful',
                'data'    => new UserResource($user),
                'token'   => $token,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 500,
                'message' => 'Registration failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 🎯 إنشاء كود تفعيل فريد
     */
    private function generateActionCode(): string
    {
        do {
            $code = rand(1000, 9999);
        } while (User::where('action_code', $code)->exists());
        return (string) $code;
    }

    /**
     * 🎯 إنشاء كود تعريف فريد للملف الشخصي
     */
    private function generateProfileCode(): string
    {
        do {
            $code = rand(10000000, 99999999);
        } while (User::where('code', $code)->exists());
        return (string) $code;
    }

    /**
     * 🎯 إدارة الـ Device Token
     */
    private function manageDevice(Request $request, User $user): void
    {
        if (!$request->filled('device_token')) return;

        $device = Device::where('device', $request->device_token)->first();

        if ($device) {
            $device->update([
                'user_id'     => $user->id,
                'device_type' => $request->device_type ?? $device->device_type,
            ]);
        } else {
            Device::create([
                'user_id'     => $user->id,
                'device'      => $request->device_token,
                'device_type' => $request->device_type ?? '',
            ]);
        }
    }
}
