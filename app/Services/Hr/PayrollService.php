<?php

namespace App\Services\Hr;

use App\Models\Hr\Advance;
use App\Models\Hr\Employee;
use App\Models\Hr\PayrollRun;
use App\Models\Hr\Payslip;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «مسير المرتبات»: draft (recomputed as often as wanted) → approved (the month locks: nothing changes it afterwards
 * except next month's adjustments, and each advance's installment is taken off what remains) → each payslip marked
 * paid as the money is handed over outside the app. Every employee sees his own payslip on his phone once approved.
 */
final class PayrollService
{
    public const DRAFT = 'draft';
    public const APPROVED = 'approved';

    public function __construct(private readonly PayrollCalculator $calculator) {}

    public function run(int $businessId, string $month): ?PayrollRun
    {
        return PayrollRun::query()->where('business_id', $businessId)->where('month', $month)->first();
    }

    public function generate(int $businessId, string $month): PayrollRun
    {
        $this->assertMonth($month);

        return DB::transaction(function () use ($businessId, $month) {
            $run = PayrollRun::query()->lockForUpdate()->firstOrCreate(['business_id' => $businessId, 'month' => $month], ['status' => self::DRAFT]);

            if ($run->status !== self::DRAFT) {
                throw ValidationException::withMessages(['month' => __('مسير هذا الشهر معتمد ومقفل؛ التعديل يكون في الشهر التالي.')]);
            }

            $run->payslips()->delete();
            $totals = ['employees' => 0, 'gross' => 0.0, 'net' => 0.0, 'employee_insurance' => 0.0, 'employer_insurance' => 0.0, 'tax' => 0.0, 'review' => 0];

            $employees = Employee::query()->where('business_id', $businessId)->where('is_active', 1)->where('basic_salary', '>', 0)->get();
            foreach ($employees as $employee) {
                $slip = $this->calculator->payslip($employee, $month);
                Payslip::create([
                    'run_id' => $run->id,
                    'business_id' => $businessId,
                    'user_id' => $employee->user_id,
                    'lines' => $slip['lines'],
                    'attendance' => $slip['attendance'] + ['review' => $slip['review']],
                    'gross' => $slip['gross'],
                    'deductions' => $slip['deductions'],
                    'net' => $slip['net'],
                    'employer_insurance' => $slip['employer_insurance'],
                ]);

                $totals['employees']++;
                $totals['gross'] += $slip['gross'];
                $totals['net'] += $slip['net'];
                $totals['employer_insurance'] += $slip['employer_insurance'];
                $totals['employee_insurance'] += collect($slip['lines'])->where('key', 'insurance')->sum('amount');
                $totals['tax'] += collect($slip['lines'])->where('key', 'tax')->sum('amount');
                $totals['review'] += $slip['review'] ? 1 : 0;
            }

            $run->update(['totals' => array_map(fn ($v) => is_float($v) ? round($v, 2) : $v, $totals)]);

            return $run->fresh();
        });
    }

    public function approve(PayrollRun $run, int $actorId): PayrollRun
    {
        if ($run->status !== self::DRAFT) {
            throw ValidationException::withMessages(['status' => __('هذا المسير معتمد من قبل.')]);
        }
        if (! $run->payslips()->exists()) {
            throw ValidationException::withMessages(['status' => __('أعد حساب المسير أولًا؛ لا كشوف فيه.')]);
        }

        DB::transaction(function () use ($run, $actorId) {
            foreach ($run->payslips()->get() as $slip) {
                foreach (collect($slip->lines)->where('key', 'advance') as $line) {
                    $advance = Advance::query()->where('business_id', $run->business_id)->where('user_id', $slip->user_id)
                        ->where('first_month', '<=', $run->month)->where('remaining', '>', 0)->orderBy('id')->lockForUpdate()->first();
                    $advance?->update(['remaining' => max(0, round((float) $advance->remaining - (float) $line['amount'], 2))]);
                }
            }
            $run->update(['status' => self::APPROVED, 'approved_by' => $actorId, 'approved_at' => now()]);
        });

        foreach ($run->payslips()->get() as $slip) {
            $this->tell((int) $slip->user_id, $run, $slip);
        }

        return $run->fresh();
    }

    /** RECORD that this payslip was handed over (cash / transfer, outside the app). */
    public function markPaid(Payslip $slip, string $method, int $actorId): Payslip
    {
        $run = PayrollRun::query()->find($slip->run_id);
        if (! $run || $run->status !== self::APPROVED) {
            throw ValidationException::withMessages(['status' => __('اعتمد المسير قبل تسجيل الصرف.')]);
        }

        if (! $slip->paid_at) {
            $slip->update(['paid_at' => now(), 'paid_method' => $method, 'paid_by' => $actorId]);
        }

        return $slip->fresh();
    }

    /**
     * The employee, from his own account, confirms he received this salary — the other half of a handover the manager
     * records as paid. Only his own payslip, only once the month is approved; confirming twice changes nothing.
     */
    public function confirmReceived(Payslip $slip, int $employeeId): Payslip
    {
        if ((int) $slip->user_id !== $employeeId) {
            abort(404);
        }

        $run = PayrollRun::query()->find($slip->run_id);
        if (! $run || $run->status !== self::APPROVED) {
            throw ValidationException::withMessages(['status' => __('لا يُؤكد الاستلام قبل اعتماد المسير.')]);
        }

        if (! $slip->received_confirmed_at) {
            $slip->update(['received_confirmed_at' => now()]);

            try {
                $name = (string) optional(User::query()->find($employeeId))->name;
                app(NotificationDispatcherService::class)->dispatch('hr_payslip_received', (int) $slip->business_id, [
                    'body_ar' => "أكّد {$name} استلام راتب {$run->month}: " . number_format((float) $slip->net, 2) . ' ج.',
                    'body_en' => "{$name} confirmed receiving the {$run->month} salary: " . number_format((float) $slip->net, 2) . ' EGP.',
                    'source_type' => 'hr_payslip',
                    'source_id' => $slip->id,
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $slip->fresh();
    }

    private function assertMonth(string $month): void
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw ValidationException::withMessages(['month' => __('الشهر بصيغة YYYY-MM.')]);
        }
    }

    private function tell(int $userId, PayrollRun $run, Payslip $slip): void
    {
        try {
            $business = (string) optional(User::query()->find($run->business_id))->name;
            app(NotificationDispatcherService::class)->dispatch('hr_payslip_ready', $userId, [
                'body_ar' => "كشف مرتبك لشهر {$run->month} من {$business} جاهز: الصافي " . number_format((float) $slip->net, 2) . ' ج.',
                'body_en' => "Your {$run->month} payslip from {$business} is ready: net " . number_format((float) $slip->net, 2) . ' EGP.',
                'source_type' => 'hr_payslip',
                'source_id' => $slip->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
