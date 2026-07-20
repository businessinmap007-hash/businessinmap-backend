<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThreadParticipant extends Model
{
    public const ROLE_CLIENT = 'client';
    public const ROLE_BUSINESS = 'business';
    public const ROLE_ARBITRATOR = 'arbitrator';
    public const ROLE_MEMBER = 'member';

    protected $fillable = [
        'thread_id',
        'user_id',
        'role',
        'joined_at',
        'conduct_accepted_at',
        'conduct_version',
        'conduct_declined_at',
        'last_read_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'conduct_accepted_at' => 'datetime',
        'conduct_version' => 'integer',
        'conduct_declined_at' => 'datetime',
        'last_read_at' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
