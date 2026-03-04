<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $table = 'cart_items';

    protected $fillable = [
        'cart_id',
        'item_id',
        'variant_id',   // nullable
        'qty',
        'unit_price',   // snapshot price وقت الإضافة (مهم)
        'notes',
    ];

    protected $casts = [
        'qty'        => 'integer',
        'unit_price' => 'decimal:2',
    ];

    public function cart()
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function variant()
    {
        return $this->belongsTo(Variant::class, 'variant_id');
    }

    public function extras()
    {
        return $this->hasMany(CartItemExtra::class, 'cart_item_id');
    }

    public function subtotal(): float
    {
        $extrasTotal = $this->extras->sum(function ($x) {
            return (float)$x->unit_price * (int)$x->qty;
        });

        return ((float)$this->unit_price * (int)$this->qty) + $extrasTotal;
    }
}
