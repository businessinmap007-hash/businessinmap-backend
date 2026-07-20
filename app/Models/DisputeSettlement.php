<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisputeSettlement extends Model
{
    public const STATUS_PROPOSED = 'proposed';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_WITHDRAWN = 'withdrawn';

    /** Statuses that still occupy the dispute — only one of these at a time. */
    public const LIVE_STATUSES = [
        self::STATUS_PROPOSED,
        self::STATUS_ACCEPTED,
    ];

    protected $fillable = [
        'dispute_id',
        'proposed_by_user_id',
        'proposed_by_role',
        'payer_side',
        'amount',
        'method',
        'note',
        'status',
        'accepted_by_user_id',
        'accepted_at',
        'received_by_user_id',
        'received_at',
        'closed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'accepted_at' => 'datetime',
        'received_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(Dispute::class);
    }

    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by_user_id');
    }

    /** The side that receives the money — never the payer. */
    public function payeeSide(): string
    {
        return $this->payer_side === 'client' ? 'business' : 'client';
    }

    public function isLive(): bool
    {
        return in_array($this->status, self::LIVE_STATUSES, true);
    }
}
