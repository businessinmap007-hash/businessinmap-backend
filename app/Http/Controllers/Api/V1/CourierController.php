<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use Illuminate\Http\Request;

class CourierController extends Controller
{
    public function __construct()
    {
        $language = request()->headers->get('lang') ?: 'ar';
        app()->setLocale($language);
    }

    /**
     * Helper: Ensure user is allowed courier (business + category=5)
     * and ensure courier row exists (auto create).
     */
    protected function ensureCourier(Request $request): Courier
    {
        $user = $request->user();

        if (!$user || $user->type !== 'business' || (int)$user->category_id !== 5) {
            abort(response()->json([
                'status'  => 403,
                'message' => 'Only Shipping & Delivery business (category=5) can access courier features.',
            ], 403));
        }

        // Auto create courier profile if missing
        $courier = Courier::firstOrCreate(
            ['user_id' => $user->id],
            ['is_active' => 1]
        );

        return $courier;
    }

    /**
     * Get my courier profile
     */
    public function myProfile(Request $request)
    {
        $courier = $this->ensureCourier($request);

        return response()->json([
            'status' => 200,
            'data'   => $courier,
        ]);
    }

    /**
     * Turn service ON/OFF
     */
    public function updateStatus(Request $request)
    {
        $courier = $this->ensureCourier($request);

        $data = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $courier->update([
            'is_active' => (bool)$data['is_active'],
        ]);

        return response()->json([
            'status' => 200,
            'message_ar' => 'تم تحديث حالة خدمة التوصيل',
            'message_en' => 'Courier service status updated',
            'data' => $courier,
        ]);
    }

    /**
     * Update live location (stored in couriers table)
     */
    public function updateLocation(Request $request)
    {
        $courier = $this->ensureCourier($request);

        $data = $request->validate([
            'location_lat' => 'required|numeric',
            'location_lng' => 'required|numeric',
        ]);

        $courier->update([
            'location_lat' => (float)$data['location_lat'],
            'location_lng' => (float)$data['location_lng'],
        ]);

        return response()->json([
            'status' => 200,
            'message_ar' => 'تم تحديث موقع المندوب',
            'message_en' => 'Courier location updated',
        ]);
    }
}
