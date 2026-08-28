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
    protected $fillable = [
        'user_id',
        'business_id',
        'is_active',
        'phone',
        'vehicle_label',
        'assigned_count',
        'picked_up_count',
        'delivered_count',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'business_id' => 'integer',
        'is_active' => 'boolean',
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
}
