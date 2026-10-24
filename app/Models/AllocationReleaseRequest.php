<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A guest asked a partner to give back a room the hotel promised it. See {@see \App\Services\Partnerships\AllocationReleases}. */
class AllocationReleaseRequest extends Model
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const DECLINED = 'declined';
    public const EXPIRED = 'expired';

    protected $fillable = ['bookable_item_id', 'requester_id', 'seller_business_id', 'starts_at', 'ends_at', 'quantity', 'status', 'allocation_id', 'decided_by', 'decided_at', 'expires_at'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'decided_at' => 'datetime', 'expires_at' => 'datetime'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(BookableItem::class, 'bookable_item_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /** Still waiting for an answer (an unanswered one stops counting when its time is up). */
    public function isOpen(): bool
    {
        return $this->status === self::PENDING && $this->expires_at->isFuture();
    }
}
