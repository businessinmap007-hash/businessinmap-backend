<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformService extends Model
{
    protected $table = 'platform_services';

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
    | Relations
    |--------------------------------------------------------------------------
    */

    public function categoryPlatformServices(): HasMany
    {
        return $this->hasMany(CategoryPlatformService::class, 'platform_service_id');
    }

    public function categoryServiceConfigs(): HasMany
    {
        return $this->hasMany(CategoryServiceConfig::class, 'platform_service_id');
    }

    /**
     * Legacy root-level relations
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

    /**
     * Main relation now = category children
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

    /**
     * root containers reached through assigned children
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

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isBooking(): bool
    {
        return (string) $this->key === 'booking';
    }

    public function getDisplayNameAttribute(): string
    {
        return (string) ($this->name_ar ?: $this->name_en ?: $this->key ?: ('Service #' . $this->id));
    }
    public function categoryChildServiceFees()
    {
        return $this->hasMany(CategoryChildServiceFee::class, 'platform_service_id');
    }
}