<?php

namespace App\Services\Hr;

use App\Models\Hr\Employee;
use App\Models\Hr\Holiday;
use App\Models\Hr\HrRequest;
use App\Models\Hr\Shift;
use App\Models\StaffAttendance;
use Illuminate\Support\Carbon;

/**
 * One employee's month, day by day, from the punches (`staff_attendances`) against the employee's shift:
 *
 *   present · late / early (beyond the grace, priced by the business's tiers) · absent (a work day with no punch) ·
 *   leave (an approved request; unpaid leave apart) · rest (the weekly day off) · holiday · overtime (after the shift's
 *   end, split into day and night minutes by the business's night window).
 *
 * Overtime found here is only a SUGGESTION: payroll pays the overtime the manager approved (hr_requests kind overtime).
 * The month's deduction days are capped by the business's monthly cap.
 */
final class AttendanceCalculator
{
    public const PRESENT = 'present';
    public const LATE = 'late';
    public const ABSENT = 'absent';
    public const LEAVE = 'leave';
    public const UNPAID = 'unpaid_leave';
    public const REST = 'rest';
    public const HOLIDAY = 'holiday';
    public const NONE = 'none';

    public function __construct(private readonly HrRules $rules) {}

    /**
     * @return array{days:list<array<string,mixed>>,totals:array<string,float|int>}
     */
    public function month(int $businessId, int $userId, string $month, ?Employee $employee = null): array
    {
        $employee ??= Employee::query()->where('business_id', $businessId)->where('user_id', $userId)->first();
        $settings = $this->rules->settings($businessId);
        $start = Carbon::parse($month . '-01')->startOfDay();
        $end = $start->copy()->endOfMonth();
        $today = now()->startOfDay();
        $shift = $employee?->shift_id ? Shift::query()->find($employee->shift_id) : null;
        $restDays = $this->rules->restDays($settings, $employee);

        $punches = StaffAttendance::query()->where('business_id', $businessId)->where('user_id', $userId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])->get()
            ->keyBy(fn ($a) => $a->date->toDateString());
        $holidays = Holiday::query()->where('business_id', $businessId)->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()->keyBy(fn ($h) => $h->date->toDateString());
        $leaves = HrRequest::query()->where('business_id', $businessId)->where('user_id', $userId)->where('kind', 'leave')->where('status', 'approved')
            ->where('date_from', '<=', $end->toDateString())->where('date_to', '>=', $start->toDateString())->get();
        $overtime = HrRequest::query()->where('business_id', $businessId)->where('user_id', $userId)->where('kind', 'overtime')->where('status', 'approved')
            ->whereBetween('date_from', [$start->toDateString(), $end->toDateString()])->get();

        $days = [];
        $totals = [
            'present' => 0, 'late_days' => 0, 'late_minutes' => 0, 'early_minutes' => 0, 'absent' => 0, 'leave' => 0, 'unpaid_leave' => 0,
            'rest' => 0, 'holiday' => 0, 'deduction_days' => 0.0, 'suggested_overtime_minutes' => 0, 'suggested_overtime_night_minutes' => 0,
            'approved_overtime_minutes' => 0, 'approved_overtime_night_minutes' => 0,
        ];

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $date = $day->toDateString();
            $punch = $punches->get($date);
            $leave = $leaves->first(fn ($l) => $l->date_from->lte($day) && $l->date_to->gte($day));
            $row = ['date' => $date, 'status' => self::NONE, 'check_in' => null, 'check_out' => null, 'late_minutes' => 0, 'early_minutes' => 0,
                'overtime_minutes' => 0, 'overtime_night_minutes' => 0, 'deduction' => 0.0, 'note' => null];

            if ($punch) {
                $row['check_in'] = optional($punch->checked_in_at)->format('H:i');
                $row['check_out'] = optional($punch->checked_out_at)->format('H:i');
            }

            $beforeHire = $employee?->hire_date && $day->lt($employee->hire_date);

            if ($beforeHire || $day->gt($today)) {
                $row['status'] = self::NONE;
            } elseif ($holidays->has($date)) {
                $row['status'] = self::HOLIDAY;
                $row['note'] = $holidays->get($date)->name;
                $totals['holiday']++;
            } elseif ($leave) {
                $unpaid = (($leave->data['type'] ?? 'annual') === 'unpaid');
                $row['status'] = $unpaid ? self::UNPAID : self::LEAVE;
                $row['note'] = $leave->data['type'] ?? 'annual';
                $totals[$unpaid ? 'unpaid_leave' : 'leave']++;
            } elseif (in_array($day->dayOfWeek, $restDays, true)) {
                $row['status'] = self::REST;
                $totals['rest']++;
                if ($punch?->checked_in_at && $punch->checked_out_at) {
                    [$dayMin, $nightMin] = $this->split($punch->checked_in_at, $punch->checked_out_at, $settings);
                    $row['overtime_minutes'] = $dayMin;
                    $row['overtime_night_minutes'] = $nightMin;
                }
            } elseif (! $punch?->checked_in_at) {
                // today counts as absent only once the shift is over
                $shiftOver = ! $day->isSameDay($today) || ($shift && now()->gt($this->at($day, $shift->ends_at, $shift)));
                if ($shiftOver) {
                    $row['status'] = self::ABSENT;
                    $row['deduction'] = (float) $settings->absence_day_fraction;
                    $totals['absent']++;
                }
            } else {
                $row['status'] = self::PRESENT;
                $totals['present']++;

                if ($shift) {
                    $starts = $this->at($day, $shift->starts_at, $shift);
                    $ends = $this->at($day, $shift->ends_at, $shift, true);
                    $late = max(0, (int) floor($starts->diffInMinutes($punch->checked_in_at, false)));
                    if ($late > (int) $settings->grace_minutes) {
                        $row['status'] = self::LATE;
                        $row['late_minutes'] = $late;
                        $row['deduction'] += $this->rules->tierFraction($settings, $late);
                        $totals['late_days']++;
                        $totals['late_minutes'] += $late;
                    }
                    if ($punch->checked_out_at) {
                        $early = max(0, (int) floor($punch->checked_out_at->diffInMinutes($ends, false)));
                        if ($early > (int) $settings->grace_minutes) {
                            $row['early_minutes'] = $early;
                            $row['deduction'] += $this->rules->tierFraction($settings, $early);
                            $totals['early_minutes'] += $early;
                        }
                        if ($punch->checked_out_at->gt($ends)) {
                            [$dayMin, $nightMin] = $this->split($ends, $punch->checked_out_at, $settings);
                            $row['overtime_minutes'] = $dayMin;
                            $row['overtime_night_minutes'] = $nightMin;
                        }
                    }
                }
            }

            $totals['deduction_days'] += $row['deduction'];
            $totals['suggested_overtime_minutes'] += $row['overtime_minutes'];
            $totals['suggested_overtime_night_minutes'] += $row['overtime_night_minutes'];
            $days[] = $row;
        }

        foreach ($overtime as $ot) {
            $totals['approved_overtime_minutes'] += (int) ($ot->data['minutes'] ?? 0);
            $totals['approved_overtime_night_minutes'] += (int) ($ot->data['night_minutes'] ?? 0);
        }

        if ($settings->monthly_cap_days !== null) {
            $totals['deduction_days'] = min($totals['deduction_days'], (float) $settings->monthly_cap_days);
        }
        $totals['deduction_days'] = round($totals['deduction_days'], 2);

        return ['days' => $days, 'totals' => $totals];
    }

    /** The moment a shift time falls on, for that day — an end before its start belongs to the next day. */
    private function at(Carbon $day, string $time, Shift $shift, bool $isEnd = false): Carbon
    {
        $moment = Carbon::parse($day->toDateString() . ' ' . $time);

        if ($isEnd && $shift->ends_at <= $shift->starts_at) {
            $moment->addDay();
        }

        return $moment;
    }

    /**
     * Minutes between two moments, split by the business's night window.
     *
     * @return array{0:int,1:int} [day minutes, night minutes]
     */
    private function split(Carbon $from, Carbon $to, $settings): array
    {
        $day = 0;
        $night = 0;

        for ($t = $from->copy(); $t->lt($to); $t->addMinute()) {
            $hm = $t->format('H:i');
            $isNight = $settings->night_from > $settings->night_to
                ? ($hm >= $settings->night_from || $hm < $settings->night_to)
                : ($hm >= $settings->night_from && $hm < $settings->night_to);
            $isNight ? $night++ : $day++;
        }

        return [$day, $night];
    }
}
