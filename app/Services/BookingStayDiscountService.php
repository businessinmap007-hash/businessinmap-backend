<?php

namespace App\Services;

use App\Models\BookableItem;

/**
 * «خصم الأسبوع والشهر» — a longer stay costs less per day.
 *
 * A car, a flat or a shop unit is let by the day, but nobody takes it for a month at thirty times the day's price. The
 * owner names two percentages on the unit itself (`bookable_items.meta.stay_discounts`): one for a stay of a week or
 * more, one for a month or more. The booking engine takes the larger tier the stay reaches off the total — the day price
 * and the season rules stay what they were, so the invoice still reads night by night.
 */
final class BookingStayDiscountService
{
    public const WEEK_NIGHTS = 7;

    public const MONTH_NIGHTS = 30;

    public const MAX_PERCENT = 60;

    /** @return array{weekly_percent:float,monthly_percent:float} always the same shape, set or not */
    public function settings(BookableItem $item): array
    {
        $saved = is_array($item->meta['stay_discounts'] ?? null) ? $item->meta['stay_discounts'] : [];

        return [
            'weekly_percent' => round(max(0, min((float) ($saved['weekly_percent'] ?? 0), self::MAX_PERCENT)), 2),
            'monthly_percent' => round(max(0, min((float) ($saved['monthly_percent'] ?? 0), self::MAX_PERCENT)), 2),
        ];
    }

    /** @return array{weekly_percent:float,monthly_percent:float} */
    public function save(BookableItem $item, float $weekly, float $monthly): array
    {
        $meta = is_array($item->meta) ? $item->meta : [];
        $meta['stay_discounts'] = [
            'weekly_percent' => round(max(0, min($weekly, self::MAX_PERCENT)), 2),
            'monthly_percent' => round(max(0, min($monthly, self::MAX_PERCENT)), 2),
        ];

        $item->update(['meta' => $meta]);

        return $this->settings($item->refresh());
    }

    /** The percentage a stay of [$nights] earns on this unit: the month's if it reaches a month, else the week's, else 0. */
    public function percentFor(BookableItem $item, int $nights): float
    {
        $s = $this->settings($item);

        if ($nights >= self::MONTH_NIGHTS && $s['monthly_percent'] > 0) {
            return $s['monthly_percent'];
        }

        if ($nights >= self::WEEK_NIGHTS && $s['weekly_percent'] > 0) {
            return $s['weekly_percent'];
        }

        return 0.0;
    }
}
