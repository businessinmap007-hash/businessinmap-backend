<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisputeObligation extends Model
{
    public const TYPE_SESSION_FEE = 'session_fee';
    public const TYPE_PLATFORM_FINE = 'platform_fine';
    public const TYPE_COMPENSATION = 'compensation';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';

    public const FROM_WALLET = 'wallet';
    public const FROM_GUARANTEE = 'guarantee';

    protected $fillable = [
        'dispute_id',
        'user_id',
        'type',
        'amount',
        'payee_user_id',
        'status',
        'settled_from',
        'due_at',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(Dispute::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** Past the window the payer was given, so the guarantee may be opened. */
    public function isDue(): bool
    {
        return $this->isPending() && $this->due_at !== null && $this->due_at->isPast();
    }
}
