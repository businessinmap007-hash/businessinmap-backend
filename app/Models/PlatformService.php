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

    public const FEE_TYPE_FIXED   = 'fixed';
    public const FEE_TYPE_PERCENT = 'percent';

    protected $fillable = [
        'key',
        'name_ar',
        'name_en',
        'is_active',
        'supports_deposit',
        'max_deposit_percent',
        'fee_type',
        'fee_value',
        'rules',
    ];

    protected $casts = [
        'is_active'           => 'boolean',
        'supports_deposit'    => 'boolean',
        'max_deposit_percent' => 'integer',
        'fee_value'           => 'decimal:2',
        'rules'               => 'array',
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
    | هذه العلاقات موجودة للتوافق مع أي أجزاء قديمة.
    | المصدر العملي الجديد للخدمات هو children/category_platform_services.
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
    | Main Child Relations
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
    | Service Fees
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

    public function feeForChild(?int $childId): ?CategoryChildServiceFee
    {
        if (! $childId) {
            return null;
        }

        if ($this->relationLoaded('activeCategoryChildServiceFees')) {
            return $this->activeCategoryChildServiceFees
                ->firstWhere('child_id', (int) $childId);
        }

        return $this->activeCategoryChildServiceFees()
            ->where('child_id', (int) $childId)
            ->first();
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

    public function maxDepositPercent(): int
    {
        return max(0, min((int) ($this->max_deposit_percent ?? 0), 100));
    }

    public function hasLegacyPlatformFee(): bool
    {
        return in_array((string) $this->fee_type, [
            self::FEE_TYPE_FIXED,
            self::FEE_TYPE_PERCENT,
        ], true) && (float) ($this->fee_value ?? 0) > 0;
    }

    public function calculateLegacyPlatformFee(float $price): float
    {
        $price = round((float) $price, 2);
        $feeValue = round((float) ($this->fee_value ?? 0), 2);

        if ($price <= 0 || $feeValue <= 0) {
            return 0.00;
        }

        return match ((string) $this->fee_type) {
            self::FEE_TYPE_FIXED => $feeValue,
            self::FEE_TYPE_PERCENT => round($price * ($feeValue / 100), 2),
            default => 0.00,
        };
    }

    public function displayName(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();

        if ($locale === 'ar') {
            return (string) ($this->name_ar ?: $this->name_en ?: $this->key ?: ('Service #' . $this->id));
        }

        return (string) ($this->name_en ?: $this->name_ar ?: $this->key ?: ('Service #' . $this->id));
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->displayName();
    }
}