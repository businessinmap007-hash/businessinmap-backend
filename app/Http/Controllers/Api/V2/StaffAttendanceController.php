<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\Business\StaffAttendanceService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;

/**
 * A staff member's own check-in/check-out, on the app itself - any active
 * capability qualifies (no specific one is required, see the bare
 * `business.member` route middleware), and the owner may mark their own
 * attendance the same way. The owner only ever READS this back, on the
 * grouped staff cards (BusinessStaffController::groups()).
 */
class StaffAttendanceController extends Controller
{
    public function __construct(private readonly StaffAttendanceService $attendance)
    {
    }

    /** POST /api/v2/staff/attendance/check-in */
    public function checkIn(Request $request)
    {
        $attendance = $this->attendance->checkIn(BusinessContext::id($request), (int) $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => __('تم تسجيل الحضور.'),
            'data' => $this->serialize($attendance),
        ]);
    }

    /** POST /api/v2/staff/attendance/check-out */
    public function checkOut(Request $request)
    {
        $attendance = $this->attendance->checkOut(BusinessContext::id($request), (int) $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => __('تم تسجيل الانصراف.'),
            'data' => $this->serialize($attendance),
        ]);
    }

    /** GET /api/v2/staff/attendance/today — my own status for the acting business. */
    public function today(Request $request)
    {
        $attendance = $this->attendance->todayFor(BusinessContext::id($request), (int) $request->user()->id);

        return response()->json([
            'success' => true,
            'data' => $attendance ? $this->serialize($attendance) : null,
        ]);
    }

    private function serialize($attendance): array
    {
        return [
            'checked_in_at' => optional($attendance->checked_in_at)->toIso8601String(),
            'checked_out_at' => optional($attendance->checked_out_at)->toIso8601String(),
            'is_present' => $attendance->isPresent(),
        ];
    }
}
