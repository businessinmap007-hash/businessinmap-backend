<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryOrder extends Model
{
    protected $table = 'delivery_orders';

    protected $fillable = [
        'user_id',
        'business_id',
        'pickup_address',
        'pickup_lat',
        'pickup_lng',
        'dropoff_address',
        'dropoff_lat',
        'dropoff_lng',
        'delivery_type',
        'weight',
        'price_estimated',
        'price_final',
        'payment_method',
        'price',
        'status',
        'notes',
        'delivered_image',
        'courier_id'   // مهم: وليس driver_id
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function business() {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function courier() {
        return $this->belongsTo(User::class, 'courier_id');
    }
        public function items()
    {
        return $this->hasMany(DeliveryOrderItem::class, 'order_id');
    }

}
