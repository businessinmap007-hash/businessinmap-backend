<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    /** How the order is fulfilled. dine_in links to a table booking. */
    public const FULFILLMENT_DELIVERY = 'delivery';
    public const FULFILLMENT_DINE_IN = 'dine_in';
    public const FULFILLMENT_PICKUP = 'pickup';

    public const FULFILLMENT_TYPES = [
        self::FULFILLMENT_DELIVERY,
        self::FULFILLMENT_DINE_IN,
        self::FULFILLMENT_PICKUP,
    ];

    // Columns match the orders table: total / delivery_fee / discount /
    // final_total (there is no `subtotal` column).
    protected $fillable = [

        'user_id',
        'business_id',
        'fulfillment_type',
        'booking_id',
        'business_table_id',
        'delivery_driver_id',
        'delivery_stage',
        'pickup_token',
        'delivery_token',
        'total',
        'delivery_fee',
        'discount',
        'service_fee',
        'tax',
        'final_total',
        'payment_method',
        'address',
        'notes',
        'status',
        'share_token',
        'is_shared',
        'handover_token',
        'handover_confirmed_at',

    ];

    protected $casts = [
        'booking_id' => 'integer',
        'business_table_id' => 'integer',
        'delivery_driver_id' => 'integer',
        'is_shared' => 'boolean',
        'handover_confirmed_at' => 'datetime',
    ];

    public function businessTable()
    {
        return $this->belongsTo(BusinessTable::class, 'business_table_id');
    }

    public function deliveryDriver()
    {
        return $this->belongsTo(DeliveryDriver::class, 'delivery_driver_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function business()
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function isDineIn(): bool
    {
        return (string) $this->fulfillment_type === self::FULFILLMENT_DINE_IN;
    }

    public function foodTotal(): float
    {
        return round((float) ($this->final_total ?? $this->total ?? 0), 2);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
    public function escrow()
    {
        return $this->hasOne(Escrow::class);
    }

    public function participants()
    {
        return $this->hasMany(OrderParticipant::class);
    }

    public function isSharedCart(): bool
    {
        return (bool) $this->is_shared;
    }

}
