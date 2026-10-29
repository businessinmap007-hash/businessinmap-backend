<?php

namespace App\Services\Hr;

use App\Models\Hr\Adjustment;
use App\Models\Hr\Advance;
use App\Models\Hr\Employee;
use Illuminate\Support\Carbon;

/**
 * One employee's payslip for a month, from gross to net (canvas «المرتب: من الإجمالي إلى الصافي»):
 *
 *   + basic + allowances + approved overtime (hourly × (1 + the business's day / night rate)) + bonuses
 *   − attendance (deduction days × day rate) − unpaid leave − the advance installment − penalties
 *   − social insurance (employee's share of the insurable wage, held between the law's minimum and maximum)
 *   − wage tax (the month's taxable × 12, less the personal exemption, through the brackets, ÷ 12)
 *   = net — paid outside the app and recorded; the BIM wallet never pays salaries.
 *
 * The day rate is basic ÷ the business's days per month, the hourly rate that ÷ its hours per day.
 */
final class PayrollCalculator
{
    public function __construct(
        private readonly HrRules $rules,
        private readonly AttendanceCalculator $attendance,
    ) {}

    /** @return array{lines:list<array<string,mixed>>,attendance:array<string,mixed>,gross:float,deductions:float,net:float,employer_insurance:float,review:bool} */
    public function payslip(Employee $employee, string $month): array
    {
        $businessId = (int) $employee->business_id;
        $settings = $this->rules->settings($businessId);
        $on = Carbon::parse($month . '-01');
        $att = $this->attendance->month($businessId, (int) $employee->user_id, $month, $employee)['totals'];

        $basic = (float) $employee->basic_salary;
        $dayRate = $basic / max(1, (int) $settings->days_per_month);
        $hourly = $dayRate / max(1, (int) $settings->hours_per_day);
        $lines = [];
        $add = function (string $type, string $key, string $label, float $amount, ?string $detail = null) use (&$lines) {
            if (abs($amount) < 0.005) {
                return;
            }
            $lines[] = ['type' => $type, 'key' => $key, 'label' => $label, 'amount' => round($amount, 2), 'detail' => $detail];
        };

        // ── earnings ──
        $add('earning', 'basic', 'الأساسي', $basic);
        $insurableAllowances = 0.0;
        foreach ((array) $employee->allowances as $allowance) {
            $amount = (float) ($allowance['amount'] ?? 0);
            $add('earning', 'allowance', (string) ($allowance['name'] ?? 'بدل'), $amount);
            if (! empty($allowance['insurable'])) {
                $insurableAllowances += $amount;
            }
        }
        $otDay = $att['approved_overtime_minutes'] / 60 * $hourly * (1 + (float) $settings->overtime_day_pct / 100);
        $otNight = $att['approved_overtime_night_minutes'] / 60 * $hourly * (1 + (float) $settings->overtime_night_pct / 100);
        $add('earning', 'overtime', 'إضافي', $otDay + $otNight,
            $this->hours($att['approved_overtime_minutes']) . ' س نهاري + ' . $this->hours($att['approved_overtime_night_minutes']) . ' س ليلي');

        $adjustments = Adjustment::query()->where('business_id', $businessId)->where('user_id', $employee->user_id)->where('month', $month)->get();
        foreach ($adjustments->where('kind', 'bonus') as $bonus) {
            $add('earning', 'bonus', 'مكافأة', (float) $bonus->amount, $bonus->reason);
        }

        $gross = array_sum(array_column(array_filter($lines, fn ($l) => $l['type'] === 'earning'), 'amount'));

        // ── deductions ──
        $attendanceCut = $att['deduction_days'] * $dayRate;
        $add('deduction', 'attendance', 'خصم الحضور', $attendanceCut, $this->days($att['deduction_days']) . ' يوم');
        $unpaid = $att['unpaid_leave'] * $dayRate;
        $add('deduction', 'unpaid_leave', 'إجازة بدون أجر', $unpaid, $att['unpaid_leave'] . ' يوم');

        foreach (Advance::query()->where('business_id', $businessId)->where('user_id', $employee->user_id)
            ->where('first_month', '<=', $month)->where('remaining', '>', 0)->get() as $advance) {
            $add('deduction', 'advance', 'قسط سلفة', min((float) $advance->installment, (float) $advance->remaining),
                'الباقي بعده ' . round(max(0, (float) $advance->remaining - (float) $advance->installment), 2));
        }
        foreach ($adjustments->where('kind', 'penalty') as $penalty) {
            $add('deduction', 'penalty', 'جزاء', (float) $penalty->amount, $penalty->reason);
        }

        $employerInsurance = 0.0;
        $employeeInsurance = 0.0;
        if ($employee->insured) {
            $si = $this->rules->law('social_insurance', $on);
            $insurable = $employee->insurable_wage ?? ($basic + $insurableAllowances);
            $insurable = min(max($insurable, (float) $si['min_wage']), (float) $si['max_wage']);
            $employeeInsurance = $insurable * (float) $si['employee_pct'] / 100;
            $employerInsurance = $insurable * (float) $si['employer_pct'] / 100;
            $add('deduction', 'insurance', 'التأمينات الاجتماعية', $employeeInsurance, (float) $si['employee_pct'] . '% من ' . round($insurable, 2));
        }

        $review = false;
        if ($employee->taxable) {
            $tax = $this->rules->law('wage_tax', $on);
            $annual = max(0, ($gross - $attendanceCut - $unpaid - $employeeInsurance) * 12 - (float) $tax['exemption']);
            $review = isset($tax['review_above']) && $annual > (float) $tax['review_above'];
            $add('deduction', 'tax', 'ضريبة كسب العمل', $this->bracketTax($annual, (array) $tax['brackets']) / 12, 'وعاء سنوي ' . round($annual, 2));
        }

        $deductions = array_sum(array_column(array_filter($lines, fn ($l) => $l['type'] === 'deduction'), 'amount'));

        return [
            'lines' => $lines,
            'attendance' => $att,
            'gross' => round($gross, 2),
            'deductions' => round($deductions, 2),
            'net' => round($gross - $deductions, 2),
            'employer_insurance' => round($employerInsurance, 2),
            'review' => $review,
        ];
    }

    /** Progressive brackets: [[upper bound or null, rate %], …] — each slice taxed at its own rate. */
    public function bracketTax(float $annual, array $brackets): float
    {
        $tax = 0.0;
        $lower = 0.0;

        foreach ($brackets as [$upper, $rate]) {
            $top = $upper === null ? $annual : min($annual, (float) $upper);
            if ($top > $lower) {
                $tax += ($top - $lower) * (float) $rate / 100;
            }
            if ($upper === null || $annual <= (float) $upper) {
                break;
            }
            $lower = (float) $upper;
        }

        return $tax;
    }

    private function hours(int $minutes): string
    {
        return rtrim(rtrim(number_format($minutes / 60, 2, '.', ''), '0'), '.') ?: '0';
    }

    private function days(float $days): string
    {
        return rtrim(rtrim(number_format($days, 2, '.', ''), '0'), '.') ?: '0';
    }
}
