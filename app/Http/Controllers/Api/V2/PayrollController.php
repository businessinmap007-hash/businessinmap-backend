<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\BusinessStaff;
use App\Models\Hr\Adjustment;
use App\Models\Hr\Advance;
use App\Models\Hr\Payslip;
use App\Models\User;
use App\Services\Hr\PayrollService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * «المرتبات» (capability payroll): advances in installments, a month's bonuses and penalties (each with its reason),
 * the payroll run — draft → approved and locked — and recording that each payslip was handed over outside the app.
 */
final class PayrollController extends Controller
{
    public function __construct(private readonly PayrollService $payroll) {}

    /** GET /business/hr/advances */
    public function advances(Request $request)
    {
        return $this->ok(['advances' => $this->named(Advance::query()->where('business_id', BusinessContext::id($request))->latest('id')->limit(200)->get())]);
    }

    /** POST /business/hr/advances — {user_id, amount, installments, first_month, note?} */
    public function storeAdvance(Request $request)
    {
        $businessId = BusinessContext::id($request);
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'installments' => ['required', 'integer', 'min:1', 'max:36'],
            'first_month' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);
        $this->staffOf($businessId, (int) $data['user_id']);

        $advance = Advance::create([
            'business_id' => $businessId,
            'user_id' => $data['user_id'],
            'amount' => round((float) $data['amount'], 2),
            'installments' => $data['installments'],
            'installment' => round((float) $data['amount'] / (int) $data['installments'], 2),
            'remaining' => round((float) $data['amount'], 2),
            'first_month' => $data['first_month'],
            'given_on' => now()->toDateString(),
            'note' => $data['note'] ?? null,
            'created_by' => (int) $request->user()->id,
        ]);

        return $this->ok(['advance' => $advance], 201);
    }

    /** GET /business/hr/adjustments?month= */
    public function adjustments(Request $request)
    {
        $rows = Adjustment::query()->where('business_id', BusinessContext::id($request))
            ->when($request->query('month'), fn ($q, $m) => $q->where('month', $m))->latest('id')->limit(300)->get();

        return $this->ok(['adjustments' => $this->named($rows)]);
    }

    /** POST /business/hr/adjustments — {user_id, month, kind bonus|penalty, amount, reason} */
    public function storeAdjustment(Request $request)
    {
        $businessId = BusinessContext::id($request);
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'month' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'kind' => ['required', Rule::in(['bonus', 'penalty'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:300'],
        ]);
        $this->staffOf($businessId, (int) $data['user_id']);
        $run = $this->payroll->run($businessId, $data['month']);
        if ($run && $run->status !== PayrollService::DRAFT) {
            abort(response()->json(['success' => false, 'message' => __('مسير هذا الشهر معتمد ومقفل؛ التعديل يكون في الشهر التالي.')], 422));
        }

        return $this->ok(['adjustment' => Adjustment::create(['business_id' => $businessId, 'created_by' => (int) $request->user()->id] + $data)], 201);
    }

    /** GET /business/hr/payroll/{month} — the run with its payslips (null when not generated yet) */
    public function show(Request $request, string $month)
    {
        $run = $this->payroll->run(BusinessContext::id($request), $month);

        return $this->ok(['run' => $run, 'payslips' => $run ? $this->named($run->payslips()->get()) : []]);
    }

    /** POST /business/hr/payroll/{month}/generate — (re)compute a draft from attendance, advances and adjustments */
    public function generate(Request $request, string $month)
    {
        $run = $this->payroll->generate(BusinessContext::id($request), $month);

        return $this->ok(['run' => $run, 'payslips' => $this->named($run->payslips()->get())]);
    }

    /** POST /business/hr/payroll/{month}/approve — the month locks; each employee sees his payslip */
    public function approve(Request $request, string $month)
    {
        $run = $this->payroll->run(BusinessContext::id($request), $month);
        abort_unless($run, 404);

        $run = $this->payroll->approve($run, (int) $request->user()->id);

        return $this->ok(['run' => $run, 'payslips' => $this->named($run->payslips()->get())]);
    }

    /** POST /business/hr/payslips/{payslip}/paid — {method cash|transfer|wallet_outside}: RECORDED, never moved by the app */
    public function paid(Request $request, int $payslip)
    {
        $slip = Payslip::query()->where('business_id', BusinessContext::id($request))->findOrFail($payslip);
        $data = $request->validate(['method' => ['required', Rule::in(['cash', 'transfer', 'wallet_outside'])]]);

        return $this->ok(['payslip' => $this->payroll->markPaid($slip, $data['method'], (int) $request->user()->id)]);
    }

    private function staffOf(int $businessId, int $userId): void
    {
        if (! BusinessStaff::query()->where('business_id', $businessId)->where('user_id', $userId)->exists()) {
            abort(404);
        }
    }

    private function named($rows)
    {
        $names = User::query()->whereIn('id', $rows->pluck('user_id'))->pluck('name', 'id');

        return $rows->map(fn ($r) => $r->toArray() + ['user_name' => $names[$r->user_id] ?? null])->values();
    }

    private function ok(array $data, int $status = 200)
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }
}
