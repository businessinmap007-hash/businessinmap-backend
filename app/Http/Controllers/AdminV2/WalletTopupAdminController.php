<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\WalletTopup;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * AdminV2 oversight of wallet top-ups (money-in). Read-only: status, amount,
 * gateway reference, method — for reconciliation against the gateway.
 */
class WalletTopupAdminController extends Controller
{
    /** GET admin/wallet-topups */
    public function index(Request $request): View
    {
        $status = trim((string) $request->get('status', ''));
        $q = trim((string) $request->get('q', ''));

        $rows = WalletTopup::query()
            ->with('user:id,name,email,phone')
            ->when(in_array($status, [
                WalletTopup::STATUS_PENDING, WalletTopup::STATUS_PAID,
                WalletTopup::STATUS_FAILED, WalletTopup::STATUS_EXPIRED,
            ], true), fn ($x) => $x->where('status', $status))
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w
                ->where('merchant_ref', 'like', "%{$q}%")
                ->orWhere('gateway_ref', 'like', "%{$q}%")))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        $summary = WalletTopup::query()
            ->selectRaw('status, COUNT(*) as c, COALESCE(SUM(amount),0) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return view('admin-v2.wallet-topups.index', [
            'rows' => $rows,
            'status' => $status,
            'q' => $q,
            'summary' => $summary,
        ]);
    }
}
