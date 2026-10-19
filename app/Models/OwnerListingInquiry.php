<?php

namespace App\Models;

use App\Contracts\BlocksPhoneNumbers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A buyer's conversation about one owner listing — the subject of its chat thread, and the place where «اتفقنا» and the
 * swap of phone numbers (with the platform's fee) happen.
 */
class OwnerListingInquiry extends Model implements BlocksPhoneNumbers
{
    public const STATUS_OPEN = 'open';
    public const STATUS_REVEAL_REQUESTED = 'reveal_requested';
    public const STATUS_REVEALED = 'revealed';

    protected $fillable = [
        'owner_listing_id', 'buyer_id', 'seller_id', 'status', 'reveal_requested_by', 'reveal_requested_at', 'revealed_at', 'fee_total', 'fee_split',
    ];

    protected $casts = [
        'reveal_requested_at' => 'datetime',
        'revealed_at' => 'datetime',
        'fee_total' => 'decimal:2',
        'fee_split' => 'array',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(OwnerListing::class, 'owner_listing_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function blocksPhoneNumbers(): bool
    {
        return $this->status !== self::STATUS_REVEALED;
    }

    /** @return array{0:int,1:int} [buyerId, sellerId] */
    public function parties(): array
    {
        return [(int) $this->buyer_id, (int) $this->seller_id];
    }
}
