<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class CartItemAddon extends Model
{
    protected $fillable = [
        'cart_item_id',
        'addon_id',
        'price',
    ];

    public function item()
    {
        return $this->belongsTo(CartItem::class, 'cart_item_id');
    }
}
