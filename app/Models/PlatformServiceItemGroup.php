<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A "branch" that groups item types under a platform service.
 *
 * e.g. under the `booking` service: hotel / clinic / sports / restaurant_table.
 * See docs (BIM-2) — this is purely an organizational layer for the admin; the
 * booking/pricing logic still keys on the item type `key`, not the group.
 */
class PlatformServiceItemGroup extends Model
{
    protected $table = 'platform_service_item_groups';

    protected $fillable = [
        'platform_service_id',
        'key',
        'name_ar',
        'name_en',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'platform_service_id' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(PlatformService::class, 'platform_service_id');
    }

    public function platformService(): BelongsTo
    {
        return $this->service();
    }

    public function itemTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            PlatformServiceItemType::class,
            'platform_service_item_group_type',
            'group_id',
            'item_type_id'
        )->orderByRaw('COALESCE(platform_service_item_types.sort_order, 999999) ASC')
         ->orderBy('platform_service_item_types.id');
    }

    public function activeItemTypes(): BelongsToMany
    {
        return $this->itemTypes()->where('platform_service_item_types.is_active', 1);
    }

    public function scopeActive($query, bool $active = true)
    {
        return $query->where('is_active', $active ? 1 : 0);
    }

    public function scopeForService($query, int $serviceId)
    {
        return $query->where('platform_service_id', $serviceId);
    }

    public function scopeOrdered($query)
    {
        return $query
            ->orderByRaw('COALESCE(sort_order, 999999) ASC')
            ->orderBy('id');
    }

    public function displayName(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();

        $ar = trim((string) ($this->name_ar ?? ''));
        $en = trim((string) ($this->name_en ?? ''));

        if ($locale === 'ar') {
            return $ar !== '' ? $ar : ($en !== '' ? $en : (string) $this->key);
        }

        return $en !== '' ? $en : ($ar !== '' ? $ar : (string) $this->key);
    }
}
