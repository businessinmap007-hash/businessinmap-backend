<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    // Columns match the order_items table: order_id, menu_id, size_id,
    // addons, qty, price, total_price.
    protected $fillable = [
        'order_id',
        'menu_id',
        'size_id',
        'addons',
        'qty',
        'price',
        'total_price',
    ];

    protected $casts = [
        'addons' => 'array',
        'qty' => 'integer',
        'price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class, 'menu_id');
    }
}
