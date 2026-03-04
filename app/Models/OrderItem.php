<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'menu_item_id',    // لو عندك جدول menu_items
        'qty',
        'price',
        'size',            // small, medium, large (لو بتستعملها)
        'extras',          // JSON لإضافات خاصة
        'notes',
    ];

    protected $casts = [
        'extras' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }
}
