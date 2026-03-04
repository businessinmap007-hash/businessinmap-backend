<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Deposit;

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

        return view('admin-v2.disputes.index', compact('rows'));
    }

    public function show(Booking $booking)
    {
        // هات أحدث Deposit للحجز بشرط أنه نزاع
        $deposit = Deposit::query()
            ->where('target_type', Booking::class)
            ->where('target_id', (int) $booking->id)
            ->where('status', 'dispute')
            ->orderByDesc('id')
            ->first();

        abort_unless($deposit, 404);

        return view('admin-v2.disputes.show', compact('booking', 'deposit'));
    }
}