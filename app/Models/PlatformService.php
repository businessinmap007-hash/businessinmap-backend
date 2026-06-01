<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformService extends Model
{
    protected $table = 'platform_services';

    public const KEY_BOOKING  = 'booking';
    public const KEY_MENU     = 'menu';
    public const KEY_DELIVERY = 'delivery';

    protected $fillable = [
        'key',
        'name_ar',
        'name_en',
        'is_active',
        'supports_deposit',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'supports_deposit' => 'boolean',
    ];

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

    /*
    |--------------------------------------------------------------------------
    | Category Service Link Relations
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Legacy Root Category Relations
    |--------------------------------------------------------------------------
    | هذه العلاقات للتوافق الإداري فقط.
    | مصدر تشغيل الخدمات الفعلي يكون من category_platform_services/configs.
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Child Relations
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Parent Categories reached through Children
    |--------------------------------------------------------------------------
    */

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
            ->wherePivot('is_active', true)
            ->wherePivotNotNull('child_id')
            ->withPivot(['category_id', 'child_id', 'is_active', 'sort_order', 'meta'])
            ->withTimestamps()
            ->orderBy('category_platform_services.sort_order')
            ->orderBy('categories.id');
    }

    /*
    |--------------------------------------------------------------------------
    | Config Relations
    |--------------------------------------------------------------------------
    */

    public function rootConfigs(): HasMany
    {
        return $this->hasMany(CategoryServiceConfig::class, 'platform_service_id')
            ->whereNull('child_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function childConfigs(): HasMany
    {
        return $this->hasMany(CategoryServiceConfig::class, 'platform_service_id')
            ->whereNotNull('child_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function activeChildConfigs(): HasMany
    {
        return $this->hasMany(CategoryServiceConfig::class, 'platform_service_id')
            ->whereNotNull('child_id')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Category Child Service Fees
    |--------------------------------------------------------------------------
    | العلاقة هنا للعرض والربط الإداري فقط.
    | حساب رسوم العميل/البزنس يجب أن يتم من CategoryChildServiceFee
    | أو من PlatformServiceFeePromotion كأولوية أعلى.
    |--------------------------------------------------------------------------
    */

    public function categoryChildServiceFees(): HasMany
    {
        return $this->hasMany(CategoryChildServiceFee::class, 'platform_service_id')
            ->orderBy('child_id')
            ->orderByRaw('COALESCE(sort_order, 999999) ASC')
            ->orderBy('id');
    }

    public function activeCategoryChildServiceFees(): HasMany
    {
        return $this->hasMany(CategoryChildServiceFee::class, 'platform_service_id')
            ->where('is_active', 1)
            ->orderBy('child_id')
            ->orderByRaw('COALESCE(sort_order, 999999) ASC')
            ->orderBy('id');
    }

    public function feePromotions(): HasMany
    {
        return $this->hasMany(PlatformServiceFeePromotion::class, 'service_id');
    }

    public function activeFeePromotions(): HasMany
    {
        return $this->hasMany(PlatformServiceFeePromotion::class, 'service_id')
            ->active()
            ->currentlyRunning()
            ->orderedForApply();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isBooking(): bool
    {
        return (string) $this->key === self::KEY_BOOKING;
    }

    public function isMenu(): bool
    {
        return (string) $this->key === self::KEY_MENU;
    }

    public function isDelivery(): bool
    {
        return (string) $this->key === self::KEY_DELIVERY;
    }

    public function supportsDeposit(): bool
    {
        return (bool) $this->supports_deposit;
    }

    public function displayName(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();

        $ar = trim((string) ($this->name_ar ?? ''));
        $en = trim((string) ($this->name_en ?? ''));
        $key = trim((string) ($this->key ?? ''));

        if ($locale === 'ar') {
            return $ar !== '' ? $ar : ($en !== '' ? $en : $key);
        }

        return $en !== '' ? $en : ($ar !== '' ? $ar : $key);
    }
}