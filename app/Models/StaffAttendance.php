<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One staff member's check-in/check-out for one business on one day. A
 * person who works for several businesses (business_staff supports many
 * memberships) gets one independent row per business per day - attendance is
 * scoped to the shift, not the person.
 */
class StaffAttendance extends Model
{
    protected $fillable = [
        'business_id',
        'user_id',
        'date',
        'checked_in_at',
        'checked_out_at',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'user_id' => 'integer',
        'date' => 'date',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    /** Checked in today and hasn't checked out yet. */
    public function isPresent(): bool
    {
        return $this->checked_in_at !== null && $this->checked_out_at === null;
    }
}
