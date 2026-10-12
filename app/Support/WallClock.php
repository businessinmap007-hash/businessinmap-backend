<?php

namespace App\Support;

use App\Services\BusinessHoursService;
use Illuminate\Support\Carbon;

/**
 * «الآن» بساعة الجدار.
 *
 * The platform runs on Cairo's clock (config app.timezone), so for the usual business `now()` and the wall clock
 * agree. This helper stays the one place that says WHICH clock a stored shop/customer time is written on: a business
 * that chose another zone of its own (a hotel in Dubai) has its bookings, hours and queue written in THAT zone, and
 * `WallClock::now($businessId)` is the current moment in it, as the digits a stored time is compared with. Use it for
 * ANY comparison between "now" and a stored shop/customer time; keep `now()` for true instants (created_at, sent_at).
 */
final class WallClock
{
    /** The zone a shop's wall clock runs in: its own, or the platform's business zone. */
    public static function zone(?int $businessId = null): string
    {
        return $businessId
            ? app(BusinessHoursService::class)->timezoneFor($businessId)
            : BusinessHoursService::defaultTimezone();
    }

    /** The current moment as wall-clock digits (a Carbon in the app zone carrying the shop's local time). */
    public static function now(?int $businessId = null): Carbon
    {
        $local = Carbon::now(self::zone($businessId));

        return Carbon::parse($local->format('Y-m-d H:i:s'));
    }

    /** Today's date, at 00:00, on the shop's wall clock. */
    public static function today(?int $businessId = null): Carbon
    {
        return self::now($businessId)->startOfDay();
    }

    /**
     * The bounds, in the app zone, of the shop's local day `$day` (default today) — for a column that holds a TRUE
     * instant (checked_in_at, created_at: written in the app zone) and is asked "which local day was this?". Equal to the
     * wall-clock day for a Cairo shop; shifted for a shop on another zone.
     *
     * @return array{0:Carbon,1:Carbon} [start, end]
     */
    public static function instantBounds(?int $businessId = null, ?Carbon $day = null): array
    {
        $zone = self::zone($businessId);
        $date = ($day ?: self::today($businessId))->format('Y-m-d');

        $app = (string) config('app.timezone');

        // set the zone, then re-read the digits as app-zone digits: that is how an instant is stored
        return [
            Carbon::parse(Carbon::createFromFormat('Y-m-d H:i:s', $date . ' 00:00:00', $zone)->setTimezone($app)->format('Y-m-d H:i:s')),
            Carbon::parse(Carbon::createFromFormat('Y-m-d H:i:s', $date . ' 23:59:59', $zone)->setTimezone($app)->format('Y-m-d H:i:s')),
        ];
    }
}
