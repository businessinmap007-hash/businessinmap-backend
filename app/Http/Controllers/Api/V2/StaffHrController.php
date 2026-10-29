<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrRequest;
use App\Models\Hr\Payslip;
use App\Models\Hr\PayrollRun;
use App\Services\Hr\AttendanceCalculator;
use App\Services\Hr\HrRequestService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;

/**
 * «عملي» — the employee's own side on his phone: his month (days, lateness, overtime), his leave balance, his requests
 * and his payslips once the month is approved. Never anyone else's.
 */
final class StaffHrController extends Controller
{
    public function __construct(
        private readonly AttendanceCalculator $attendance,
        private readonly HrRequestService $requests,
    ) {}

    /** GET /staff/hr/me?month=YYYY-MM */
    public function me(Request $request)
    {
        $businessId = BusinessContext::id($request);
        $userId = (int) $request->user()->id;
        $month = (string) $request->query('month', now()->format('Y-m'));
        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month), 422, __('الشهر بصيغة YYYY-MM.'));

        $approvedRuns = PayrollRun::query()->where('business_id', $businessId)->where('status', 'approved')->pluck('month', 'id');

        return response()->json(['success' => true, 'data' => [
            'month' => $month,
            'attendance' => $this->attendance->month($businessId, $userId, $month),
            'leave' => $this->requests->leaveBalance($businessId, $userId),
            'requests' => HrRequest::query()->where('business_id', $businessId)->where('user_id', $userId)->latest('id')->limit(30)->get(),
            'payslips' => Payslip::query()->where('business_id', $businessId)->where('user_id', $userId)->whereIn('run_id', $approvedRuns->keys())
                ->latest('id')->limit(12)->get()->map(fn ($p) => $p->toArray() + ['month' => $approvedRuns[$p->run_id] ?? null])->values(),
        ]]);
    }

    /** POST /staff/hr/requests — leave, a punch correction, or overtime */
    public function store(Request $request)
    {
        $data = $request->validate(HrController::requestRules());
        $made = $this->requests->create(BusinessContext::id($request), (int) $request->user()->id, $data);

        return response()->json(['success' => true, 'data' => ['request' => $made]], 201);
    }

    /** POST /staff/hr/requests/{hrRequest}/cancel — while still pending */
    public function cancel(Request $request, int $hrRequest)
    {
        $model = HrRequest::query()->where('business_id', BusinessContext::id($request))->where('user_id', $request->user()->id)->findOrFail($hrRequest);
        abort_unless($model->status === 'pending', 422, __('تم الرد على هذا الطلب من قبل.'));
        $model->update(['status' => 'cancelled']);

        return response()->json(['success' => true, 'data' => ['request' => $model->fresh()]]);
    }
}
