<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class WalletOverviewController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $type = trim((string) $request->get('type', ''));
        $walletStatus = trim((string) $request->get('wallet_status', ''));
        $amountFilter = trim((string) $request->get('amount_filter', ''));
        $perPage = (int) $request->get('per_page', 50);
        $perPage = in_array($perPage, [20, 50, 100, 200], true) ? $perPage : 50;

        $query = Wallet::query()
            ->with(['user:id,name,email,phone,type,logo,image'])
            ->withCount('transactions');

        if ($q !== '') {
            $query->where(function (Builder $w) use ($q) {
                if (is_numeric($q)) {
                    $w->orWhere('id', (int) $q)
                        ->orWhere('user_id', (int) $q);
                }

                $w->orWhereHas('user', function (Builder $u) use ($q) {
                    $u->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                });
            });
        }

        if ($type !== '' && in_array($type, ['client', 'business', 'admin'], true)) {
            $query->whereHas('user', fn (Builder $u) => $u->where('type', $type));
        }

        if ($walletStatus !== '' && in_array($walletStatus, [Wallet::STATUS_ACTIVE, Wallet::STATUS_BLOCKED], true)) {
            $query->where('status', $walletStatus);
        }

        if ($amountFilter === 'with_amount') {
            $query->where(function (Builder $w) {
                $w->where('balance', '>', 0)->orWhere('locked_balance', '>', 0);
            });
        } elseif ($amountFilter === 'zero') {
            $query->where('balance', '<=', 0)->where('locked_balance', '<=', 0);
        }

        $totals = [
            'wallets' => Wallet::query()->count(),
            'active' => Wallet::query()->where('status', Wallet::STATUS_ACTIVE)->count(),
            'blocked' => Wallet::query()->where('status', Wallet::STATUS_BLOCKED)->count(),
            'balance' => round((float) Wallet::query()->sum('balance'), 2),
            'locked' => round((float) Wallet::query()->sum('locked_balance'), 2),
            'total_in' => round((float) Wallet::query()->sum('total_in'), 2),
            'total_out' => round((float) Wallet::query()->sum('total_out'), 2),
        ];

        $rows = $query
            ->orderByDesc('balance')
            ->orderByDesc('locked_balance')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin-v2.wallet-overview.index', [
            'rows' => $rows,
            'totals' => $totals,
            'q' => $q,
            'type' => $type,
            'walletStatus' => $walletStatus,
            'amountFilter' => $amountFilter,
            'perPage' => $perPage,
        ]);
    }
}
