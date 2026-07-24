<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\MerchantPayment;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * AdminV2 oversight of customer→merchant payments (money-in that settles to the
 * merchant). Read-only: status, amount, which account it was routed to
 * (merchant vs platform), gateway reference — for reconciliation.
 */
class MerchantPaymentAdminController extends Controller
{
    /** GET admin/merchant-payments */
    public function index(Request $request): View
    {
        $status = trim((string) $request->get('status', ''));
        $q = trim((string) $request->get('q', ''));

        $rows = MerchantPayment::query()
            ->with(['customer:id,name,phone', 'business:id,name,phone'])
            ->when(in_array($status, [
                MerchantPayment::STATUS_PENDING, MerchantPayment::STATUS_PAID,
                MerchantPayment::STATUS_FAILED, MerchantPayment::STATUS_EXPIRED,
            ], true), fn ($x) => $x->where('status', $status))
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w
                ->where('merchant_ref', 'like', "%{$q}%")
                ->orWhere('gateway_ref', 'like', "%{$q}%")))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        $summary = MerchantPayment::query()
            ->selectRaw('status, COUNT(*) as c, COALESCE(SUM(amount),0) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return view('admin-v2.merchant-payments.index', [
            'rows' => $rows,
            'status' => $status,
            'q' => $q,
            'summary' => $summary,
        ]);
    }
}
