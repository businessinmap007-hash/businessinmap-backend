<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuCartItem extends Model
{
    protected $table = 'menu_cart_items';

    protected $fillable = [
        'menu_cart_id',
        'menu_item_id',
        'variant_id',
        'qty',
        'unit_price',
        'total_price',
        'notes',
    ];

    protected $casts = [
        'menu_cart_id' => 'integer',
        'menu_item_id' => 'integer',
        'variant_id' => 'integer',
        'qty' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(MenuCart::class, 'menu_cart_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(MenuItemVariant::class, 'variant_id');
    }

    public function extras(): HasMany
    {
        return $this->hasMany(MenuCartItemExtra::class, 'menu_cart_item_id');
    }
}
