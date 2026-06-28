<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BookableAllocation extends Model
{
    public const TYPE_GUARANTEED = 'guaranteed';
    public const TYPE_NON_GUARANTEED = 'non_guaranteed';
    public const TYPE_REQUEST_ONLY = 'request_only';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_STOPPED = 'stopped';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'bookable_allocations';

    protected $fillable = [
        'partnership_id',
        'owner_business_id',
        'partner_business_id',
        'bookable_item_id',
        'platform_service_id',
        'allocation_type',
        'starts_at',
        'ends_at',
        'quantity_total',
        'quantity_sold',
        'quantity_reserved',
        'quantity_released',
        'release_days_before',
        'min_nights',
        'max_nights',
        'contract_price',
        'currency',
        'markup_type',
        'markup_value',
        'status',
        'meta',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'quantity_total' => 'integer',
        'quantity_sold' => 'integer',
        'quantity_reserved' => 'integer',
        'quantity_released' => 'integer',
        'release_days_before' => 'integer',
        'min_nights' => 'integer',
        'max_nights' => 'integer',
        'contract_price' => 'decimal:2',
        'markup_value' => 'decimal:2',
        'meta' => 'array',
    ];

    public static function allocationTypes(): array
    {
        return [
            self::TYPE_GUARANTEED => 'Guaranteed',
            self::TYPE_NON_GUARANTEED => 'Non Guaranteed',
            self::TYPE_REQUEST_ONLY => 'Request Only',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_PAUSED => 'Paused',
            self::STATUS_STOPPED => 'Stopped',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public function partnership()
    {
        return $this->belongsTo(BusinessPartnership::class, 'partnership_id');
    }

    public function ownerBusiness()
    {
        return $this->belongsTo(User::class, 'owner_business_id');
    }

    public function partnerBusiness()
    {
        return $this->belongsTo(User::class, 'partner_business_id');
    }

    public function bookableItem()
    {
        return $this->belongsTo(BookableItem::class, 'bookable_item_id');
    }

    public function platformService()
    {
        return $this->belongsTo(PlatformService::class, 'platform_service_id');
    }

    public function commercialOffers()
    {
        return $this->hasMany(CommercialOffer::class, 'source_id')
            ->where('source_type', CommercialOffer::SOURCE_ALLOCATION);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(function (Builder $q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    public function availableQuantity(): int
    {
        return max(
            (int) $this->quantity_total
            - (int) $this->quantity_sold
            - (int) $this->quantity_reserved
            - (int) $this->quantity_released,
            0
        );
    }

    public function isReleasedForDate($date): bool
    {
        if ((int) $this->release_days_before <= 0) {
            return false;
        }

        try {
            $releaseAt = \Illuminate\Support\Carbon::parse($date)->subDays((int) $this->release_days_before)->endOfDay();
        } catch (\Throwable $e) {
            return false;
        }

        return now()->greaterThan($releaseAt);
    }

    public function finalPrice(): float
    {
        $price = round((float) $this->contract_price, 2);
        $markupType = (string) ($this->markup_type ?? 'none');
        $markupValue = round((float) ($this->markup_value ?? 0), 2);

        if ($markupType === 'fixed') {
            return max(round($price + $markupValue, 2), 0);
        }

        if ($markupType === 'percent') {
            return max(round($price + ($price * ($markupValue / 100)), 2), 0);
        }

        return max($price, 0);
    }
}
