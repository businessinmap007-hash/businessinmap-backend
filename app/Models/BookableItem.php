<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookableItem extends Model
{
    protected $table = 'bookable_items';

    protected $fillable = [
        'business_id',
        'service_id',
        'item_type',
        'title',
        'code',
        'price',
        'capacity',
        'quantity',
        'deposit_enabled',
        'deposit_percent',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'business_id'      => 'integer',
        'service_id'       => 'integer',
        'item_type'        => 'string',
        'price'            => 'decimal:2',
        'capacity'         => 'integer',
        'quantity'         => 'integer',
        'deposit_enabled'  => 'boolean',
        'deposit_percent'  => 'integer',
        'is_active'        => 'boolean',
        'meta'             => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(PlatformService::class, 'service_id');
    }

    public function blockedSlots(): HasMany
    {
        return $this->hasMany(BookableItemBlockedSlot::class, 'bookable_item_id');
    }

    public function activeBlockedSlots(): HasMany
    {
        return $this->hasMany(BookableItemBlockedSlot::class, 'bookable_item_id')
            ->where('is_active', true)
            ->orderBy('starts_at')
            ->orderBy('id');
    }

    public function priceRules(): HasMany
    {
        return $this->hasMany(BookableItemPriceRule::class, 'bookable_item_id');
    }

    public function activePriceRules(): HasMany
    {
        return $this->hasMany(BookableItemPriceRule::class, 'bookable_item_id')
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderByDesc('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

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

    public function scopeForService(Builder $query, ?int $serviceId): Builder
    {
        if (! $serviceId) {
            return $query;
        }

        return $query->where('service_id', $serviceId);
    }

    public function scopeForItemType(Builder $query, ?string $itemType): Builder
    {
        $itemType = trim((string) $itemType);

        if ($itemType === '') {
            return $query;
        }

        return $query->where('item_type', $itemType);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function getDisplayNameAttribute(): string
    {
        return (string) ($this->title ?: ($this->code ?: ('Item #' . $this->id)));
    }
}