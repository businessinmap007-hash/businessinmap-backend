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

    public const DEAL_SALE = 'sale';
    public const DEAL_RENT = 'rent';
    public const DEAL_SWAP = 'swap';

    protected $fillable = [
        'user_id', 'category', 'deal', 'title', 'description', 'price', 'price_unit', 'currency', 'governorate_id', 'city_id',
        'attributes', 'status', 'done_reason', 'done_at', 'expires_at', 'expiry_alerted_for', 'purge_at', 'reports_count', 'deposit_amount', 'deposit_state',
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
