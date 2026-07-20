<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConductViolation extends Model
{
    protected $fillable = [
        'thread_id',
        'thread_message_id',
        'against_user_id',
        'recorded_by_user_id',
        'reason',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    /** The message being pointed at, if the conduct was something typed. */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ThreadMessage::class, 'thread_message_id');
    }

    public function against(): BelongsTo
    {
        return $this->belongsTo(User::class, 'against_user_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
