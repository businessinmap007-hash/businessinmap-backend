<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItemExtra extends Model
{
    protected $table = 'cart_item_extras';

    protected $fillable = [
        'cart_item_id',
        'extra_id',
        'qty',
        'unit_price', // snapshot price وقت الإضافة
    ];

    protected $casts = [
        'qty'        => 'integer',
        'unit_price' => 'decimal:2',
    ];

    public function cartItem()
    {
        return $this->belongsTo(CartItem::class, 'cart_item_id');
    }

    public function extra()
    {
        return $this->belongsTo(Extra::class, 'extra_id');
    }
}
