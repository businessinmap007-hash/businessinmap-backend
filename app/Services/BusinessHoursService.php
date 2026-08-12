<?php

namespace App\Services;

use App\Models\BusinessWorkingHour;
use Carbon\Carbon;
use Carbon\CarbonInterface;
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

    /** The business's own timezone, or the platform default. */
    public function timezoneFor(int $businessId): string
    {
        $tz = \App\Models\User::query()->whereKey($businessId)->value('timezone');

        return $tz ?: (string) config('app.timezone');
    }

    /**
     * Is the business open at the given moment? With no explicit $at, "now" is
     * read in the BUSINESS's own timezone, so a shop is judged where it is.
     */
    public function isOpenNow(int $businessId, ?Carbon $at = null): bool
    {
        $at = $at ?: Carbon::now($this->timezoneFor($businessId));
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
     * Is the business open for a whole WINDOW, not just an instant?
     *
     * `isOpenNow` answers about a moment, which is all a search result needs. A
     * booking is a range, and a clinic that closes at 17:00 was happily handed a
     * 16:50 appointment thirty minutes long — and, worse, could publish a whole
     * grid of Friday slots on a Friday it is closed.
     *
     * Two deliberate abstentions:
     *
     * - **No rows at all → true.** Hours unknown is not hours refused; the same
     *   rule search uses. Nearly every business on the platform has set none
     *   yet, and a booking engine that started refusing them all would take the
     *   platform down rather than tighten it.
     * - **A window crossing midnight into another DAY → true.** A four-night
     *   hotel stay is not a question about opening hours, and answering it as
     *   one would refuse every stay ever made. Opening hours gate the
     *   appointment-shaped bookings — the ones measured in minutes and hours.
     */
    public function isOpenThroughout(int $businessId, CarbonInterface $start, CarbonInterface $end): bool
    {
        $hours = $this->hoursFor($businessId);

        if ($hours->isEmpty()) {
            return true;
        }

        if (! $start->isSameDay($end)) {
            return true;
        }

        $row = $hours->get($start->dayOfWeek);

        // A business that described its week but left this day out has said
        // nothing about it — same abstention as above, one day wide.
        if (! $row) {
            return true;
        }

        if ($row->is_closed || ! $row->open_time || ! $row->close_time) {
            return false;
        }

        $open = (string) $row->open_time;
        $close = (string) $row->close_time;

        // An end exactly ON closing time is inside: 16:30–17:00 at a clinic that
        // closes at 17:00 is the last appointment of the day, not a refusal.
        $from = $start->format('H:i:s');
        $to = $end->format('H:i:s');

        if ($open <= $close) {
            return $from >= $open && $to <= $close;
        }

        // Past midnight, and both ends on the same date: either both in the
        // evening stretch or both in the early-morning one.
        return ($from >= $open && $to > $open) || ($from < $close && $to <= $close);
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
        // All of a business's rows (all weekdays), so each can be judged at its
        // OWN local "now" when no fixed $at is supplied.
        $rows = BusinessWorkingHour::query()
            ->whereIn('business_id', $businessIds)
            ->get()
            ->groupBy('business_id');

        // Per-business timezone, only when we must compute "now" ourselves.
        $zones = $at
            ? []
            : \App\Models\User::query()->whereIn('id', $businessIds)->pluck('timezone', 'id');

        $map = [];
        foreach ($businessIds as $id) {
            $moment = $at ?: Carbon::now($zones[$id] ?? config('app.timezone'));
            $row = ($rows->get($id) ?? collect())->firstWhere('day_of_week', $moment->dayOfWeek);

            $map[$id] = ! $row
                ? true
                : (! $row->is_closed && $row->open_time && $row->close_time
                    && $this->withinWindow($moment->format('H:i:s'), (string) $row->open_time, (string) $row->close_time));
        }

        return $map;
    }

    /**
     * Restrict a `users` query to businesses open at $at (keyed on users.id).
     */
    public function filterOpenNow(Builder $query, ?Carbon $at = null): Builder
    {
        return $this->applyOpenNow($query, 'users.id', $at);
    }

    /**
     * The reusable "open now" constraint for ANY query that carries a business
     * id — a users query (users.id), a catalog-listings subquery
     * (business_catalog_listings.business_id), an offers query (a COALESCE of
     * its seller/owner columns). Excludes rows whose business has a today-row
     * saying it is closed now; a business with no today-row is kept.
     *
     * $businessColumn is a developer-supplied SQL expression (never user input),
     * so a raw correlation is safe and lets a COALESCE be passed.
     *
     * @param  \Illuminate\Database\Query\Builder|Builder  $query
     */
    public function applyOpenNow($query, string $businessColumn, ?Carbon $at = null)
    {
        $at = $at ?: Carbon::now();
        $dow = $at->dayOfWeek;
        $now = $at->format('H:i:s');

        return $query->whereNotExists(function ($sub) use ($dow, $now, $businessColumn) {
            $sub->from('business_working_hours as wh')
                ->whereRaw('wh.business_id = ' . $businessColumn)
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
