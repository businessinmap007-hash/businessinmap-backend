<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookableItem extends Model
{
    protected $table = 'bookable_items';

    // Units are inventory only. Price and deposit are single-source in
    // business_service_prices (per item type); the legacy price/deposit_*
    // columns were dropped from bookable_items. See services-blueprint.md.
    protected $fillable = [
        'business_id',
        'service_id',
        'item_type',
        'title',
        'code',
        'capacity',
        'quantity',

        'is_active',
        'meta',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'service_id' => 'integer',
        'item_type' => 'string',
        'capacity' => 'integer',
        'quantity' => 'integer',

        'is_active' => 'boolean',
        'meta' => 'array',
    ];

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

    public function getDisplayNameAttribute(): string
    {
        return (string) ($this->title ?: ($this->code ?: ('Item #' . $this->id)));
    }

    /**
     * The unit's base price, single-sourced from the BusinessServicePrice for
     * its item type (bookable_items no longer carries a price column). Resolved
     * lazily; call only where a single unit's base price is needed, not in lists.
     */
    public function resolvedBasePrice(): float
    {
        $price = app(\App\Services\BusinessServicePriceResolver::class)
            ->resolveForBookableItem($this);

        return round((float) ($price?->baseUnitPrice() ?? 0), 2);
    }
}