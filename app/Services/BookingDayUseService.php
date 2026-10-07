<?php

namespace App\Services;

use App\Models\BookableItem;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * «خدمة Day use للحجز في الفنادق» — a room used through the day, with no night.
 *
 * It is a second way of selling a room TYPE the hotel already has, not a second room: the type says «from 09:00 to
 * 18:00, for 350» and a guest who picks Day use gives only a date. The booking holds one room of that type for that
 * window — the same overlap rule an overnight stay uses, so a room cannot be sold for the afternoon and the night at
 * once — and is priced at the flat day-use price instead of a night's price.
 *
 * Stored on the type itself (`bookable_items.meta.day_use`); nothing else about the type changes.
 */
final class BookingDayUseService
{
    /** A day-use window is a stretch of the day — never so short it is a nap, never so long it is a night. */
    private const MIN_HOURS = 2;

    private const MAX_HOURS = 16;

    /** What the hotel has set on a room type — always the same shape, enabled or not. */
    public function settings(BookableItem $item): array
    {
        $saved = is_array($item->meta['day_use'] ?? null) ? $item->meta['day_use'] : [];

        return [
            'enabled' => (bool) ($saved['enabled'] ?? false),
            'from' => $saved['from'] ?? '09:00',
            'to' => $saved['to'] ?? '18:00',
            'price' => isset($saved['price']) ? (float) $saved['price'] : null,
        ];
    }

    /** What a guest may be offered: null unless this room type sells Day use right now. */
    public function offer(BookableItem $item): ?array
    {
        if ($item->item_type !== 'booking_stay' || ! $item->is_active) {
            return null;
        }

        $settings = $this->settings($item);

        if (! $settings['enabled'] || ! $settings['price'] || $settings['price'] <= 0) {
            return null;
        }

        return ['from' => $settings['from'], 'to' => $settings['to'], 'price' => $settings['price']];
    }

    public function save(BookableItem $item, bool $enabled, string $from, string $to, ?float $price): array
    {
        if ($item->item_type !== 'booking_stay') {
            throw ValidationException::withMessages(['day_use' => __('Day use للفنادق والإقامة فقط.')]);
        }

        if ($enabled) {
            $hours = $this->hoursBetween($from, $to);

            if ($hours < self::MIN_HOURS || $hours > self::MAX_HOURS) {
                throw ValidationException::withMessages(['to' => __('مدة Day use بين :min و:max ساعة.', ['min' => self::MIN_HOURS, 'max' => self::MAX_HOURS])]);
            }

            if (! $price || $price <= 0) {
                throw ValidationException::withMessages(['price' => __('اكتب سعر Day use.')]);
            }
        }

        $meta = is_array($item->meta) ? $item->meta : [];
        $meta['day_use'] = ['enabled' => $enabled, 'from' => $from, 'to' => $to, 'price' => $price];
        $item->forceFill(['meta' => $meta])->save();

        return $this->settings($item);
    }

    /**
     * The window a guest holds when they choose Day use on [date].
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function window(BookableItem $item, string $date): array
    {
        $offer = $this->offer($item);

        if (! $offer) {
            throw ValidationException::withMessages(['bookable_id' => __('هذه الغرفة لا تُحجز Day use.')]);
        }

        $day = Carbon::parse($date)->startOfDay();
        $start = $day->copy()->setTimeFromTimeString($offer['from']);
        $end = $day->copy()->setTimeFromTimeString($offer['to']);

        if ($start->lte(now())) {
            throw ValidationException::withMessages(['date' => __('اختر موعدًا لم يبدأ بعد.')]);
        }

        return [$start, $end];
    }

    /** Whole hours the window lasts, rounded up — the booking's duration. */
    public function hours(Carbon $start, Carbon $end): int
    {
        return max((int) ceil($start->diffInMinutes($end) / 60), 1);
    }

    /**
     * The price a Day use booking carries: the flat day-use price, once per room. No night's price, no per-night
     * extras, no discount of the nightly row — the hotel named one price for the day.
     *
     * The one thing that rides on it is the meals the guest ticked (فطار / غداء / عشاء): they are priced by the hotel
     * like any add-on and counted once per room.
     *
     * @param  array{base:float,total:float,lines:array<int,array<string,mixed>>}|null  $meals
     */
    public function breakdown(array $base, BookableItem $item, int $quantity, string $date, ?array $meals = null): array
    {
        $offer = $this->offer($item);
        $price = round((float) ($offer['price'] ?? 0), 2);
        $quantity = max($quantity, 1);
        $extras = round((float) ($meals['total'] ?? 0), 2);
        $unit = round($price + $extras, 2);

        return array_merge($base, [
            'source' => 'day_use',
            'unit_price' => $unit,
            'base_unit_price' => $price,
            'price_rule' => null,
            'modifiers' => $meals['lines'] ?? [],
            'modifiers_total' => $extras,
            'periods' => [['date' => $date, 'base_price' => $price, 'modifiers_total' => $extras, 'price' => $unit, 'rule' => null]],
            'periods_count' => 1,
            'period_unit' => 'day_use',
            'units' => $quantity,
            'quantity' => $quantity,
            'original_price' => round($unit * $quantity, 2),
            'discount_enabled' => false,
            'discount_percent' => 0,
            'discount_amount' => 0.0,
            'final_price' => round($unit * $quantity, 2),
        ]);
    }

    private function hoursBetween(string $from, string $to): float
    {
        $start = Carbon::createFromFormat('H:i', $from);
        $end = Carbon::createFromFormat('H:i', $to);

        return $start->diffInMinutes($end, false) / 60;
    }
}
