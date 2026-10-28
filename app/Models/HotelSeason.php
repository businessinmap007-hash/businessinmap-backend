<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * «موسم»: a stretch of dates a hotel prices on its own — its rooms (a % or an amount on every room type, or a night price per type),
 * its meal plans, and the shortest stay that may arrive in it. Read by HotelSeasons.
 */
class HotelSeason extends Model
{
    public const CHANGE_PERCENT = 'percent';
    public const CHANGE_AMOUNT = 'amount';

    protected $fillable = ['business_id', 'name', 'start_date', 'end_date', 'room_change_type', 'room_change_value', 'room_prices', 'meal_prices', 'min_nights'];

    protected $casts = [
        'business_id' => 'integer',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'room_change_value' => 'decimal:2',
        'room_prices' => 'array',
        'meal_prices' => 'array',
        'min_nights' => 'integer',
    ];

    public function covers(string $date): bool
    {
        return $this->start_date->toDateString() <= $date && $this->end_date->toDateString() >= $date;
    }

    /** The night price of a room type in this season, from its price outside it. */
    public function roomPrice(int $itemId, float $base): float
    {
        $own = $this->room_prices[(string) $itemId] ?? $this->room_prices[$itemId] ?? null;

        if ($own !== null && $own !== '') {
            return max(round((float) $own, 2), 0);
        }

        return match ($this->room_change_type) {
            self::CHANGE_PERCENT => max(round($base + $base * (float) $this->room_change_value / 100, 2), 0),
            self::CHANGE_AMOUNT => max(round($base + (float) $this->room_change_value, 2), 0),
            default => $base,
        };
    }

    /** The season's price of a meal plan, or null when the season keeps the plan's own price. */
    public function mealPrice(int $optionId): ?float
    {
        $p = $this->meal_prices[(string) $optionId] ?? $this->meal_prices[$optionId] ?? null;

        return $p === null || $p === '' ? null : max(round((float) $p, 2), 0);
    }

    /** @return array<string,mixed> */
    public function payload(): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'room_change_type' => $this->room_change_type,
            'room_change_value' => $this->room_change_value === null ? null : (float) $this->room_change_value,
            'room_prices' => (object) array_map('floatval', (array) ($this->room_prices ?? [])),
            'meal_prices' => (object) array_map('floatval', (array) ($this->meal_prices ?? [])),
            'min_nights' => $this->min_nights,
        ];
    }
}
