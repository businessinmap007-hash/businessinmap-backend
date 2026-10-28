<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A hotel's «سياسة الأطفال»: age bands with the percentage of the night's price a child pays (0 = free), the age from which a child
 * takes a place in the room's capacity, the age from which a guest is an adult, and whether a child's meals follow the same percentage.
 */
class ChildPolicy extends Model
{
    protected $fillable = ['business_id', 'adult_from_age', 'counts_from_age', 'meals_follow', 'extra_bed_price', 'extra_beds_max', 'single_parent_percent', 'bands'];

    protected $casts = [
        'business_id' => 'integer',
        'adult_from_age' => 'integer',
        'counts_from_age' => 'integer',
        'meals_follow' => 'boolean',
        'extra_bed_price' => 'decimal:2',
        'extra_beds_max' => 'integer',
        'single_parent_percent' => 'decimal:2',
        'bands' => 'array',
    ];

    /** How many extra beds a stay of $rooms rooms may take — none when the hotel wrote no extra-bed price. */
    public function extraBedsAllowed(int $rooms = 1): int
    {
        return $this->extra_bed_price === null ? 0 : max((int) $this->extra_beds_max, 0) * max($rooms, 1);
    }

    public static function forBusiness(int $businessId): ?self
    {
        return $businessId > 0 ? static::query()->where('business_id', $businessId)->first() : null;
    }

    /** Is a guest of this age an adult (inside the room's own price, counted like any guest)? */
    public function isAdult(int $age): bool
    {
        return $age >= (int) $this->adult_from_age;
    }

    /** Does a child of this age take a place in the room's capacity? */
    public function counts(int $age): bool
    {
        return $age >= (int) $this->counts_from_age;
    }

    /**
     * The percentage of one person's night a child of this age pays — as the room's FIRST child, or as a further one («الطفل الأول مجانًا،
     * الثاني 50%» — the way Egyptian rate sheets write it). An age no band covers pays nothing.
     */
    public function percentFor(int $age, bool $first = true): float
    {
        if ($this->isAdult($age)) {
            return 0.0; // an adult is inside the room's own price
        }

        foreach ((array) $this->bands as $band) {
            if ($age >= (int) ($band['from'] ?? 0) && $age <= (int) ($band['to'] ?? -1)) {
                $percent = $first ? ($band['percent'] ?? 0) : ($band['second_percent'] ?? $band['percent'] ?? 0);

                return max(min((float) $percent, 100), 0);
            }
        }

        return 0.0;
    }

    /** @return array<string,mixed> the policy as the app reads it */
    public function payload(): array
    {
        return [
            'adult_from_age' => (int) $this->adult_from_age,
            'counts_from_age' => (int) $this->counts_from_age,
            'meals_follow' => (bool) $this->meals_follow,
            'extra_bed_price' => $this->extra_bed_price === null ? null : (float) $this->extra_bed_price,
            'extra_beds_max' => (int) $this->extra_beds_max,
            // «بالغ واحد مع طفل»: what the child in the second adult's place pays of one person's night (null = its normal share)
            'single_parent_percent' => $this->single_parent_percent === null ? null : (float) $this->single_parent_percent,
            'bands' => array_values(array_map(fn ($b) => [
                'from' => (int) ($b['from'] ?? 0),
                'to' => (int) ($b['to'] ?? 0),
                'percent' => (float) ($b['percent'] ?? 0),
                'second_percent' => (float) ($b['second_percent'] ?? $b['percent'] ?? 0),
            ], (array) $this->bands)),
        ];
    }
}
