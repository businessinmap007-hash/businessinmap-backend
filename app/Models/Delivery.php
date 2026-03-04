<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $fillable = [
        'user_id',
        'driver_id',
        'item_description',
        'pickup_address',
        'pickup_lat',
        'pickup_lng',
        'dropoff_address',
        'dropoff_lat',
        'dropoff_lng',
        'price',
        'payment_method',
        'status',
    ];

    // العميل
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // السائق
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
