<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\Business\StaffAttendanceQrService;
use App\Services\Business\StaffAttendanceService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;

/**
 * A staff member's own check-in/check-out, on the app itself - any active
 * capability qualifies (no specific one is required, see the bare
 * `business.member` route middleware), and the owner may mark their own
 * attendance the same way. The owner only ever READS this back, on the
 * grouped staff cards (BusinessStaffController::groups()).
 *
 * When the acting business opted into attendance_verification_enabled,
 * check-in/check-out also require a freshly scanned attendance QR code
 * (StaffAttendanceQrService) and GPS coordinates within range of the
 * business's own location - both validated before the plain check-in/out
 * logic ever runs. A business that never opts in sees no behaviour change
 * at all.
 */
class StaffAttendanceController extends Controller
{
    public function __construct(
        private readonly StaffAttendanceService $attendance,
        private readonly StaffAttendanceQrService $qr,
    ) {
    }

    /** POST /api/v2/staff/attendance/check-in */
    public function checkIn(Request $request)
    {
        $business = BusinessContext::business($request);
        $this->verifyIfRequired($request, $business);

        $attendance = $this->attendance->checkIn((int) $business->id, (int) $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => __('تم تسجيل الحضور.'),
            'data' => $this->serialize($attendance),
        ]);
    }

    /** POST /api/v2/staff/attendance/check-out */
    public function checkOut(Request $request)
    {
        $business = BusinessContext::business($request);
        $this->verifyIfRequired($request, $business);

        $attendance = $this->attendance->checkOut((int) $business->id, (int) $request->user()->id);

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

    /**
     * GET /api/v2/business/staff/attendance-qr — the code to show on the
     * business's own display device. Owner-only: this is what a staff
     * member is meant to scan, not something they should be able to fetch
     * and show themselves.
     */
    public function currentQr(Request $request)
    {
        abort_unless(BusinessContext::isOwner($request), 403, __('هذه الشاشة مخصصة لصاحب النشاط فقط.'));

        $token = $this->qr->currentFor((int) $request->user()->id);

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token->token,
                'expires_at' => $token->expires_at->toIso8601String(),
            ],
        ]);
    }

    /** GET /api/v2/business/staff/attendance-settings — owner-only read. */
    public function settings(Request $request)
    {
        abort_unless(BusinessContext::isOwner($request), 403, __('هذه الإعدادات مخصصة لصاحب النشاط فقط.'));

        return response()->json([
            'success' => true,
            'data' => ['enabled' => (bool) $request->user()->attendance_verification_enabled],
        ]);
    }

    /** PATCH /api/v2/business/staff/attendance-settings — owner-only toggle. */
    public function updateSettings(Request $request)
    {
        abort_unless(BusinessContext::isOwner($request), 403, __('هذه الإعدادات مخصصة لصاحب النشاط فقط.'));

        $data = $request->validate(['enabled' => ['required', 'boolean']]);

        $business = $request->user();
        $business->attendance_verification_enabled = $data['enabled'];
        $business->save();

        return response()->json([
            'success' => true,
            'data' => ['enabled' => (bool) $business->attendance_verification_enabled],
        ]);
    }

    private function verifyIfRequired(Request $request, $business): void
    {
        if (! $business->attendance_verification_enabled) {
            return;
        }

        $data = $request->validate([
            'qr_token' => ['required', 'string'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        // GPS first: a bad reading shouldn't burn an otherwise-valid code
        // the employee would just have to get a fresh scan for.
        $this->qr->assertWithinRange($business, (float) $data['lat'], (float) $data['lng']);
        $this->qr->redeem((int) $business->id, $data['qr_token'], (int) $request->user()->id);
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
