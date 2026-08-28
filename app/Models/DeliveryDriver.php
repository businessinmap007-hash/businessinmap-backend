<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A v2 delivery driver. (Replaces a dead never-migrated stub; the legacy V1
 * courier lives in App\Models\Courier and is untouched.) One row per driver
 * user, with lifetime counters; per-delivery success is also recorded in
 * delivery_completions. See DeliveryDispatchService.
 *
 * `business_id` NULL is the original platform-wide freelance pool — any
 * ready order from any business. A set `business_id` is a business's own
 * private driver (added 2026-08-28): the same accept/pickup/deliver loop,
 * scoped to only that business's orders.
 */
class DeliveryDriver extends Model
{
    /** A ping older than this is not a position any more — show "unavailable", not a stale dot on a map. */
    public const LOCATION_STALE_AFTER_MINUTES = 10;

    protected $fillable = [
        'user_id',
        'business_id',
        'is_active',
        'phone',
        'vehicle_label',
        'last_lat',
        'last_lng',
        'location_updated_at',
        'assigned_count',
        'picked_up_count',
        'delivered_count',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'business_id' => 'integer',
        'is_active' => 'boolean',
        'last_lat' => 'float',
        'last_lng' => 'float',
        'location_updated_at' => 'datetime',
        'assigned_count' => 'integer',
        'picked_up_count' => 'integer',
        'delivered_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The business this driver privately works for, or null for the freelance pool. */
    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function hasFreshLocation(): bool
    {
        return $this->last_lat !== null
            && $this->last_lng !== null
            && $this->location_updated_at !== null
            && $this->location_updated_at->gt(now()->subMinutes(self::LOCATION_STALE_AFTER_MINUTES));
    }

    /** Distance to a point, or null when this driver has no fresh position to measure from. */
    public function distanceKmTo(?float $lat, ?float $lng): ?float
    {
        if (! $this->hasFreshLocation() || $lat === null || $lng === null) {
            return null;
        }

        return self::haversineKm($this->last_lat, $this->last_lng, $lat, $lng);
    }

    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
