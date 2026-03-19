<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryServiceConfig extends Model
{
    protected $table = 'category_service_configs';

    protected $fillable = [
        'category_id',
        'platform_service_id',
        'config',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'platform_service_id' => 'integer',
        'config' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function platformService(): BelongsTo
    {
        return $this->belongsTo(PlatformService::class, 'platform_service_id');
    }

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

    public function getValue(string $key, mixed $default = null): mixed
    {
        $config = is_array($this->config) ? $this->config : [];

        return data_get($config, $key, $default);
    }
}