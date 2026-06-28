<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

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

    public const AUDIENCE_B2C = 'b2c';
    public const AUDIENCE_B2B = 'b2b';
    public const AUDIENCE_BOTH = 'both';
    public const AUDIENCE_PRIVATE = 'private';

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
        'audience_type',
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
        'is_featured',
        'featured_until',
        'boost_score',
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
        'is_featured' => 'boolean',
        'featured_until' => 'datetime',
        'boost_score' => 'decimal:4',
        'meta' => 'array',
    ];

    public static function audienceTypes(): array
    {
        return [
            self::AUDIENCE_B2C,
            self::AUDIENCE_B2B,
            self::AUDIENCE_BOTH,
            self::AUDIENCE_PRIVATE,
        ];
    }

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

    public function targets(): HasMany
    {
        return $this->hasMany(CommercialOfferTarget::class, 'offer_id');
    }

    public function boostPurchases(): HasMany
    {
        return $this->hasMany(OfferBoostPurchase::class, 'offer_id');
    }

    public function followNotifications(): HasMany
    {
        return $this->hasMany(OfferFollowNotification::class, 'offer_id');
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

    public function scopeVisibleForUserType(Builder $query, ?string $userType): Builder
    {
        $userType = (string) $userType;

        if ($userType === 'business') {
            return $query->whereIn('audience_type', [self::AUDIENCE_B2B, self::AUDIENCE_BOTH]);
        }

        if ($userType === 'client') {
            return $query->whereIn('audience_type', [self::AUDIENCE_B2C, self::AUDIENCE_BOTH]);
        }

        return $query->whereIn('audience_type', [self::AUDIENCE_B2C, self::AUDIENCE_BOTH]);
    }

    public function scopeForOfferable(Builder $query, string $type, int $id): Builder
    {
        return $query->where('offerable_type', $type)->where('offerable_id', $id);
    }

    public function scopeOrderByBoost(Builder $query): Builder
    {
        if (! Schema::hasColumn($this->getTable(), 'boost_score')) {
            return $query->orderByDesc('ranking_score');
        }

        if (Schema::hasColumn($this->getTable(), 'featured_until') && Schema::hasColumn($this->getTable(), 'is_featured')) {
            return $query
                ->orderByRaw('CASE WHEN is_featured = 1 AND (featured_until IS NULL OR featured_until >= NOW()) THEN 1 ELSE 0 END DESC')
                ->orderByDesc('boost_score')
                ->orderByDesc('ranking_score');
        }

        return $query->orderByDesc('boost_score')->orderByDesc('ranking_score');
    }

    public function isBoosted(): bool
    {
        if (! (bool) ($this->is_featured ?? false) && (float) ($this->boost_score ?? 0) <= 0) {
            return false;
        }

        if ($this->featured_until && $this->featured_until->isPast()) {
            return false;
        }

        return true;
    }

    public function effectiveBoostScore(): float
    {
        return $this->isBoosted() ? (float) ($this->boost_score ?? 0) : 0.0;
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
