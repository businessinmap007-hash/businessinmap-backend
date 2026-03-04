<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
       
        'user_id',
        'business_id',
        'subtotal',
        'delivery_fee',
        'total',
        'payment_method',
        'address',
        'notes',
        'status',
        
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function business()
    {
        return $this->belongsTo(User::class, 'business_id');
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
