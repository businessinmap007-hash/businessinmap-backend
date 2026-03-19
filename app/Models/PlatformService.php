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

    public function bookingProfiles(): HasMany
    {
        return $this->hasMany(CategoryBookingProfile::class, 'platform_service_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'category_platform_services',
            'platform_service_id',
            'category_id'
        )
            ->withPivot(['is_active', 'sort_order', 'meta'])
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
            ->withPivot(['is_active', 'sort_order', 'meta'])
            ->withTimestamps()
            ->orderBy('category_platform_services.sort_order')
            ->orderBy('categories.id');
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
    public function categoryServiceConfigs()
    {
        return $this->hasMany(CategoryServiceConfig::class, 'platform_service_id');
    }
}