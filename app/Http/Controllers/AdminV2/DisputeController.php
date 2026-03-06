<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\Booking;

class DisputeController extends Controller
{
    public function index()
    {
        $rows = Deposit::query()
            ->where('status', 'dispute')
            ->where('target_type', Booking::class)
            ->leftJoin('bookings', 'bookings.id', '=', 'deposits.target_id')
            ->select('deposits.*', 'bookings.id as booking_exists')
            ->orderByDesc('deposits.id')
            ->paginate(50);

        // ✅ موحّد مع admin_v2
        return view('admin_v2.disputes.index', compact('rows'));
    }
}