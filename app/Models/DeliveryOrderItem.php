<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryOrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'item_name',
        'weight',
        'quantity',
        'price',
        'total',
        'notes',
    ];

    public function order()
    {
        return $this->belongsTo(DeliveryOrder::class, 'order_id');
    }
}



