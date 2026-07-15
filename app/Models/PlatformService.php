<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformService extends Model
{
    protected $table = 'platform_services';

    public const KEY_BOOKING = 'booking';
    public const KEY_MENU = 'menu';
    public const KEY_DELIVERY = 'delivery';
    public const KEY_BUSINESS_OFFERS = 'business_offers';
    public const KEY_RETAIL = 'retail';
    public const KEY_SCHEDULES = 'schedules';

    protected $fillable = [
        'key',
        'name_ar',
        'name_en',
        'is_active',
        'sort_order',
        'supports_deposit',
        'max_deposit_percent',
        'rules',
        'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'supports_deposit' => 'boolean',
        'rules' => 'array',
        'meta' => 'array',
    ];

    public function scopeActive(Builder $query, $value = true): Builder
    {
        if ($value === null || $value === '') {
            return $query;
        }

        return $query->where('is_active', (bool) $value);
    }

    public function scopeKey(Builder $query, ?string $key): Builder
    {
        $key = trim((string) $key);

        if ($key === '') {
            return $query;
        }

        return $query->where('key', $key);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('name_ar')
            ->orderBy('name_en')
            ->orderBy('id');
    }

    public function categoryPlatformServices(): HasMany
    {
        return $this->hasMany(CategoryPlatformService::class, 'platform_service_id');
    }

    public function activeCategoryPlatformServices(): HasMany
    {
        return $this->hasMany(CategoryPlatformService::class, 'platform_service_id')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function categoryServiceConfigs(): HasMany
    {
        return $this->hasMany(CategoryServiceConfig::class, 'platform_service_id');
    }

    public function itemTypes(): HasMany
    {
        return $this->hasMany(PlatformServiceItemType::class, 'platform_service_id')
            ->ordered();
    }

    public function activeItemTypes(): HasMany
    {
        return $this->hasMany(PlatformServiceItemType::class, 'platform_service_id')
            ->active()
            ->ordered();
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'category_platform_services',
            'platform_service_id',
            'category_id'
        )
            ->withPivot(['category_id', 'child_id', 'is_active', 'sort_order', 'meta'])
            ->withTimestamps();
    }

    public function activeCategories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'category_platform_services',
            'platform_service_id',
            'category_id'
        )
            ->wherePivot('is_active', true)
            ->wherePivot('child_id', null)
            ->withPivot(['category_id', 'child_id', 'is_active', 'sort_order', 'meta'])
            ->withTimestamps()
            ->orderBy('category_platform_services.sort_order')
            ->orderBy('categories.id');
    }

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(
            CategoryChild::class,
            'category_platform_services',
            'platform_service_id',
            'child_id'
        )
            ->withPivot(['category_id', 'child_id', 'is_active', 'sort_order', 'meta'])
            ->withTimestamps();
    }

    public function activeChildren(): BelongsToMany
    {
        return $this->belongsToMany(
            CategoryChild::class,
            'category_platform_services',
            'platform_service_id',
            'child_id'
        )
            ->wherePivot('is_active', true)
            ->withPivot(['category_id', 'child_id', 'is_active', 'sort_order', 'meta'])
            ->withTimestamps()
            ->orderBy('category_platform_services.sort_order')
            ->orderBy('category_children_master.id');
    }

    public function childrenForParent(?int $parentId): BelongsToMany
    {
        $relation = $this->children();

        if ($parentId && $parentId > 0) {
            $relation->wherePivot('category_id', (int) $parentId);
        }

        return $relation
            ->orderBy('category_platform_services.sort_order')
            ->orderBy('category_children_master.id');
    }

    public function activeChildrenForParent(?int $parentId): BelongsToMany
    {
        $relation = $this->activeChildren();

        if ($parentId && $parentId > 0) {
            $relation->wherePivot('category_id', (int) $parentId);
        }

        return $relation;
    }

    public function parentCategories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'category_platform_services',
            'platform_service_id',
            'category_id'
        )
            ->wherePivotNotNull('child_id')
            ->withPivot(['category_id', 'child_id', 'is_active', 'sort_order', 'meta'])
            ->withTimestamps();
    }

    public function activeParentCategories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'category_platform_services',
            'platform_service_id',
            'category_id'
        )
            ->wherePivotNotNull('child_id')
            ->wherePivot('is_active', true)
            ->withPivot(['category_id', 'child_id', 'is_active', 'sort_order', 'meta'])
            ->withTimestamps();
    }

    public function categoryChildServiceFees(): HasMany
    {
        return $this->hasMany(CategoryChildServiceFee::class, 'platform_service_id');
    }

    public function activeCategoryChildServiceFees(): HasMany
    {
        return $this->hasMany(CategoryChildServiceFee::class, 'platform_service_id')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
