<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\BusinessBookingSetting;
use Illuminate\Http\Request;

/**
 * A stay's own check-in/check-out clock — separate from the business's
 * general working hours (a hotel's front desk can open at 9am while guests
 * only check in from 3pm). The only two `business_booking_settings` columns
 * the app writes; every other field on that row is web-panel-only for now
 * (App\Http\Controllers\Business\BookingSettingsController).
 */
final class BusinessBookingCheckTimesController extends Controller
{
    use ResolvesOwnerCatalog;

    /** GET /api/v2/business/booking-settings/check-times */
    public function show()
    {
        $row = BusinessBookingSetting::query()->where('business_id', $this->businessId())->first();

        return response()->json([
            'success' => true,
            'data' => [
                'check_in_time' => $row?->check_in_time,
                'check_out_time' => $row?->check_out_time,
            ],
        ]);
    }

    /** PUT /api/v2/business/booking-settings/check-times */
    public function update(Request $request)
    {
        $data = $request->validate([
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
        ]);

        $row = BusinessBookingSetting::query()->updateOrCreate(
            ['business_id' => $this->businessId()],
            [
                'check_in_time' => $data['check_in_time'] ?? null,
                'check_out_time' => $data['check_out_time'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'check_in_time' => $row->check_in_time,
                'check_out_time' => $row->check_out_time,
            ],
        ]);
    }
}
