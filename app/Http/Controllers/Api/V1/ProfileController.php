<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\UpdateProfileFormRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    protected $public_path = 'files/uploads/';

    public function __construct(Request $request)
    {
        // تحديد اللغة من الهيدر أو الافتراضي 'ar'
        $lang = $request->header('lang', $request->get('lang', 'ar'));
        app()->setLocale($lang);
    }

    /**
     * ✅ عرض بيانات المستخدم الحالي
     */
    public function index(Request $request)
    {
        $user = $request->user();
        return (new UserResource($user))
            ->additional(['message' => 'User Profile', 'status' => 200]);
    }

    /**
     * ✅ تحديث بيانات الملف الشخصي
     */
    public function updateProfile(UpdateProfileFormRequest $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'Unauthenticated',
            ], 401);
        }

        DB::beginTransaction();

        try {
            $inputs = $request->all();

            // تحديث حسابات التواصل
            $user->social()->updateOrCreate(
                ['user_id' => $user->id],
                $request->only('facebook', 'twitter', 'linkedin', 'youtube', 'instagram')
            );

            // تحديث البيانات الأساسية
            $user->update($inputs);

            // تحديث خيارات البزنس
            if ($request->filled('businessOptions')) {
                $options = explode(',', $request->businessOptions);
                $user->options()->sync($options);
            }

            DB::commit();

            return (new UserResource($user))
                ->additional(['status' => 200, 'message' => 'Profile updated successfully']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => 500,
                'message' => 'Failed to update profile',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ تحديث اللغة المفضلة للمستخدم
     */
    public function updateLanguage(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['status' => 401, 'message' => 'Unauthenticated'], 401);
        }

        $language = $request->header('lang', 'ar');
        $user->lang = $language;
        $user->save();

        return response()->json([
            'status' => 200,
            'message' => 'Language updated successfully',
            'lang' => $user->lang,
        ]);
    }

    /**
     * ✅ تسجيل الخروج (وحذف الجهاز الحالي)
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['status' => 401, 'message' => 'Unauthenticated'], 401);
        }

        // حذف التوكن الحالي فقط
        $request->user()->currentAccessToken()->delete();

        // حذف الجهاز من الجدول إن وجد
        if ($request->filled('deviceId')) {
            Device::where('device', $request->deviceId)
                ->where('user_id', $user->id)
                ->delete();
        }

        return response()->json([
            'status' => 200,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * ✅ تحديث رقم الهاتف بخطوتين:
     *  1. إرسال كود تحقق
     *  2. تأكيد الكود وتحديث الرقم
     */
    public function updatePhone(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['status' => 401, 'message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'newPhone' => 'required|string|unique:users,phone',
            'type' => 'required|string|in:check,confirm',
            'activationCode' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        // الخطوة الأولى: إرسال كود تحقق
        if ($request->type === 'check') {
            if ($user->is_active && !$user->is_suspend) {
                $code = rand(1000, 9999);
                $user->action_code = $user->actionCode($code);
                $user->save();

                return response()->json([
                    'status' => 200,
                    'message' => 'Activation code sent successfully',
                    'code' => $user->action_code, // يمكن حذفها في الإنتاج
                ]);
            }

            return response()->json([
                'status' => 400,
                'message' => 'User not active or suspended',
            ]);
        }

        // الخطوة الثانية: تأكيد الكود وتحديث الرقم
        if ($request->type === 'confirm') {
            if ($request->activationCode == $user->action_code) {
                $user->update(['phone' => $request->newPhone]);

                return response()->json([
                    'status' => 200,
                    'message' => __('trans.phoneUpdatedSuccessfully'),
                    'data' => new UserResource($user),
                ]);
            }

            return response()->json([
                'status' => 400,
                'message' => 'Incorrect activation code',
            ]);
        }

        return response()->json(['status' => 400, 'message' => 'Invalid request']);
    }

    /**
     * ✅ عرض بيانات مستخدم آخر (حسب ID)
     */
    public function getProfileInformation(Request $request)
    {
        $userId = (int) $request->userId;
        $user = User::find($userId);

        if (!$user) {
            return response()->json(['status' => 404, 'message' => 'User not found'], 404);
        }

        return (new UserResource($user))
            ->additional(['message' => 'User Information', 'status' => 200]);
    }
}
