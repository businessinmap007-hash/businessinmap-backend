<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    /**
     * How long one booking of this kind is measured in.
     *
     * The kind says HOW a thing is booked, and the unit is part of that answer:
     * a hotel stay is counted in days, a hall in hours, a clinic examination in
     * minutes. It used to be decided by whichever app was calling — the API
     * validated `duration_unit` against an enum and nothing else, so «day» on a
     * كشف was accepted and three live bookings carry no unit at all.
     *
     * Written by BookingKindGranularitySeeder from
     * `database/seeders/data/booking_kind_granularity.php`. A kind with no
     * declaration returns null, and the caller keeps its old freedom.
     *
     * @return array{unit:string,slot_minutes:int,all_day:bool}|null
     */
    public function granularity(): ?array
    {
        $meta = is_array($this->meta) ? $this->meta : [];
        $unit = trim((string) ($meta['duration_unit'] ?? ''));

        if ($unit === '') {
            return null;
        }

        return [
            'unit' => $unit,
            'slot_minutes' => max((int) ($meta['slot_minutes'] ?? 0), 1),
            'all_day' => (bool) ($meta['all_day'] ?? false),
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(PlatformService::class, 'platform_service_id');
    }

    public function platformService(): BelongsTo
    {
        return $this->belongsTo(PlatformService::class, 'platform_service_id');
    }

    /**
     * Branches this item type belongs to. An item type can be in several
     * branches at once (e.g. "room" under "hotel" and "residential units").
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(
            PlatformServiceItemGroup::class,
            'platform_service_item_group_type',
            'item_type_id',
            'group_id'
        );
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