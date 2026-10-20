<?php

namespace App\Models;

use App\Models\Concerns\HasOwnedImages;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One item an ordinary account puts up for sale, rent or swap, with no business behind it. See the
 * create_owner_listings migration and {@see \App\Services\OwnerListings\OwnerListingService}.
 */
class OwnerListing extends Model
{
    use HasOwnedImages;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_DONE = 'done';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_FLAGGED = 'flagged';

    /** Who may see it: everybody, the people of its governorate only, or the people of its city only. */
    public const VISIBILITY_ALL = 'all';
    public const VISIBILITY_GOVERNORATE = 'governorate';
    public const VISIBILITY_CITY = 'city';
    public const VISIBILITIES = [self::VISIBILITY_ALL, self::VISIBILITY_GOVERNORATE, self::VISIBILITY_CITY];

    public const DEAL_SALE = 'sale';
    public const DEAL_RENT = 'rent';
    public const DEAL_SWAP = 'swap';

    protected $fillable = [
        'user_id', 'category', 'deal', 'title', 'description', 'price', 'price_unit', 'currency', 'governorate_id', 'city_id', 'visibility',
        'attributes', 'status', 'done_reason', 'done_at', 'expires_at', 'expiry_alerted_for', 'purge_at', 'reports_count', 'deposit_amount', 'deposit_kind', 'deposit_state',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'attributes' => 'array',
        'done_at' => 'datetime',
        'expires_at' => 'datetime',
        'expiry_alerted_for' => 'datetime',
        'purge_at' => 'datetime',
        'reports_count' => 'integer',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(OwnerListingInquiry::class);
    }

    /** What a stranger may see: active, not past its day, and with at least one photo. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereHas('images');
    }

    /**
     * What this viewer's own registered place lets him see: unrestricted listings, plus a governorate-only one of HIS governorate
     * and a city-only one of HIS city. A viewer with no registered place sees only the unrestricted ones.
     */
    public function scopeReachableBy(Builder $query, ?User $viewer): Builder
    {
        $gov = $viewer?->governorate_id ? (int) $viewer->governorate_id : null;
        $city = $viewer?->city_id ? (int) $viewer->city_id : null;

        return $query->where(function (Builder $q) use ($gov, $city) {
            $q->where('visibility', self::VISIBILITY_ALL)->orWhereNull('visibility');

            if ($gov !== null) {
                $q->orWhere(fn (Builder $w) => $w->where('visibility', self::VISIBILITY_GOVERNORATE)->where('governorate_id', $gov));
            }

            if ($city !== null) {
                $q->orWhere(fn (Builder $w) => $w->where('visibility', self::VISIBILITY_CITY)->where('city_id', $city));
            }
        });
    }

    public function isReachableBy(?User $viewer): bool
    {
        return match ($this->visibility ?: self::VISIBILITY_ALL) {
            self::VISIBILITY_GOVERNORATE => $viewer?->governorate_id && (int) $viewer->governorate_id === (int) $this->governorate_id,
            self::VISIBILITY_CITY => $viewer?->city_id && $this->city_id && (int) $viewer->city_id === (int) $this->city_id,
            default => true,
        };
    }

    /** Still the owner's live listing — the one that fills his place in the category. */
    public function scopeHeld(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_FLAGGED]);
    }

    public function isVisible(): bool
    {
        return $this->status === self::STATUS_ACTIVE && ($this->expires_at === null || $this->expires_at->isFuture()) && $this->images()->exists();
    }
}
