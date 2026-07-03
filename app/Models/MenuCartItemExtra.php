<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuCartItemExtra extends Model
{
    protected $table = 'menu_cart_item_extras';

    protected $fillable = [
        'menu_cart_item_id',
        'extra_id',
        'qty',
        'price',
    ];

    protected $casts = [
        'menu_cart_item_id' => 'integer',
        'extra_id' => 'integer',
        'qty' => 'integer',
        'price' => 'decimal:2',
    ];

    public function cartItem(): BelongsTo
    {
        return $this->belongsTo(MenuCartItem::class, 'menu_cart_item_id');
    }

    public function extra(): BelongsTo
    {
        return $this->belongsTo(MenuItemExtra::class, 'extra_id');
    }
}
