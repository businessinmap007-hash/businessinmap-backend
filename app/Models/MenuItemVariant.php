<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemVariant extends Model
{
    protected $table = 'menu_item_variants';

    protected $fillable = [
        'menu_item_id',
        'type',
        'name_ar',
        'name_en',
        'price',
        'price_delta',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'menu_item_id' => 'integer',
        'price' => 'decimal:2',
        'price_delta' => 'decimal:2',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Resolves the effective unit price for this variant against an item's base price.
     * A variant may carry its own absolute price, or a delta on top of the base price.
     */
    public function resolvePrice(float $basePrice): float
    {
        if ($this->price !== null && (float) $this->price > 0) {
            return round((float) $this->price, 2);
        }

        return round($basePrice + (float) ($this->price_delta ?? 0), 2);
    }

    public function getDisplayNameAttribute(): string
    {
        return (string) ($this->name_ar ?: ($this->name_en ?: ('Variant #' . $this->id)));
    }
}
