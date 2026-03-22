<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'image',
        'parent_id',
        'is_active',
        'per_month',
        'per_year',
        'reorder',
        'name_ar',
        'name_en',
        'slug',
        'meta',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'is_active' => 'boolean',
        'per_month' => 'float',
        'per_year'  => 'float',
        'reorder'   => 'integer',
        'meta'      => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeParentCategory(Builder $query): Builder
    {
        return $query->where('parent_id', 0);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->where('parent_id', 0);
    }

    public function scopeParentsOnly(Builder $query): Builder
    {
        return $query->where('parent_id', 0);
    }

    public function scopeChildrenOnly(Builder $query): Builder
    {
        return $query->where('parent_id', '>', 0);
    }

    public function scopeActive(Builder $query, $value = true): Builder
    {
        if ($value === null || $value === '') {
            return $query;
        }

        return $query->where('is_active', (bool) $value);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderByRaw('COALESCE(reorder, 999999) ASC')
            ->orderByDesc('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Base Relations
    |--------------------------------------------------------------------------
    */

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Legacy old direct children from categories table.
     * Keep temporarily فقط للبيانات القديمة إن احتجتها.
     */
    public function legacyChildren(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->where('parent_id', '>', 0)
            ->orderByRaw('COALESCE(reorder, 999999) ASC')
            ->orderByDesc('id');
    }

    /**
     * Legacy active direct children from categories table.
     */
    public function activeLegacyChildren(): HasMany
    {
        return $this->legacyChildren()
            ->where('is_active', true);
    }

    /**
     * New normalized children through pivot table.
     */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(
            CategoryChild::class,
            'category_parent_child',
            'parent_id',
            'child_id'
        )->withTimestamps();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Legacy relation only if you still have category_id in users table.
     * Keep it as-is if used elsewhere.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'category_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Category <-> Platform Services
    |--------------------------------------------------------------------------
    */

    public function categoryPlatformServices(): HasMany
    {
        return $this->hasMany(CategoryPlatformService::class, 'category_id');
    }

    public function activeCategoryPlatformServices(): HasMany
    {
        return $this->categoryPlatformServices()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function platformServices(): BelongsToMany
    {
        return $this->belongsToMany(
            PlatformService::class,
            'category_platform_services',
            'category_id',
            'platform_service_id'
        )
            ->withPivot(['is_active', 'sort_order', 'meta'])
            ->withTimestamps();
    }

    public function activePlatformServices(): BelongsToMany
    {
        return $this->belongsToMany(
            PlatformService::class,
            'category_platform_services',
            'category_id',
            'platform_service_id'
        )
            ->wherePivot('is_active', true)
            ->withPivot(['is_active', 'sort_order', 'meta'])
            ->withTimestamps()
            ->orderBy('category_platform_services.sort_order')
            ->orderBy('platform_services.id');
    }

    public function bookingServiceLinks(): HasMany
    {
        return $this->activeCategoryPlatformServices()
            ->whereHas('platformService', function ($q) {
                $q->where('key', 'booking');
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Service Configs
    |--------------------------------------------------------------------------
    */

    public function serviceConfigs(): HasMany
    {
        return $this->hasMany(CategoryServiceConfig::class, 'category_id');
    }

    public function activeServiceConfigs(): HasMany
    {
        return $this->serviceConfigs()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isRoot(): bool
    {
        return (int) $this->parent_id === 0;
    }

    public function isLegacyChild(): bool
    {
        return (int) $this->parent_id > 0;
    }

    public function displayName(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();

        if ($locale === 'ar') {
            return (string) ($this->name_ar ?: $this->name_en ?: ('Category #' . $this->id));
        }

        return (string) ($this->name_en ?: $this->name_ar ?: ('Category #' . $this->id));
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->displayName();
    }

    public function hasService(int|string $serviceIdOrKey): bool
    {
        $services = $this->relationLoaded('activePlatformServices')
            ? $this->activePlatformServices
            : $this->activePlatformServices()->get();

        return $services->contains(function ($service) use ($serviceIdOrKey) {
            if (is_numeric($serviceIdOrKey)) {
                return (int) $service->id === (int) $serviceIdOrKey;
            }

            return (string) $service->key === (string) $serviceIdOrKey;
        });
    }

    public function getServiceConfig(string $serviceKey): array
    {
        $serviceKey = trim($serviceKey);

        if ($serviceKey === '') {
            return [];
        }

        $service = PlatformService::query()
            ->where('key', $serviceKey)
            ->first(['id']);

        if (! $service) {
            return [];
        }

        if ($this->relationLoaded('serviceConfigs')) {
            $configRow = $this->serviceConfigs->first(function ($row) use ($service) {
                return (int) $row->platform_service_id === (int) $service->id;
            });

            $config = $configRow?->config ?? [];

            return is_array($config) ? $config : [];
        }

        $configRow = $this->serviceConfigs()
            ->where('platform_service_id', $service->id)
            ->first();

        $config = $configRow?->config ?? [];

        return is_array($config) ? $config : [];
    }

    public function bookingAllowedItemTypes(): array
    {
        $config = $this->getServiceConfig('booking');
        $values = $config['allowed_item_types'] ?? [];

        if (! is_array($values)) {
            return [];
        }

        return collect($values)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function bookingModes(): array
    {
        $config = $this->getServiceConfig('booking');
        $values = $config['booking_modes'] ?? [];

        if (! is_array($values)) {
            return [];
        }

        return collect($values)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function bookingRequiredFields(): array
    {
        $config = $this->getServiceConfig('booking');
        $values = $config['required_fields'] ?? [];

        if (! is_array($values)) {
            return [];
        }

        return collect($values)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function bookingItemFamily(): ?string
    {
        $config = $this->getServiceConfig('booking');
        $value = trim((string) ($config['item_family'] ?? ''));

        return $value !== '' ? $value : null;
    }
}