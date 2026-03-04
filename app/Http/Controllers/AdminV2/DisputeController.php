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

    return view('admin-v2.disputes.index', compact('rows'));
}
public function show(Deposit $deposit)
{
    // لو حابب تتأكد إنها نزاع
    // abort_unless((string)$deposit->status === 'dispute', 404);

    return view('admin-v2.disputes.show', compact('deposit'));
}
}