<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * «اكتمل العدد اليوم» — the business's OPTIONAL button (never automatic: a doctor may rather add hours). It closes NEW
 * bookings for one date, for every kind of dated booking the business takes (clinic appointments, bookings of units,
 * appointments); reopened by the same button. One date per business, kept on the account.
 */
final class DayClosure
{
    public static function closedDate(User $business): ?string
    {
        return $business->booking_closed_date ? Carbon::parse($business->booking_closed_date)->toDateString() : null;
    }

    /** Close (or reopen) — returns the date now closed, or null. */
    public static function set(User $business, bool $closed, ?string $date = null): ?string
    {
        $business->booking_closed_date = $closed ? Carbon::parse($date ?? WallClock::now())->toDateString() : null;
        $business->save();

        return self::closedDate($business);
    }

    public static function isClosedOn(User $business, mixed $when): bool
    {
        $closed = self::closedDate($business);

        return $closed !== null && $when && Carbon::parse($when)->toDateString() === $closed;
    }

    /** A customer is refused a date the business closed for new bookings. */
    public static function assertOpen(User $business, mixed $when, string $field = 'scheduled_at'): void
    {
        if (self::isClosedOn($business, $when)) {
            throw ValidationException::withMessages([$field => __('اكتمل العدد في هذا اليوم. اختر يومًا آخر.')]);
        }
    }
}
