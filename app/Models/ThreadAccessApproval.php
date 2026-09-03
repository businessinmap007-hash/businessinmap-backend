<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One admin's vote toward the quorum needed to view a thread without party consent. */
class ThreadAccessApproval extends Model
{
    protected $table = 'thread_access_admin_approvals';

    protected $fillable = [
        'thread_id',
        'admin_id',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
