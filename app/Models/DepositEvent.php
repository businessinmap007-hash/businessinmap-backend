<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepositEvent extends Model
{
    protected $table = 'deposit_events';

    protected $fillable = [
        'deposit_id',
        'booking_id',
        'dispute_id',
        'actor_id',
        'actor_type',
        'event_type',
        'amount',
        'notes',
        'meta',
    ];

    protected $casts = [
        'deposit_id' => 'integer',
        'booking_id' => 'integer',
        'dispute_id' => 'integer',
        'actor_id' => 'integer',
        'amount' => 'decimal:2',
        'meta' => 'array',
    ];

    public function deposit(): BelongsTo
    {
        return $this->belongsTo(Deposit::class, 'deposit_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(Dispute::class, 'dispute_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
