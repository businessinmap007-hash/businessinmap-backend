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
        'child_id',
        'platform_service_id',
        'is_active',
        'sort_order',
        'meta',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'child_id' => 'integer',
        'platform_service_id' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'meta' => 'array',
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

    public function child(): BelongsTo
    {
        return $this->belongsTo(CategoryChild::class, 'child_id');
    }

    public function categoryChild(): BelongsTo
    {
        return $this->child();
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

        return $query->where('category_id', (int) $categoryId);
    }

    public function scopeForChild(Builder $query, ?int $childId): Builder
    {
        if (! $childId) {
            return $query;
        }

        return $query->where('child_id', (int) $childId);
    }

    public function scopeForChildren(Builder $query, array $childIds): Builder
    {
        $childIds = collect($childIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($childIds)) {
            return $query;
        }

        return $query->whereIn('child_id', $childIds);
    }

    public function scopeForService(Builder $query, ?int $serviceId): Builder
    {
        if (! $serviceId) {
            return $query;
        }

        return $query->where('platform_service_id', (int) $serviceId);
    }

    public function scopeForServices(Builder $query, array $serviceIds): Builder
    {
        $serviceIds = collect($serviceIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($serviceIds)) {
            return $query;
        }

        return $query->whereIn('platform_service_id', $serviceIds);
    }

    public function scopeForPair(Builder $query, ?int $childId, ?int $serviceId): Builder
    {
        return $query
            ->forChild($childId)
            ->forService($serviceId);
    }

    public function scopeForRootChild(Builder $query, ?int $rootId, ?int $childId): Builder
    {
        return $query
            ->forCategory($rootId)
            ->forChild($childId);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderByRaw('COALESCE(sort_order, 999999) ASC')
            ->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Finders
    |--------------------------------------------------------------------------
    */

    public static function activeForPair(int $childId, int $serviceId): ?self
    {
        if ($childId <= 0 || $serviceId <= 0) {
            return null;
        }

        return static::query()
            ->active(1)
            ->forPair($childId, $serviceId)
            ->ordered()
            ->first();
    }

    public static function activeForRootChild(int $rootId, int $childId, int $serviceId): ?self
    {
        if ($rootId <= 0 || $childId <= 0 || $serviceId <= 0) {
            return null;
        }

        return static::query()
            ->active(1)
            ->forCategory($rootId)
            ->forPair($childId, $serviceId)
            ->ordered()
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function isBooking(): bool
    {
        return (string) ($this->platformService?->key ?? '') === PlatformService::KEY_BOOKING;
    }

    public function isAssignedToChild(): bool
    {
        return (int) ($this->child_id ?? 0) > 0;
    }

    public function isAssignedToCategory(): bool
    {
        return (int) ($this->category_id ?? 0) > 0;
    }

    public function getDisplayNameAttribute(): string
    {
        $root = $this->category?->name_ar
            ?: $this->category?->name_en
            ?: ('Root #' . $this->category_id);

        $child = $this->child?->display_name
            ?: $this->child?->name_ar
            ?: $this->child?->name_en
            ?: ('Child #' . $this->child_id);

        $service = $this->platformService?->display_name
            ?: $this->platformService?->name_ar
            ?: $this->platformService?->name_en
            ?: $this->platformService?->key
            ?: ('Service #' . $this->platform_service_id);

        return "{$root} / {$child} / {$service}";
    }
}