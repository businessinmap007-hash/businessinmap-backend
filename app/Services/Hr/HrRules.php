<?php

namespace App\Services\Hr;

use App\Models\Hr\Employee;
use App\Models\Hr\LawTable;
use App\Models\Hr\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * The two kinds of rules a payroll reads: the business's own (its «لائحة» — grace minutes, lateness tiers, absence,
 * the monthly cap, its overtime rates and rest days) and the law's (insurance, tax, overtime minimum, leave days), the
 * latter one dated row per kind so a month always reads the row in force that month.
 */
final class HrRules
{
    public const DEFAULT_TIERS = [['upto_minutes' => 60, 'day_fraction' => 0.25], ['upto_minutes' => null, 'day_fraction' => 0.5]];

    public function settings(int $businessId): Setting
    {
        $setting = Setting::query()->firstOrCreate(['business_id' => $businessId], [
            'late_tiers' => self::DEFAULT_TIERS,
            'rest_days' => [Carbon::FRIDAY],
        ]);

        // a row made just now carries none of the table's defaults (grace, days per month, overtime rates) until reloaded
        return $setting->wasRecentlyCreated ? $setting->fresh() : $setting;
    }

    /** @return array<string,mixed> the law row of this kind in force on that date */
    public function law(string $kind, Carbon|string $on): array
    {
        $date = $on instanceof Carbon ? $on->toDateString() : $on;
        $row = LawTable::query()->where('kind', $kind)->where('effective_from', '<=', $date)->orderByDesc('effective_from')->first();

        if (! $row) {
            throw ValidationException::withMessages(['law' => __('لا يوجد جدول قانوني ساري لهذا الشهر: :kind', ['kind' => $kind])]);
        }

        return $row->data;
    }

    /** @return list<int> the employee's weekly rest days (Carbon dayOfWeek) */
    public function restDays(Setting $settings, ?Employee $employee): array
    {
        $days = $employee?->rest_days ?? $settings->rest_days ?? [Carbon::FRIDAY];

        return array_map('intval', (array) $days);
    }

    /** The lateness / early-leave tier for these minutes, as a fraction of a day (0 inside the grace). */
    public function tierFraction(Setting $settings, int $minutes): float
    {
        if ($minutes <= (int) $settings->grace_minutes) {
            return 0.0;
        }

        foreach ((array) ($settings->late_tiers ?: self::DEFAULT_TIERS) as $tier) {
            $upto = $tier['upto_minutes'] ?? null;
            if ($upto === null || $minutes <= (int) $upto) {
                return (float) ($tier['day_fraction'] ?? 0);
            }
        }

        return 0.0;
    }

    /**
     * Annual leave the law gives this employee in the given year: 15 in the first year, 21 after it, 30 after ten years
     * of service or from fifty years of age.
     */
    public function annualLeave(Employee $employee, Carbon $on): int
    {
        $law = $this->law('annual_leave', $on);
        $hired = $employee->hire_date;
        $years = $hired ? $hired->diffInYears($on) : 1;
        $age = $employee->birth_date ? $employee->birth_date->diffInYears($on) : 0;

        if ($years >= 10 || $age >= 50) {
            return (int) $law['after_ten_years_or_fifty'];
        }

        return $years >= 1 ? (int) $law['after_first_year'] : (int) $law['first_year'];
    }
}
