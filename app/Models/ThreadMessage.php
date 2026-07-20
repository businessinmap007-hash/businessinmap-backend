<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThreadMessage extends Model
{
    public const KIND_MESSAGE = 'message';
    public const KIND_SYSTEM = 'system';

    protected $fillable = [
        'thread_id',
        'sender_id',
        'kind',
        'body',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function isSystem(): bool
    {
        return $this->kind === self::KIND_SYSTEM;
    }
}
