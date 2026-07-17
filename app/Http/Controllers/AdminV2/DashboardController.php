<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BusinessServicePrice;
use App\Models\Category;
use App\Models\CategoryChild;
use App\Models\CategoryChildServiceFee;
use App\Models\Deposit;
use App\Models\PlatformService;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\AdminAbility;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        // BIM-14.1. The dashboard was the hole in the money boundary: it summed
        // platform fees and listed real transactions for anyone who could open
        // the panel, so gating the wallet screens alone would have moved the
        // leak rather than closed it. Money is computed only for those allowed
        // to see it — not merely hidden in the view, since a query that never
        // runs cannot leak.
        $canSeeMoney = (bool) $request->user()?->can(AdminAbility::MONEY);

        $stats = [
            'users' => $this->safeCount(User::class),
            'businesses' => $this->safeCount(User::class, fn ($q) => $q->where('type', 'business')),
            'clients' => $this->safeCount(User::class, fn ($q) => $q->where('type', 'client')),

            'categories' => $this->safeCount(Category::class),
            'category_children' => $this->safeCount(CategoryChild::class),

            'platform_services' => $this->safeCount(PlatformService::class),
            'business_service_prices' => $this->safeCount(BusinessServicePrice::class),
            'category_child_service_fees' => $this->safeCount(CategoryChildServiceFee::class),

            'bookings' => $this->safeCount(Booking::class),
            'open_disputes' => $this->safeCount(Deposit::class, fn ($q) => $q->where('status', 'dispute')),
            'wallet_transactions' => $canSeeMoney ? $this->safeCount(WalletTransaction::class) : null,
        ];

        $bookingStats = [
            'pending' => $this->safeCount(Booking::class, fn ($q) => $q->where('status', 'pending')),
            'confirmed' => $this->safeCount(Booking::class, fn ($q) => $q->where('status', 'confirmed')),
            'in_progress' => $this->safeCount(Booking::class, fn ($q) => $q->where('status', 'in_progress')),
            'completed' => $this->safeCount(Booking::class, fn ($q) => $q->where('status', 'completed')),
            'cancelled' => $this->safeCount(Booking::class, fn ($q) => $q->where('status', 'cancelled')),
        ];

        $walletStats = $canSeeMoney ? [
            'platform_fees' => $this->safeSum(
                WalletTransaction::class,
                'amount',
                fn ($q) => $q->where('type', 'platform_fee')->where('status', 'completed')
            ),
            'in_total' => $this->safeSum(
                WalletTransaction::class,
                'amount',
                fn ($q) => $q->where('direction', 'in')->where('status', 'completed')
            ),
            'out_total' => $this->safeSum(
                WalletTransaction::class,
                'amount',
                fn ($q) => $q->where('direction', 'out')->where('status', 'completed')
            ),
        ] : null;

        $latestBookings = $this->safeLatest(Booking::class, [
            'id',
            'user_id',
            'business_id',
            'service_id',
            'status',
            'total_price',
            'created_at',
        ], 8);

        $latestWalletTransactions = $canSeeMoney ? $this->safeLatest(WalletTransaction::class, [
            'id',
            'user_id',
            'wallet_id',
            'type',
            'direction',
            'amount',
            'status',
            'created_at',
        ], 8) : collect();

        $openDisputesCount = (int) ($stats['open_disputes'] ?? 0);

        return view('admin-v2.dashboard.index', [
            'stats' => $stats,
            'bookingStats' => $bookingStats,
            'walletStats' => $walletStats,
            'latestBookings' => $latestBookings,
            'latestWalletTransactions' => $latestWalletTransactions,
            'openDisputesCount' => $openDisputesCount,
        ]);
    }

    private function safeCount(string $modelClass, ?callable $callback = null): int
    {
        try {
            if (! class_exists($modelClass)) {
                return 0;
            }

            $query = $modelClass::query();

            if ($callback) {
                $callback($query);
            }

            return (int) $query->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function safeSum(string $modelClass, string $column, ?callable $callback = null): float
    {
        try {
            if (! class_exists($modelClass)) {
                return 0.0;
            }

            $query = $modelClass::query();

            if ($callback) {
                $callback($query);
            }

            return round((float) $query->sum($column), 2);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    private function safeLatest(string $modelClass, array $columns = ['*'], int $limit = 8)
    {
        try {
            if (! class_exists($modelClass)) {
                return collect();
            }

            return $modelClass::query()
                ->latest('id')
                ->limit($limit)
                ->get($columns);
        } catch (\Throwable $e) {
            return collect();
        }
    }
}