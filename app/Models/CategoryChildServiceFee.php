<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryChildServiceFee extends Model
{
    protected $table = 'category_child_service_fees';

    protected $fillable = [
        'child_id',
        'platform_service_id',
        'business_fee_enabled',
        'business_fee_amount',
        'client_fee_enabled',
        'client_fee_amount',
        'currency',
        'is_active',
        'sort_order',
        'notes',
    ];

    protected $casts = [
        'child_id'              => 'integer',
        'platform_service_id'   => 'integer',
        'business_fee_enabled'  => 'boolean',
        'business_fee_amount'   => 'decimal:2',
        'client_fee_enabled'    => 'boolean',
        'client_fee_amount'     => 'decimal:2',
        'is_active'             => 'boolean',
        'sort_order'            => 'integer',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(CategoryChild::class, 'child_id');
    }

    public function platformService(): BelongsTo
    {
        return $this->belongsTo(PlatformService::class, 'platform_service_id');
    }

    public function service(): BelongsTo
    {
        return $this->platformService();
    }

    public function scopeActive(Builder $query, $value = true): Builder
    {
        if ($value === null || $value === '') {
            return $query;
        }

        return $query->where('is_active', (bool) $value);
    }

    public function scopeForChild(Builder $query, ?int $childId): Builder
    {
        if (! $childId) {
            return $query;
        }

        return $query->where('child_id', $childId);
    }

    public function scopeForService(Builder $query, ?int $serviceId): Builder
    {
        if (! $serviceId) {
            return $query;
        }

        return $query->where('platform_service_id', $serviceId);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function hasBusinessFee(): bool
    {
        return (bool) $this->business_fee_enabled && (float) $this->business_fee_amount > 0;
    }

    public function hasClientFee(): bool
    {
        return (bool) $this->client_fee_enabled && (float) $this->client_fee_amount > 0;
    }
}