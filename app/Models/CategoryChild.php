<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryChild extends Model
{
    protected $table = 'category_children_master';

    protected $fillable = [
        'name_ar',
        'name_en',
        'reorder',
    ];

    protected $casts = [
        'reorder' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Parent Categories
    |--------------------------------------------------------------------------
    */

    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'category_parent_child',
            'child_id',
            'parent_id'
        )->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | Options
    |--------------------------------------------------------------------------
    */

    public function options(): BelongsToMany
    {
        return $this->belongsToMany(
            Option::class,
            'category_child_option',
            'child_id',
            'option_id'
        )
            ->orderBy('category_child_option.reorder')
            ->orderBy('options.id');
    }

    public function activeOptions(): BelongsToMany
    {
        return $this->belongsToMany(
            Option::class,
            'category_child_option',
            'child_id',
            'option_id'
        )
            ->where('options.is_active', 1)
            ->orderBy('category_child_option.reorder')
            ->orderBy('options.id');
    }

    public function optionLinks(): HasMany
    {
        return $this->hasMany(CategoryChildOption::class, 'child_id')
            ->orderBy('reorder')
            ->orderBy('id');
    }

    /**
     * الجروبات المستخدمة فعليًا داخل options الخاصة بهذا child.
     */
    public function optionGroups()
    {
        return OptionGroup::query()
            ->whereIn('id', function ($query) {
                $query->select('options.group_id')
                    ->from('options')
                    ->join('category_child_option', 'category_child_option.option_id', '=', 'options.id')
                    ->where('category_child_option.child_id', $this->id)
                    ->whereNotNull('options.group_id');
            })
            ->orderBy('reorder')
            ->orderBy('id');
    }

    /**
     * الجروبات النشطة المستخدمة فعليًا داخل options الخاصة بهذا child.
     */
    public function activeOptionGroups()
    {
        return OptionGroup::query()
            ->where('is_active', 1)
            ->whereIn('id', function ($query) {
                $query->select('options.group_id')
                    ->from('options')
                    ->join('category_child_option', 'category_child_option.option_id', '=', 'options.id')
                    ->where('category_child_option.child_id', $this->id)
                    ->where('options.is_active', 1)
                    ->whereNotNull('options.group_id');
            })
            ->orderBy('reorder')
            ->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Platform Services
    |--------------------------------------------------------------------------
    */

    public function platformServices(): BelongsToMany
    {
        return $this->belongsToMany(
            PlatformService::class,
            'category_platform_services',
            'child_id',
            'platform_service_id'
        )
            ->withPivot(['category_id', 'is_active', 'sort_order', 'meta'])
            ->withTimestamps();
    }

    public function activePlatformServices(): BelongsToMany
    {
        return $this->belongsToMany(
            PlatformService::class,
            'category_platform_services',
            'child_id',
            'platform_service_id'
        )
            ->wherePivot('is_active', 1)
            ->withPivot(['category_id', 'is_active', 'sort_order', 'meta'])
            ->withTimestamps()
            ->orderBy('category_platform_services.sort_order')
            ->orderBy('platform_services.id');
    }

    public function platformServicesForParent(?int $parentId): BelongsToMany
    {
        $relation = $this->platformServices();

        if ($parentId && $parentId > 0) {
            $relation->wherePivot('category_id', (int) $parentId);
        }

        return $relation
            ->orderBy('category_platform_services.sort_order')
            ->orderBy('platform_services.id');
    }

    public function activePlatformServicesForParent(?int $parentId): BelongsToMany
    {
        $relation = $this->activePlatformServices();

        if ($parentId && $parentId > 0) {
            $relation->wherePivot('category_id', (int) $parentId);
        }

        return $relation;
    }

    public function hasPlatformService(int|string $serviceIdOrKey, ?int $parentId = null): bool
    {
        $services = $this->activePlatformServicesForParent($parentId)->get();

        return $services->contains(function ($service) use ($serviceIdOrKey) {
            if (is_numeric($serviceIdOrKey)) {
                return (int) $service->id === (int) $serviceIdOrKey;
            }

            return (string) $service->key === (string) $serviceIdOrKey;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Service Fees
    |--------------------------------------------------------------------------
    */

    public function serviceFees(): HasMany
    {
        return $this->hasMany(CategoryChildServiceFee::class, 'child_id')
            ->orderByRaw('COALESCE(sort_order, 999999) ASC')
            ->orderBy('id');
    }

    public function activeServiceFees(): HasMany
    {
        return $this->hasMany(CategoryChildServiceFee::class, 'child_id')
            ->where('is_active', 1)
            ->orderByRaw('COALESCE(sort_order, 999999) ASC')
            ->orderBy('id');
    }

    public function serviceFeeFor(?int $serviceId): ?CategoryChildServiceFee
    {
        if (! $serviceId) {
            return null;
        }

        if ($this->relationLoaded('activeServiceFees')) {
            return $this->activeServiceFees
                ->firstWhere('platform_service_id', (int) $serviceId);
        }

        return $this->activeServiceFees()
            ->where('platform_service_id', (int) $serviceId)
            ->first();
    }

    public function feeSnapshotFor(?int $serviceId): array
    {
        $row = $this->serviceFeeFor($serviceId);

        return [
            'child_id' => (int) $this->id,
            'service_id' => (int) ($serviceId ?? 0),
            'platform_service_id' => (int) ($serviceId ?? 0),
            'business' => $row
                ? $row->toFeeSnapshot(CategoryChildServiceFee::PAYER_BUSINESS)
                : null,
            'client' => $row
                ? $row->toFeeSnapshot(CategoryChildServiceFee::PAYER_CLIENT)
                : null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Display Helpers
    |--------------------------------------------------------------------------
    */

    public function displayName(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();

        if ($locale === 'ar') {
            return (string) ($this->name_ar ?: $this->name_en ?: ('Category Child #' . $this->id));
        }

        return (string) ($this->name_en ?: $this->name_ar ?: ('Category Child #' . $this->id));
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->displayName();
    }
}