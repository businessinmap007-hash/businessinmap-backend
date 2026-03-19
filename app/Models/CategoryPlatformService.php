<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryPlatformService extends Model
{
    protected $table = 'category_platform_services';

    protected $fillable = [
        'category_id',
        'platform_service_id',
        'is_active',
        'sort_order',
        'meta',
    ];

    protected $casts = [
        'category_id'         => 'integer',
        'platform_service_id' => 'integer',
        'is_active'           => 'boolean',
        'sort_order'          => 'integer',
        'meta'                => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function platformService(): BelongsTo
    {
        return $this->belongsTo(PlatformService::class, 'platform_service_id');
    }

    public function service(): BelongsTo
    {
        return $this->platformService();
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query, $value = true): Builder
    {
        if ($value === null || $value === '') {
            return $query;
        }

        return $query->where('is_active', (bool) $value);
    }

    public function scopeForCategory(Builder $query, ?int $categoryId): Builder
    {
        if (! $categoryId) {
            return $query;
        }

        return $query->where('category_id', $categoryId);
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

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isBooking(): bool
    {
        return (string) ($this->platformService?->key ?? '') === 'booking';
    }
}