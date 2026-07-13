<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The success ledger for a completed delivery — one row per delivered order.
 * Count by business_id for a restaurant's successful deliveries, by
 * driver_user_id / delivery_driver_id for a driver's. See DeliveryDispatchService.
 */
class DeliveryCompletion extends Model
{
    protected $fillable = [
        'order_id',
        'business_id',
        'delivery_driver_id',
        'driver_user_id',
        'completed_at',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'business_id' => 'integer',
        'delivery_driver_id' => 'integer',
        'driver_user_id' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(DeliveryDriver::class, 'delivery_driver_id');
    }
}
