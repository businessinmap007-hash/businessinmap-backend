<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuCart extends Model
{
    protected $table = 'menu_carts';

    protected $fillable = [
        'user_id',
        'business_id',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'business_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuCartItem::class, 'menu_cart_id');
    }

    public function totalAmount(): float
    {
        return round((float) $this->items()->sum('total_price'), 2);
    }
}
