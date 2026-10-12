<?php

namespace App\Support;

use App\Services\BusinessHoursService;
use Illuminate\Support\Carbon;

/**
 * «الآن» بساعة الجدار.
 *
 * The platform's own clock is UTC, but every time a business or a customer WRITES — a booking's start, a clinic
 * appointment, an agenda task, a reminder's due time — is stored as the shop's wall-clock digits and comes back from
 * the models as a Carbon whose digits are those (zone: app, i.e. UTC). `now()` is a true instant: setting the two side
 * by side judges "has it started?" three hours wrong in Cairo (a 1-hour reminder for a 7 PM visit fired at 9 PM).
 *
 * `WallClock::now()` is the current moment written in that same notation — the digits on a Cairo wall clock, in a
 * Carbon the models' own times compare with, and a string a SQL bound can take. Use it for ANY comparison between "now"
 * and a stored shop/customer time. Keep `now()` for true instants (created_at, expires_at the server set, sent_at).
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
     * The UTC instants that bound the shop's local day `$day` (default today) — for a column that holds a TRUE instant
     * (checked_in_at, created_at) and is asked "which local day was this?".
     *
     * @return array{0:Carbon,1:Carbon} [start, end]
     */
    public static function instantBounds(?int $businessId = null, ?Carbon $day = null): array
    {
        $zone = self::zone($businessId);
        $date = ($day ?: self::today($businessId))->format('Y-m-d');

        return [
            Carbon::createFromFormat('Y-m-d H:i:s', $date . ' 00:00:00', $zone)->utc(),
            Carbon::createFromFormat('Y-m-d H:i:s', $date . ' 23:59:59', $zone)->utc(),
        ];
    }
}
