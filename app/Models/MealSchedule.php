<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A patient's usual meal times, used to schedule food-tied medication doses.
 */
class MealSchedule extends Model
{
    protected $fillable = [
        'user_id',
        'breakfast_at',
        'lunch_at',
        'dinner_at',
    ];

    /** Sensible defaults for a patient who has not set their meal times. */
    public const DEFAULTS = [
        'breakfast_at' => '08:00:00',
        'lunch_at' => '14:00:00',
        'dinner_at' => '20:00:00',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The stored time (H:i:s) for a named slot, falling back to the default. */
    public function timeFor(string $slot): string
    {
        return match ($slot) {
            'breakfast', 'morning' => (string) ($this->breakfast_at ?? self::DEFAULTS['breakfast_at']),
            'lunch', 'noon' => (string) ($this->lunch_at ?? self::DEFAULTS['lunch_at']),
            'dinner', 'evening' => (string) ($this->dinner_at ?? self::DEFAULTS['dinner_at']),
            default => self::DEFAULTS['dinner_at'],
        };
    }
}
