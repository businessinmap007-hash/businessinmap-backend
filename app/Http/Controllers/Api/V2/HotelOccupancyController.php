<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\Hotels\OccupancyBoard;
use App\Support\BusinessContext;
use Illuminate\Http\Request;

/** «شاشة الغرف»: the hotel's month board — every room type, day by day, what is taken and what is free. */
class HotelOccupancyController extends Controller
{
    /** GET business/occupancy?month=YYYY-MM */
    public function index(Request $request, OccupancyBoard $board)
    {
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        return response()->json(['success' => true, 'data' => $board->month(BusinessContext::id($request), $data['month'] ?? now()->format('Y-m'))]);
    }
}
