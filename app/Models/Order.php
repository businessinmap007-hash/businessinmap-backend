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

    protected $fillable = [

        'user_id',
        'business_id',
        'fulfillment_type',
        'booking_id',
        'subtotal',
        'delivery_fee',
        'total',
        'payment_method',
        'address',
        'notes',
        'status',

    ];

    protected $casts = [
        'booking_id' => 'integer',
    ];

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
        return round((float) ($this->total ?? $this->subtotal ?? 0), 2);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
    public function escrow()
    {
        return $this->hasOne(Escrow::class);
    }

}
