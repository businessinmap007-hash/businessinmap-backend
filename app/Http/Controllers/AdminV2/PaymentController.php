<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Wallet;
use App\Services\Wallet\WalletLedgerService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class PaymentController extends Controller
{
    /**
     * ✅ Admin list (اختياري)
     */
    public function index(Request $request)
    {
        $q = trim((string)$request->get('q', ''));

        $items = Payment::query()
            ->with(['user:id,name'])
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where('id', $q)
                   ->orWhere('user_id', $q)
                   ->orWhere('payment_no', 'like', "%{$q}%")
                   ->orWhere('payment_type', 'like', "%{$q}%")
                   ->orWhere('operation_type', 'like', "%{$q}%");
            })
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin-v2.payments.index', compact('items','q'));
    }

    /**
     * ✅ Confirm paid (يدوي من الأدمن)
     * - لو payment مدفوع بالفعل: يمنع التكرار (والـ ledger أصلاً هيمنعه بالـ idempotency)
     */
    public function confirm(Request $request, int $paymentId, WalletLedgerService $ledger)
    {
        $noteId = (int) $request->get('note_id', 0); // optional template id

        $payment = Payment::query()->findOrFail($paymentId);

        abort_unless($payment->paid_at, 422, 'Payment not paid yet');

        $this->applyBusinessEffect($payment, $ledger, $noteId, [
            'source' => 'admin_confirm',
        ]);

        return back()->with('success', 'تم تأكيد الدفع وتنفيذ العملية');
    }

    /**
     * ✅ Callback Success (من فوري/كارت)
     * نفس وظيفة fawrySuccessPayment القديمة لكن بنظام المحفظة الجديد.
     *
     * توقعنا incoming:
     * - merchantRefNumber = payment id
     * - paymentMethod
     * - referenceNumber
     */
    public function callbackSuccess(Request $request, WalletLedgerService $ledger)
    {
        Log::notice('Payment Callback Received (AdminV2)', $request->all());

        $paymentId = (int)($request->merchantRefNumber ?? 0);
        if ($paymentId <= 0) {
            return response()->json(['status' => 400, 'message' => 'لا يوجد رقم مرجعي'], 400);
        }

        $payment = Payment::query()->find($paymentId);
        if (!$payment) {
            return response()->json(['status' => 404, 'message' => 'الطلب غير موجود'], 404);
        }

        // ✅ منع تكرار رقم المرجع (فوري)
        $referenceNumber = (string)($request->referenceNumber ?? '');
        if ($referenceNumber !== '' && Payment::where('payment_no', $referenceNumber)->where('id', '<>', $payment->id)->exists()) {
            return response()->json(['status' => 400, 'message' => 'رقم العملية مكرر'], 400);
        }

        // ✅ لو مدفوع بالفعل، نرجع OK (idempotent)
        if ($payment->paid_at) {
            return response()->json(['status' => 200, 'message' => 'تم الدفع مسبقًا'], 200);
        }

        $method = strtoupper((string)($request->paymentMethod ?? ''));
        $paidAt = Carbon::now();

        DB::transaction(function () use ($payment, $method, $referenceNumber, $paidAt) {
            $payment->update([
                'payment_type' => $method !== '' ? $method : $payment->payment_type,
                'payment_no'   => $referenceNumber !== '' ? $referenceNumber : $payment->payment_no,
                'paid_at'      => $paidAt,
            ]);
        });

        // ✅ تنفيذ أثر الدفع (شحن/اشتراك) بعد تحديث payment
        $this->applyBusinessEffect($payment->fresh(), $ledger, 0, [
            'gateway_method' => $method,
            'gateway_ref'    => $referenceNumber,
            'source'         => 'gateway_callback',
        ]);

        return response()->json(['status' => 200, 'message' => 'تمت العملية بنجاح']);
    }

    /**
     * ✅ مكان واحد لتنفيذ أثر الدفع (Recharge / Subscription)
     */
    private function applyBusinessEffect(Payment $payment, WalletLedgerService $ledger, int $noteId = 0, array $extraMeta = []): void
    {
        // ✅ recharge => شحن محفظة
        if ($payment->operation_type === 'recharge') {

            $wallet = Wallet::query()->where('user_id', $payment->user_id)->firstOrFail();

            $idem = 'pay:' . $payment->id; // ✅ idempotency ثابت

            DB::transaction(function () use ($ledger, $wallet, $payment, $idem, $noteId, $extraMeta) {

                $ledger->deposit(
                    walletId: (int)$wallet->id,
                    userId: (int)$payment->user_id,
                    amount: (float)$payment->price,
                    op: [
                        'idempotency_key' => $idem,
                        'reference_type'  => 'payments',
                        'reference_id'    => (string)$payment->id,
                        'note_id'         => $noteId, // ممكن يكون 0
                        'meta'            => array_merge([
                            'payment_type'   => (string)$payment->payment_type,
                            'payment_no'     => (string)$payment->payment_no,
                            'operation_type' => (string)$payment->operation_type,
                            'operation_id'   => (string)$payment->operation_id,
                        ], $extraMeta),
                    ]
                );

                // ✅ اختياري: خزّن في payments رقم wallet_transaction اللي اتولد (لو عندك عمود operation_id يستعملها)
                // لو operation_id تستخدمه لشيء آخر لا تعدله.
                // $payment->update(['operation_id' => $walletTxId]);
            });

            return;
        }

        // ✅ subscription => تفعيل اشتراك (مثل القديم)
        if ($payment->operation_type === 'subscription') {

            $sub = Subscription::query()->find($payment->operation_id);
            if (!$sub) {
                // لا نرمي exception عشان callback ما يفشلش لو subscription اتحذف
                Log::warning('Subscription not found for payment', ['payment_id' => $payment->id, 'operation_id' => $payment->operation_id]);
                return;
            }

            DB::transaction(function () use ($payment, $sub) {
                // إلغاء الحالي
                $active = $payment->user?->subscriptions()?->where('is_active', 1)->first();
                if ($active) $active->update(['is_active' => 0]);

                $sub->update([
                    'finished_at' => Carbon::now()->addMonths((int)$sub->duration)->toDateTimeString(),
                    'is_active'   => 1,
                ]);
            });

            return;
        }

        // ✅ غير معروف
        Log::warning('Unknown payment operation_type', ['payment_id' => $payment->id, 'operation_type' => $payment->operation_type]);
    }
}