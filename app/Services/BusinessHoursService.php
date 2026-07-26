<?php

namespace App\Services;

use App\Models\BusinessWorkingHour;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * A business's weekly opening hours, and the "is it open right now" question a
 * customer's search needs to skip closed shops.
 *
 * Times are evaluated in the app timezone (businesses carry none yet). A
 * business with no rows is "hours unknown" — treated as available, never
 * hidden — so search only ever EXCLUDES a shop that has said it is closed now.
 */
class BusinessHoursService
{
    /** 0..6 = Sunday..Saturday, matching Carbon::dayOfWeek. */
    public const DAYS = [0, 1, 2, 3, 4, 5, 6];

    /** @return Collection<int,BusinessWorkingHour> keyed by day_of_week */
    public function hoursFor(int $businessId): Collection
    {
        return BusinessWorkingHour::query()
            ->where('business_id', $businessId)
            ->get()
            ->keyBy('day_of_week');
    }

    /**
     * Replace a business's whole week. Each entry: day (0..6), is_closed, and
     * open/close "HH:MM". A day left out is untouched; an explicit is_closed
     * clears its window.
     *
     * @param  array<int,array{day:int,is_closed?:bool,open?:?string,close?:?string}>  $days
     */
    public function save(int $businessId, array $days): void
    {
        foreach ($days as $entry) {
            $day = (int) ($entry['day'] ?? -1);

            if (! in_array($day, self::DAYS, true)) {
                continue;
            }

            $closed = (bool) ($entry['is_closed'] ?? false);
            $open = $closed ? null : $this->normalize($entry['open'] ?? null);
            $close = $closed ? null : $this->normalize($entry['close'] ?? null);

            BusinessWorkingHour::query()->updateOrCreate(
                ['business_id' => $businessId, 'day_of_week' => $day],
                ['is_closed' => $closed, 'open_time' => $open, 'close_time' => $close],
            );
        }
    }

    /** Is the business open at the given moment (now by default)? */
    public function isOpenNow(int $businessId, ?Carbon $at = null): bool
    {
        $at = $at ?: Carbon::now();
        $row = $this->hoursFor($businessId)->get($at->dayOfWeek);

        // No configuration for today → hours unknown → available.
        if (! $row) {
            return true;
        }

        if ($row->is_closed || ! $row->open_time || ! $row->close_time) {
            return false;
        }

        return $this->withinWindow(
            $at->format('H:i:s'),
            (string) $row->open_time,
            (string) $row->close_time
        );
    }

    /**
     * Open-now status for many businesses in one query (avoids N+1 in a list).
     * A business with no row for today is absent from the map's source and
     * defaults to true (unknown → available).
     *
     * @param  list<int>  $businessIds
     * @return array<int,bool>
     */
    public function openNowMap(array $businessIds, ?Carbon $at = null): array
    {
        $at = $at ?: Carbon::now();
        $now = $at->format('H:i:s');

        $rows = BusinessWorkingHour::query()
            ->whereIn('business_id', $businessIds)
            ->where('day_of_week', $at->dayOfWeek)
            ->get()
            ->keyBy('business_id');

        $map = [];
        foreach ($businessIds as $id) {
            $row = $rows->get($id);

            $map[$id] = ! $row
                ? true
                : (! $row->is_closed && $row->open_time && $row->close_time
                    && $this->withinWindow($now, (string) $row->open_time, (string) $row->close_time));
        }

        return $map;
    }

    /**
     * Restrict a `users` query to businesses open at $at: exclude any that have
     * a row for today saying they are closed now. Businesses with no row for
     * today are kept (unknown → available). Handles past-midnight windows.
     */
    public function filterOpenNow(Builder $query, ?Carbon $at = null): Builder
    {
        $at = $at ?: Carbon::now();
        $dow = $at->dayOfWeek;
        $now = $at->format('H:i:s');

        return $query->whereNotExists(function ($sub) use ($dow, $now) {
            $sub->from('business_working_hours as wh')
                ->whereColumn('wh.business_id', 'users.id')
                ->where('wh.day_of_week', $dow)
                ->where(function ($w) use ($now) {
                    // Explicitly closed, or missing a window.
                    $w->where('wh.is_closed', 1)
                        ->orWhereNull('wh.open_time')
                        ->orWhereNull('wh.close_time')
                        // Normal window (open <= close): closed when outside it.
                        ->orWhere(function ($n) use ($now) {
                            $n->whereColumn('wh.open_time', '<=', 'wh.close_time')
                                ->where(function ($x) use ($now) {
                                    $x->where('wh.open_time', '>', $now)
                                        ->orWhere('wh.close_time', '<=', $now);
                                });
                        })
                        // Past-midnight window (open > close): closed only in the
                        // gap between close and open.
                        ->orWhere(function ($o) use ($now) {
                            $o->whereColumn('wh.open_time', '>', 'wh.close_time')
                                ->where('wh.open_time', '>', $now)
                                ->where('wh.close_time', '<=', $now);
                        });
                });
        });
    }

    private function withinWindow(string $now, string $open, string $close): bool
    {
        if ($open <= $close) {
            return $now >= $open && $now < $close;
        }

        // Past midnight: open in the evening OR early morning.
        return $now >= $open || $now < $close;
    }

    private function normalize(?string $time): ?string
    {
        $time = trim((string) $time);

        if ($time === '' || ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            return null;
        }

        return $time . ':00';
    }
}
