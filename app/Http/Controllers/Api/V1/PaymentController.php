<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Libraries\Main;
use App\Models\Category;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected $config;

    public function __construct(Main $config)
    {
        $this->config = $config;
    }

    /**
     * ✅ تنفيذ عملية اشتراك / تجديد
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user)
            return response()->json(['status' => 401, 'message' => 'Unauthenticated'], 401);

        // التحقق من الرصيد
        if ($this->config->calculateUserBalance($user) < $request->price) {
            return response()->json(['status' => 400, 'message' => 'الرصيد غير كافٍ']);
        }

        DB::beginTransaction();

        try {
            $inputs = $request->all();

            // كود الكوبون أو كود الملف الشخصي
            if ($request->filled('code')) {
                $inputs['coupon_id'] = $request->code;
                $inputs['code_type'] = $request->codeType ?? 'couponCode';
            }

            // اشتراك في حساب آخر
            if ($request->filled('profileCode')) {
                $targetUser = User::where('code', $request->profileCode)->first();
                if (!$targetUser)
                    return response()->json(['status' => 404, 'message' => 'المستخدم غير موجود']);

                // إلغاء الاشتراك الحالي إن وجد
                $monthDiff = 0;
                if ($sub = $targetUser->subscriptions()->where('is_active', 1)->first()) {
                    $monthDiff = Carbon::parse($sub->finished_at)->diffInMonths(Carbon::now());
                    $sub->update(['is_active' => 0]);
                }

                $inputs['category_id'] = optional($targetUser->category)->parent_id;
                $inputs['finished_at'] = Carbon::now()->addMonths($request->duration + $monthDiff)->toDateTimeString();

                $targetUser->subscriptions()->create($inputs);

                // سحب الرصيد من المستخدم الحالي
                $user->transactions()->create([
                    'status' => 'withdrawal',
                    'price' => $request->price,
                    'operation' => 'subscription',
                    'notes' => 'Subscription Another Account',
                    'target_id' => $targetUser->id,
                ]);

                $targetUser->update(['paid_at' => Carbon::now()]);

                DB::commit();
                return response()->json(['status' => 200, 'message' => 'تم الاشتراك في الحساب الآخر بنجاح']);
            }

            // اشتراك المستخدم بنفسه
            $monthDiff = 0;
            if ($sub = $user->subscriptions()->where('is_active', 1)->first()) {
                $monthDiff = Carbon::parse($sub->finished_at)->diffInMonths(Carbon::now());
                $sub->update(['is_active' => 0]);
            }

            $inputs['category_id'] = optional($user->category)->parent_id;
            $inputs['finished_at'] = Carbon::now()->addMonths($request->duration + $monthDiff)->toDateTimeString();

            $subscription = $user->subscriptions()->create($inputs);

            // عملية الخصم
            $user->transactions()->create([
                'status' => 'withdrawal',
                'price' => $request->price,
                'operation' => 'subscription',
                'notes' => 'Subscription Account',
                'target_id' => null,
            ]);

            // معالجة كود البروفايل
            if ($request->codeType === 'profileCode' && $request->filled('code')) {
                $owner = User::where('code', $request->code)->first();
                if (!$owner) {
                    DB::rollBack();
                    return response()->json(['status' => 400, 'message' => 'كود البروفايل المستخدم غير موجود']);
                }

                if ($owner->id === $user->id) {
                    return response()->json(['status' => 400, 'message' => 'لا يمكنك استخدام كود البروفايل الخاص بك']);
                }

                $category = $request->filled('categoryId')
                    ? Category::find($request->categoryId)
                    : optional($user->category)->parent;

                $cost = $category ? ($request->duration >= 12 ? $category->per_year : $category->per_month) : 0;

                $setting = new Setting;
                $commissionMonths = $owner->gifts?->commission_months ?? $setting->getBody('commission_months');
                $costPerMonth = $request->duration >= 12 ? $cost / 12 : $cost;
                $ownerCommission = $costPerMonth * $commissionMonths * ($request->duration / 12);

                $owner->transactions()->create([
                    'status' => 'deposit',
                    'price' => sprintf('%.2f', $ownerCommission),
                    'operation' => 'award',
                    'notes' => 'From Registration By Profile Code - ' . $user->code,
                    'target_id' => $user->id,
                ]);
            }

            DB::commit();
            return response()->json(['status' => 200, 'message' => 'تم الاشتراك بنجاح']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Payment Error: ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => 'حدث خطأ أثناء العملية']);
        }
    }

    /**
     * ✅ تحويل رصيد إلى مستخدم آخر
     */
    public function transferToAnother(Request $request)
    {
        $user = $request->user();
        $target = User::where('code', $request->profileCode)->first();

        if (!$target)
            return response()->json(['status' => 404, 'message' => 'المستخدم غير موجود']);

        if ($this->config->calculateUserBalance($user) < $request->price)
            return response()->json(['status' => 400, 'message' => 'الرصيد غير كافٍ']);

        DB::transaction(function () use ($user, $target, $request) {
            $target->transactions()->create([
                'status' => 'deposit',
                'price' => $request->price,
                'operation' => 'transfer',
                'notes' => 'Receiver Transfer From Another Account',
                'target_id' => $user->id,
            ]);

            $user->transactions()->create([
                'status' => 'withdrawal',
                'price' => $request->price,
                'operation' => 'transfer',
                'notes' => 'Transfer To Another Account',
                'target_id' => $target->id,
            ]);
        });

        return response()->json(['status' => 200, 'message' => 'تم التحويل بنجاح']);
    }

    /**
     * ✅ نجاح الدفع عبر فوري أو البطاقة
     */
    public function fawrySuccessPayment(Request $request)
    {
        Log::notice('Fawry Callback Received', $request->all());

        if (!$request->merchantRefNumber)
            return response()->json(['status' => 400, 'message' => 'لا يوجد رقم مرجعي']);

        $payment = Payment::find($request->merchantRefNumber);
        if (!$payment)
            return response()->json(['status' => 404, 'message' => 'الطلب غير موجود']);

        if ($payment->paid_at)
            return response()->json(['status' => 400, 'message' => 'تم الدفع مسبقًا']);

        $paid = strtoupper($request->paymentMethod) === 'PAYATFAWRY'
            ? $this->paymentAtFawry($request, $payment)
            : $this->paymentByCard($request, $payment);

        if ($paid)
            return response()->json(['status' => 200, 'message' => 'تمت العملية بنجاح']);

        return response()->json(['status' => 400, 'message' => 'فشل تنفيذ العملية']);
    }

    /**
     * ✅ معالجة الدفع بالبطاقة
     */
    private function paymentByCard($response, Payment $payment)
    {
        if ($payment->operation_type === 'recharge') {
            $transaction = $payment->user->transactions()->create([
                'status' => 'deposit',
                'price' => $payment->price,
                'operation' => 'recharge',
                'notes' => 'Charge Account by Card',
                'target_id' => null,
            ]);

            $payment->update([
                'payment_type' => strtoupper($response->paymentMethod),
                'payment_no' => $response->referenceNumber,
                'operation_id' => $transaction->id,
                'paid_at' => Carbon::now(),
            ]);

            return true;
        }

        $subscription = Subscription::find($payment->operation_id);
        if (!$subscription) return false;

        $active = $payment->user->subscriptions()->where('is_active', 1)->first();
        if ($active) $active->update(['is_active' => 0]);

        $subscription->update([
            'finished_at' => Carbon::now()->addMonths($subscription->duration)->toDateTimeString(),
            'is_active' => 1,
        ]);

        $payment->update([
            'payment_type' => strtoupper($response->paymentMethod),
            'payment_no' => $response->referenceNumber,
            'paid_at' => Carbon::now(),
        ]);

        return true;
    }

    /**
     * ✅ معالجة الدفع بفوري
     */
    private function paymentAtFawry($response, Payment $payment)
    {
        if ($this->isFawryNumberExists($response->referenceNumber)) {
            return false;
        }

        if ($payment->operation_type === 'recharge') {
            $transaction = $payment->user->transactions()->create([
                'status' => 'deposit',
                'price' => $payment->price,
                'operation' => 'recharge',
                'notes' => 'Charge Account by Fawry',
                'target_id' => null,
            ]);

            $payment->update([
                'operation_id' => $transaction->id,
                'paid_at' => Carbon::now(),
                'payment_no' => $response->referenceNumber,
                'payment_type' => strtoupper($response->paymentMethod),
            ]);

            return true;
        }

        $subscription = Subscription::find($payment->operation_id);
        if (!$subscription) return false;

        $active = $payment->user->subscriptions()->where('is_active', 1)->first();
        if ($active) $active->update(['is_active' => 0]);

        $subscription->update([
            'finished_at' => Carbon::now()->addMonths($subscription->duration)->toDateTimeString(),
            'is_active' => 1,
        ]);

        $payment->update(['paid_at' => Carbon::now()]);
        return true;
    }

    /**
     * ✅ فحص وجود رقم فوري مسبقًا
     */
    private function isFawryNumberExists($number): bool
    {
        return Payment::where('payment_no', $number)->exists();
    }
}
