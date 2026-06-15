<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisputeWarning extends Model
{
    protected $table = 'dispute_warnings';

    protected $fillable = [
        'dispute_id',
        'booking_id',
        'deposit_id',
        'sent_to_user_id',
        'warning_no',
        'channel',
        'message',
        'sent_at',
    ];

    protected $casts = [
        'dispute_id' => 'integer',
        'booking_id' => 'integer',
        'deposit_id' => 'integer',
        'sent_to_user_id' => 'integer',
        'warning_no' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(Dispute::class, 'dispute_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function deposit(): BelongsTo
    {
        return $this->belongsTo(Deposit::class, 'deposit_id');
    }

    public function sentTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_to_user_id');
    }
}
