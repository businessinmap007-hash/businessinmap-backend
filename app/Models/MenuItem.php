<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    protected $table = 'menu_items';

    protected $fillable = [
        'business_id',
        'category_id',
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'image',
        'base_price',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'category_id' => 'integer',
        'base_price' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(MenuItemVariant::class, 'menu_item_id');
    }

    public function activeVariants(): HasMany
    {
        return $this->hasMany(MenuItemVariant::class, 'menu_item_id')
            ->where('is_active', true);
    }

    public function extras(): HasMany
    {
        return $this->hasMany(MenuItemExtra::class, 'menu_item_id');
    }

    public function activeExtras(): HasMany
    {
        return $this->hasMany(MenuItemExtra::class, 'menu_item_id')
            ->where('is_active', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForBusiness(Builder $query, ?int $businessId): Builder
    {
        if (! $businessId) {
            return $query;
        }

        return $query->where('business_id', $businessId);
    }

    public function getDisplayNameAttribute(): string
    {
        return (string) ($this->name_ar ?: ($this->name_en ?: ('Item #' . $this->id)));
    }
}
