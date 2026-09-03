<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A participant's standing decision on whether admins may read their thread. */
class ThreadAccessConsent extends Model
{
    public const APPROVED = 'approved';
    public const DECLINED = 'declined';

    protected $fillable = [
        'thread_id',
        'user_id',
        'decision',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
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
