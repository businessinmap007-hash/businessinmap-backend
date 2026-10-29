<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\BusinessStaff;
use App\Models\Hr\Employee;
use App\Models\Hr\Holiday;
use App\Models\Hr\HrRequest;
use App\Models\Hr\LawTable;
use App\Models\Hr\Shift;
use App\Models\User;
use App\Services\Hr\AttendanceCalculator;
use App\Services\Hr\HrRequestService;
use App\Services\Hr\HrRules;
use App\Support\BusinessCapability;
use App\Support\BusinessContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * «الحضور» — the manager's side (capability attendance): the business's own rules, shifts, each employee's file
 * (shift, rest days, hire date — salary fields need the payroll capability), the month's board, holidays and the
 * requests queue. The law tables are read-only here.
 */
final class HrController extends Controller
{
    public function __construct(
        private readonly HrRules $rules,
        private readonly AttendanceCalculator $attendance,
        private readonly HrRequestService $requests,
    ) {}

    /** GET /business/hr/settings */
    public function settings(Request $request)
    {
        return $this->ok(['settings' => $this->rules->settings(BusinessContext::id($request))]);
    }

    /** PATCH /business/hr/settings */
    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'grace_minutes' => ['sometimes', 'integer', 'min:0', 'max:180'],
            'late_tiers' => ['sometimes', 'array', 'min:1', 'max:6'],
            'late_tiers.*.upto_minutes' => ['nullable', 'integer', 'min:1'],
            'late_tiers.*.day_fraction' => ['required', 'numeric', 'min:0', 'max:3'],
            'absence_day_fraction' => ['sometimes', 'numeric', 'min:0', 'max:3'],
            'monthly_cap_days' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:31'],
            'overtime_day_pct' => ['sometimes', 'numeric', 'min:0', 'max:300'],
            'overtime_night_pct' => ['sometimes', 'numeric', 'min:0', 'max:300'],
            'night_from' => ['sometimes', 'date_format:H:i'],
            'night_to' => ['sometimes', 'date_format:H:i'],
            'days_per_month' => ['sometimes', 'integer', 'min:20', 'max:31'],
            'hours_per_day' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'rest_days' => ['sometimes', 'array', 'max:3'],
            'rest_days.*' => ['integer', 'between:0,6'],
        ]);

        // the business may pay more than the law, never less
        $law = $this->rules->law('overtime', now());
        foreach (['overtime_day_pct' => 'day_min_pct', 'overtime_night_pct' => 'night_min_pct'] as $field => $min) {
            if (isset($data[$field]) && (float) $data[$field] < (float) $law[$min]) {
                abort(response()->json(['success' => false, 'message' => __('نسبة الإضافي أقل من الحد القانوني (:min%).', ['min' => $law[$min]]),
                    'errors' => [$field => [__('نسبة الإضافي أقل من الحد القانوني (:min%).', ['min' => $law[$min]])]]], 422));
            }
        }

        $settings = $this->rules->settings(BusinessContext::id($request));
        $settings->update($data);

        return $this->ok(['settings' => $settings->fresh()]);
    }

    /** GET /business/hr/law-tables — the law's numbers in force today (read-only) */
    public function lawTables()
    {
        return $this->ok(['tables' => LawTable::query()->orderBy('kind')->orderByDesc('effective_from')->get(['kind', 'effective_from', 'data', 'source'])]);
    }

    // ── shifts & holidays ──

    /** GET /business/hr/shifts */
    public function shifts(Request $request)
    {
        return $this->ok(['shifts' => Shift::query()->where('business_id', BusinessContext::id($request))->orderBy('starts_at')->get()]);
    }

    /** POST /business/hr/shifts */
    public function storeShift(Request $request)
    {
        $data = $request->validate($this->shiftRules(true));

        return $this->ok(['shift' => Shift::create(['business_id' => BusinessContext::id($request)] + $data)], 201);
    }

    /** PATCH /business/hr/shifts/{shift} */
    public function updateShift(Request $request, int $shift)
    {
        $model = Shift::query()->where('business_id', BusinessContext::id($request))->findOrFail($shift);
        $model->update($request->validate($this->shiftRules(false)));

        return $this->ok(['shift' => $model->fresh()]);
    }

    /** GET /business/hr/holidays?year= */
    public function holidays(Request $request)
    {
        $year = (int) $request->query('year', now()->year);

        return $this->ok(['holidays' => Holiday::query()->where('business_id', BusinessContext::id($request))->whereYear('date', $year)->orderBy('date')->get()]);
    }

    /** POST /business/hr/holidays */
    public function storeHoliday(Request $request)
    {
        $data = $request->validate(['date' => ['required', 'date'], 'name' => ['required', 'string', 'max:120']]);
        $holiday = Holiday::query()->updateOrCreate(['business_id' => BusinessContext::id($request), 'date' => $data['date']], ['name' => $data['name']]);

        return $this->ok(['holiday' => $holiday], 201);
    }

    /** DELETE /business/hr/holidays/{holiday} */
    public function destroyHoliday(Request $request, int $holiday)
    {
        Holiday::query()->where('business_id', BusinessContext::id($request))->findOrFail($holiday)->delete();

        return $this->ok([]);
    }

    // ── employees ──

    /** GET /business/hr/employees — the staff with their HR file and leave balance (salary only with payroll) */
    public function employees(Request $request)
    {
        $businessId = BusinessContext::id($request);
        $seesPay = $this->may($request, BusinessCapability::PAYROLL);
        $staff = BusinessStaff::query()->where('business_id', $businessId)->where('is_active', 1)->get();
        $files = Employee::query()->where('business_id', $businessId)->get()->keyBy('user_id');
        $users = User::query()->whereIn('id', $staff->pluck('user_id'))->get(['id', 'name', 'phone'])->keyBy('id');

        return $this->ok(['employees' => $staff->map(function ($s) use ($files, $users, $businessId, $seesPay) {
            $file = $files->get($s->user_id);
            $row = [
                'user_id' => $s->user_id,
                'name' => optional($users->get($s->user_id))->name,
                'title' => $s->title,
                'shift_id' => $file?->shift_id,
                'hire_date' => optional($file?->hire_date)->toDateString(),
                'birth_date' => optional($file?->birth_date)->toDateString(),
                'rest_days' => $file?->rest_days,
                'is_active' => $file ? (bool) $file->is_active : true,
                'leave' => $this->requests->leaveBalance($businessId, (int) $s->user_id),
            ];
            if ($seesPay) {
                $row += [
                    'basic_salary' => $file?->basic_salary,
                    'allowances' => $file?->allowances ?? [],
                    'insurable_wage' => $file?->insurable_wage,
                    'insured' => $file ? (bool) $file->insured : true,
                    'taxable' => $file ? (bool) $file->taxable : true,
                    'pay_method' => $file?->pay_method ?? 'cash',
                ];
            }

            return $row;
        })->values()]);
    }

    /** PATCH /business/hr/employees/{user} — salary fields need the payroll capability */
    public function updateEmployee(Request $request, int $user)
    {
        $businessId = BusinessContext::id($request);
        if (! BusinessStaff::query()->where('business_id', $businessId)->where('user_id', $user)->exists()) {
            abort(404);
        }

        $data = $request->validate([
            'shift_id' => ['sometimes', 'nullable', 'integer', Rule::exists('hr_shifts', 'id')->where('business_id', $businessId)],
            'hire_date' => ['sometimes', 'nullable', 'date'],
            'birth_date' => ['sometimes', 'nullable', 'date'],
            'rest_days' => ['sometimes', 'nullable', 'array', 'max:3'],
            'rest_days.*' => ['integer', 'between:0,6'],
            'is_active' => ['sometimes', 'boolean'],
            'basic_salary' => ['sometimes', 'numeric', 'min:0'],
            'allowances' => ['sometimes', 'array', 'max:12'],
            'allowances.*.name' => ['required', 'string', 'max:60'],
            'allowances.*.amount' => ['required', 'numeric', 'min:0'],
            'allowances.*.insurable' => ['sometimes', 'boolean'],
            'insurable_wage' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'insured' => ['sometimes', 'boolean'],
            'taxable' => ['sometimes', 'boolean'],
            'pay_method' => ['sometimes', Rule::in(['cash', 'transfer', 'wallet_outside'])],
        ]);

        $payFields = array_intersect(array_keys($data), ['basic_salary', 'allowances', 'insurable_wage', 'insured', 'taxable', 'pay_method']);
        if ($payFields !== [] && ! $this->may($request, BusinessCapability::PAYROLL)) {
            abort(response()->json(['success' => false, 'message' => __('لا تملك صلاحية تعديل المرتبات.')], 403));
        }

        $file = Employee::query()->updateOrCreate(['business_id' => $businessId, 'user_id' => $user], $data);

        return $this->ok(['employee' => $file->fresh()]);
    }

    // ── the month's board ──

    /** GET /business/hr/attendance?month=YYYY-MM — every employee, day by day, with the month's totals */
    public function board(Request $request)
    {
        $businessId = BusinessContext::id($request);
        $month = $this->month($request);
        $staff = BusinessStaff::query()->where('business_id', $businessId)->where('is_active', 1)->pluck('user_id');
        $users = User::query()->whereIn('id', $staff)->pluck('name', 'id');
        $files = Employee::query()->where('business_id', $businessId)->get()->keyBy('user_id');

        return $this->ok([
            'month' => $month,
            'employees' => $staff->map(fn ($id) => ['user_id' => $id, 'name' => $users[$id] ?? null]
                + $this->attendance->month($businessId, (int) $id, $month, $files->get($id)))->values(),
            'pending_requests' => HrRequest::query()->where('business_id', $businessId)->where('status', 'pending')->count(),
        ]);
    }

    // ── requests ──

    /** GET /business/hr/requests?status=pending */
    public function requests(Request $request)
    {
        $businessId = BusinessContext::id($request);
        $rows = HrRequest::query()->where('business_id', $businessId)
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest('id')->limit(200)->get();
        $names = User::query()->whereIn('id', $rows->pluck('user_id'))->pluck('name', 'id');

        return $this->ok(['requests' => $rows->map(fn ($r) => $r->toArray() + ['user_name' => $names[$r->user_id] ?? null])->values()]);
    }

    /** POST /business/hr/requests — the manager records one himself (approved at once) */
    public function storeRequest(Request $request)
    {
        $businessId = BusinessContext::id($request);
        $data = $request->validate(self::requestRules() + ['user_id' => ['required', 'integer']]);
        if (! BusinessStaff::query()->where('business_id', $businessId)->where('user_id', $data['user_id'])->exists()) {
            abort(404);
        }

        $made = $this->requests->create($businessId, (int) $data['user_id'], $data, true);

        return $this->ok(['request' => $this->requests->decide($made, true, (int) $request->user()->id, __('سجّله المدير'))], 201);
    }

    /** POST /business/hr/requests/{hrRequest}/decide — {approve, note?} */
    public function decide(Request $request, int $hrRequest)
    {
        $model = HrRequest::query()->where('business_id', BusinessContext::id($request))->findOrFail($hrRequest);
        $data = $request->validate(['approve' => ['required', 'boolean'], 'note' => ['nullable', 'string', 'max:300']]);

        return $this->ok(['request' => $this->requests->decide($model, (bool) $data['approve'], (int) $request->user()->id, $data['note'] ?? null)]);
    }

    // ── helpers ──

    public static function requestRules(): array
    {
        return [
            'kind' => ['required', Rule::in(HrRequestService::KINDS)],
            'date_from' => ['required', 'date'],
            'date_to' => ['nullable', 'date'],
            'leave_type' => ['required_if:kind,leave', Rule::in(HrRequestService::LEAVE_TYPES)],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i'],
            'minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'night_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'reason' => ['nullable', 'string', 'max:300'],
        ];
    }

    private function shiftRules(bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return ['name' => [$req, 'string', 'max:80'], 'starts_at' => [$req, 'date_format:H:i'], 'ends_at' => [$req, 'date_format:H:i'], 'is_active' => ['sometimes', 'boolean']];
    }

    private function month(Request $request): string
    {
        $month = (string) $request->query('month', now()->format('Y-m'));
        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month), 422, __('الشهر بصيغة YYYY-MM.'));

        return $month;
    }

    private function may(Request $request, string $capability): bool
    {
        return BusinessContext::isOwner($request) || in_array($capability, BusinessContext::capabilities($request), true);
    }

    private function ok(array $data, int $status = 200)
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }
}
