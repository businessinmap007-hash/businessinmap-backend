<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformServiceItemType extends Model
{
    protected $table = 'platform_service_item_types';

    protected $fillable = [
        'platform_service_id',
        'key',
        'name_ar',
        'name_en',
        'is_default',
        'is_active',
        'sort_order',
        'meta',
    ];

    protected $casts = [
        'platform_service_id' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'meta' => 'array',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(PlatformService::class, 'platform_service_id');
    }

    public function platformService(): BelongsTo
    {
        return $this->belongsTo(PlatformService::class, 'platform_service_id');
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
}