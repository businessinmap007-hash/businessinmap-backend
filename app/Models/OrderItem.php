<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    // Columns match the order_items table. `offering_type`/`offering_id` are the
    // polymorphic offering reference (menu item now; catalog listing / bookable
    // type later); `menu_id` is kept for backward compatibility.
    protected $fillable = [
        'order_id',
        'menu_id',
        'offering_type',
        'offering_id',
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

    /**
     * The offering this line refers to (MenuItem now; catalog listing / bookable
     * item type later). Polymorphic — see the Phase 3 offering layer.
     */
    public function offering()
    {
        return $this->morphTo(__FUNCTION__, 'offering_type', 'offering_id');
    }
}
