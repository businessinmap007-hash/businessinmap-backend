<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformServiceFeePromotion extends Model
{
    protected $table = 'platform_service_fee_promotions';

    protected $fillable = [
        'scope_type',
        'service_id',
        'child_id',
        'name',
        'description',
        'target_party',
        'discount_type',
        'discount_value',
        'starts_at',
        'ends_at',
        'is_active',
        'priority',
        'notes',
    ];

    protected $casts = [
        'service_id'      => 'integer',
        'child_id'        => 'integer',
        'discount_value'  => 'decimal:2',
        'starts_at'       => 'datetime',
        'ends_at'         => 'datetime',
        'is_active'       => 'boolean',
        'priority'        => 'integer',
    ];

    public const SCOPE_ALL_SERVICES = 'all_services';
    public const SCOPE_SERVICE = 'service';
    public const SCOPE_SERVICE_CHILD = 'service_child';

    public const TARGET_CLIENT = 'client';
    public const TARGET_BUSINESS = 'business';
    public const TARGET_BOTH = 'both';

    public const DISCOUNT_WAIVE = 'waive';
    public const DISCOUNT_FIXED_DISCOUNT = 'fixed_discount';
    public const DISCOUNT_PERCENT_DISCOUNT = 'percent_discount';
    public const DISCOUNT_OVERRIDE_TO_FIXED = 'override_to_fixed';

    public function service(): BelongsTo
    {
        return $this->belongsTo(PlatformService::class, 'service_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(CategoryChild::class, 'child_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', 1);
    }

    public function scopeCurrentlyRunning(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            });
    }

    public function scopeForServiceAndChild(Builder $query, ?int $serviceId, ?int $childId): Builder
    {
        return $query->where(function ($q) use ($serviceId, $childId) {
            $q->where('scope_type', self::SCOPE_ALL_SERVICES);

            if ($serviceId) {
                $q->orWhere(function ($qq) use ($serviceId) {
                    $qq->where('scope_type', self::SCOPE_SERVICE)
                        ->where('service_id', $serviceId);
                });
            }

            if ($serviceId && $childId) {
                $q->orWhere(function ($qq) use ($serviceId, $childId) {
                    $qq->where('scope_type', self::SCOPE_SERVICE_CHILD)
                        ->where('service_id', $serviceId)
                        ->where('child_id', $childId);
                });
            }
        });
    }

    public function scopeOrderedForApply(Builder $query): Builder
    {
        return $query
            ->orderByRaw("
                CASE scope_type
                    WHEN 'service_child' THEN 1
                    WHEN 'service' THEN 2
                    WHEN 'all_services' THEN 3
                    ELSE 4
                END
            ")
            ->orderBy('priority', 'asc')
            ->orderBy('id', 'desc');
    }

    public function isForClient(): bool
    {
        return in_array($this->target_party, [
            self::TARGET_CLIENT,
            self::TARGET_BOTH,
        ], true);
    }

    public function isForBusiness(): bool
    {
        return in_array($this->target_party, [
            self::TARGET_BUSINESS,
            self::TARGET_BOTH,
        ], true);
    }
}