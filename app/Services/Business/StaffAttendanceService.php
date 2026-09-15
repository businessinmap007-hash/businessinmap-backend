<?php

namespace App\Services\Business;

use App\Models\StaffAttendance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Self-service check-in/check-out, on the app itself - a staff member marks
 * their own shift, the owner only ever reads it back (on the grouped staff
 * cards). One row per (business, user, day); checking in twice the same day
 * is a no-op, never a second shift.
 */
class StaffAttendanceService
{
    public function checkIn(int $businessId, int $userId): StaffAttendance
    {
        $today = Carbon::today();

        $attendance = StaffAttendance::query()->firstOrNew([
            'business_id' => $businessId,
            'user_id' => $userId,
            'date' => $today->toDateString(),
        ]);

        if (! $attendance->exists) {
            $attendance->checked_in_at = now();
            $attendance->save();

            return $attendance;
        }

        // Already checked in today (idempotent) - a checked-out shift may be
        // reopened by checking in again, same day only.
        if ($attendance->checked_in_at === null) {
            $attendance->checked_in_at = now();
        }
        $attendance->checked_out_at = null;
        $attendance->save();

        return $attendance;
    }

    public function checkOut(int $businessId, int $userId): StaffAttendance
    {
        $attendance = StaffAttendance::query()
            ->where('business_id', $businessId)
            ->where('user_id', $userId)
            ->where('date', Carbon::today()->toDateString())
            ->first();

        if (! $attendance || $attendance->checked_in_at === null) {
            throw ValidationException::withMessages([
                'attendance' => [__('لم تسجّل حضورك اليوم بعد.')],
            ]);
        }

        $attendance->checked_out_at = now();
        $attendance->save();

        return $attendance;
    }

    /** Today's attendance for one staff member, or null if never checked in. */
    public function todayFor(int $businessId, int $userId): ?StaffAttendance
    {
        return StaffAttendance::query()
            ->where('business_id', $businessId)
            ->where('user_id', $userId)
            ->where('date', Carbon::today()->toDateString())
            ->first();
    }

    /**
     * Today's attendance for every staff member given, keyed by user_id -
     * one query for the whole grouped-staff view instead of one per card.
     *
     * @param  list<int>  $userIds
     * @return Collection<int,StaffAttendance>
     */
    public function todayForMany(int $businessId, array $userIds): Collection
    {
        if ($userIds === []) {
            return collect();
        }

        return StaffAttendance::query()
            ->where('business_id', $businessId)
            ->where('date', Carbon::today()->toDateString())
            ->whereIn('user_id', $userIds)
            ->get()
            ->keyBy('user_id');
    }
}
