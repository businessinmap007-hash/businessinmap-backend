<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A v2 delivery driver. (Replaces a dead never-migrated stub; the legacy V1
 * courier lives in App\Models\Courier and is untouched.) One row per driver
 * user, with lifetime counters; per-delivery success is also recorded in
 * delivery_completions. See DeliveryDispatchService.
 */
class DeliveryDriver extends Model
{
    protected $fillable = [
        'user_id',
        'is_active',
        'phone',
        'vehicle_label',
        'assigned_count',
        'picked_up_count',
        'delivered_count',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'is_active' => 'boolean',
        'assigned_count' => 'integer',
        'picked_up_count' => 'integer',
        'delivered_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
