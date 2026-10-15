<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * The state of one health visit's «عربون الجدية» (see VisitDepositService). The wallet money itself is the ordinary
 * escrow row (`deposit_id`); an external deposit moves no money here, the business only records that it arrived.
 */
class VisitDeposit extends Model
{
    public const MODE_WALLET = 'wallet';
    public const MODE_EXTERNAL = 'external';

    public const STATUS_HELD = 'held';           // frozen in the wallet(s)
    public const STATUS_PENDING = 'pending';     // owed outside the platform, the business has not said it arrived
    public const STATUS_RECEIVED = 'received';   // the business confirmed receipt (cash / transfer)
    public const STATUS_WAIVED = 'waived';       // the business accepted the visit without it
    public const STATUS_RELEASED = 'released';   // the visit was done: both holds went back
    public const STATUS_REFUNDED = 'refunded';   // the visit did not happen / the business gave it back
    public const STATUS_VOID = 'void';           // an external deposit of a visit that did not happen
    public const STATUS_DISPUTED = 'disputed';   // under an open dispute
    public const STATUS_RESOLVED = 'resolved';   // a dispute ruled on it

    protected $fillable = [
        'subject_type', 'subject_id', 'client_id', 'business_id', 'mode', 'amount', 'counter_amount',
        'status', 'method', 'reference', 'deposit_id', 'dispute_id', 'meta',
    ];

    protected $casts = [
        'amount' => 'float',
        'counter_amount' => 'float',
        'meta' => 'array',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo('subject');
    }

    /** Frozen money still waiting for an outcome. */
    public function isHeld(): bool
    {
        return $this->mode === self::MODE_WALLET && $this->status === self::STATUS_HELD;
    }
}
