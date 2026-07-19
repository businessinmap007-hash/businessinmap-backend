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

        return view('admin-v2.payments.index', compact('items', 'q'));
    }

    public function confirm(Request $request, int $paymentId, WalletLedgerService $ledger)
    {
        $noteId = (int) $request->get('note_id', 0);

        $payment = Payment::query()->findOrFail($paymentId);

        if ((int) $payment->user_id <= 0) {
            return back()->withErrors(__('لا يمكن تأكيد الدفع لأن payment لا يحتوي على user_id.'));
        }

        if ((float) $payment->price <= 0) {
            return back()->withErrors(__('لا يمكن تأكيد الدفع لأن قيمة payment غير صحيحة.'));
        }

        if (! $payment->paid_at) {
            $payment->update([
                'paid_at' => now(),
                'payment_type' => $payment->payment_type ?: 'manual_admin',
                'payment_no' => $payment->payment_no ?: ('ADMIN-' . $payment->id),
            ]);
            $payment = $payment->fresh();
        }

        $this->applyBusinessEffect($payment, $ledger, $noteId, [
            'source' => 'admin_confirm',
            'admin_id' => auth()->id(),
        ]);

        return back()->with('success', __('تم تأكيد الدفع وتنفيذ العملية.'));
    }

    public function callbackSuccess(Request $request, WalletLedgerService $ledger)
    {
        Log::notice('Payment Callback Received (AdminV2)', $request->all());

        $paymentId = (int)($request->merchantRefNumber ?? 0);
        if ($paymentId <= 0) {
            return response()->json(['status' => 400, 'message' => __('لا يوجد رقم مرجعي')], 400);
        }

        $payment = Payment::query()->find($paymentId);
        if (! $payment) {
            return response()->json(['status' => 404, 'message' => __('الطلب غير موجود')], 404);
        }

        if ((int) $payment->user_id <= 0) {
            return response()->json(['status' => 422, 'message' => __('payment user_id مفقود')], 422);
        }

        $referenceNumber = (string)($request->referenceNumber ?? '');
        if ($referenceNumber !== '' && Payment::where('payment_no', $referenceNumber)->where('id', '<>', $payment->id)->exists()) {
            return response()->json(['status' => 400, 'message' => __('رقم العملية مكرر')], 400);
        }

        if ($payment->paid_at) {
            return response()->json(['status' => 200, 'message' => __('تم الدفع مسبقًا')], 200);
        }

        $method = strtoupper((string)($request->paymentMethod ?? ''));
        $paidAt = Carbon::now();

        DB::transaction(function () use ($payment, $method, $referenceNumber, $paidAt) {
            $payment->update([
                'payment_type' => $method !== '' ? $method : ($payment->payment_type ?: 'gateway'),
                'payment_no' => $referenceNumber !== '' ? $referenceNumber : $payment->payment_no,
                'paid_at' => $paidAt,
            ]);
        });

        $this->applyBusinessEffect($payment->fresh(), $ledger, 0, [
            'gateway_method' => $method,
            'gateway_ref' => $referenceNumber,
            'source' => 'gateway_callback',
        ]);

        return response()->json(['status' => 200, 'message' => __('تمت العملية بنجاح')]);
    }

    private function applyBusinessEffect(Payment $payment, WalletLedgerService $ledger, int $noteId = 0, array $extraMeta = []): void
    {
        if ($payment->operation_type === 'recharge') {
            $wallet = Wallet::query()->firstOrCreate(
                ['user_id' => (int) $payment->user_id],
                ['balance' => 0, 'locked_balance' => 0, 'status' => 'active']
            );

            $idem = 'pay:' . $payment->id;

            $ledger->deposit(
                walletId: (int) $wallet->id,
                userId: (int) $payment->user_id,
                amount: (float) $payment->price,
                op: [
                    'idempotency_key' => $idem,
                    'reference_type' => 'payments',
                    'reference_id' => (string) $payment->id,
                    'note_id' => $noteId,
                    'meta' => array_merge([
                        'payment_type' => (string) $payment->payment_type,
                        'payment_no' => (string) $payment->payment_no,
                        'operation_type' => (string) $payment->operation_type,
                        'operation_id' => (string) $payment->operation_id,
                    ], $extraMeta),
                ]
            );

            return;
        }

        if ($payment->operation_type === 'subscription') {
            $sub = Subscription::query()->find($payment->operation_id);
            if (! $sub) {
                Log::warning('Subscription not found for payment', [
                    'payment_id' => $payment->id,
                    'operation_id' => $payment->operation_id,
                ]);
                return;
            }

            DB::transaction(function () use ($payment, $sub) {
                $active = $payment->user?->subscriptions()?->where('is_active', 1)->first();
                if ($active) {
                    $active->update(['is_active' => 0]);
                }

                $sub->update([
                    'finished_at' => Carbon::now()->addMonths((int) $sub->duration)->toDateTimeString(),
                    'is_active' => 1,
                ]);
            });

            return;
        }

        Log::warning('Unknown payment operation_type', [
            'payment_id' => $payment->id,
            'operation_type' => $payment->operation_type,
        ]);
    }
}
