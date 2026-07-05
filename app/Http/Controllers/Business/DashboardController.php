<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use App\Models\Booking;
use App\Models\BusinessServicePrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Landing screen for the business-owner panel. Everything is scoped to the
 * logged-in owner (business_id === auth id).
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $businessId = (int) Auth::id();

        $stats = [
            'bookable_items' => BookableItem::query()->where('business_id', $businessId)->count(),
            'active_items' => BookableItem::query()->where('business_id', $businessId)->where('is_active', 1)->count(),
            'prices' => BusinessServicePrice::query()->where('business_id', $businessId)->count(),
            'bookings' => Booking::query()->where('business_id', $businessId)->count(),
        ];

        return view('business.dashboard', [
            'user' => Auth::user(),
            'stats' => $stats,
        ]);
    }
}
