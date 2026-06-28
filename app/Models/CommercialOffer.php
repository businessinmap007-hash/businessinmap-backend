<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CommercialOffer extends Model
{
    public const OFFERABLE_BOOKABLE_ITEM = 'bookable_item';
    public const OFFERABLE_PRODUCT = 'product';
    public const OFFERABLE_SERVICE = 'service';
    public const OFFERABLE_PACKAGE = 'package';

    public const SOURCE_DIRECT = 'direct';
    public const SOURCE_ALLOCATION = 'allocation';
    public const SOURCE_RESELLER = 'reseller';
    public const SOURCE_PROMOTION = 'promotion';
    public const SOURCE_MARKETPLACE = 'marketplace';

    public const AVAILABILITY_INSTANT = 'instant';
    public const AVAILABILITY_REQUEST = 'request';
    public const AVAILABILITY_LIMITED = 'limited_quantity';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'commercial_offers';

    protected $fillable = [
        'offerable_type',
        'offerable_id',
        'owner_business_id',
        'seller_business_id',
        'source_type',
        'source_id',
        'title_ar',
        'title_en',
        'base_price',
        'final_price',
        'currency',
        'discount_type',
        'discount_value',
        'availability_mode',
        'available_quantity',
        'starts_at',
        'ends_at',
        'is_refundable',
        'payment_model',
        'cancellation_policy_id',
        'deposit_policy_id',
        'guarantee_policy_id',
        'ranking_score',
        'status',
        'meta',
    ];

    protected $casts = [
        'offerable_id' => 'integer',
        'owner_business_id' => 'integer',
        'seller_business_id' => 'integer',
        'source_id' => 'integer',
        'base_price' => 'decimal:2',
        'final_price' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'available_quantity' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_refundable' => 'boolean',
        'ranking_score' => 'decimal:4',
        'meta' => 'array',
    ];

    public function ownerBusiness()
    {
        return $this->belongsTo(User::class, 'owner_business_id');
    }

    public function sellerBusiness()
    {
        return $this->belongsTo(User::class, 'seller_business_id');
    }

    public function bookableItem()
    {
        return $this->belongsTo(BookableItem::class, 'offerable_id')
            ->where('offerable_type', self::OFFERABLE_BOOKABLE_ITEM);
    }

    public function allocation()
    {
        return $this->belongsTo(BookableAllocation::class, 'source_id')
            ->where('source_type', self::SOURCE_ALLOCATION);
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

    public function scopeForOfferable(Builder $query, string $type, int $id): Builder
    {
        return $query->where('offerable_type', $type)->where('offerable_id', $id);
    }

    public function isAvailable(int $quantity = 1): bool
    {
        if ((string) $this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        if ((string) $this->availability_mode === self::AVAILABILITY_LIMITED) {
            return (int) $this->available_quantity >= max($quantity, 1);
        }

        return true;
    }

    public function displayTitle(): string
    {
        return $this->title_ar ?: ($this->title_en ?: ('Offer #' . $this->id));
    }
}
