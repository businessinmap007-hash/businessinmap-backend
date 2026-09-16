<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single-use attendance-check-in code shown on a physical display at the
 * business's premises. Redeeming it (StaffAttendanceQrService::redeem) marks
 * it used immediately, so a photo of the screen is worthless the instant a
 * real scan happens; expires_at bounds exposure even if nobody scans it.
 */
class StaffAttendanceQrToken extends Model
{
    protected $fillable = [
        'business_id',
        'token',
        'expires_at',
        'used_at',
        'used_by_user_id',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'used_by_user_id' => 'integer',
    ];

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return ! $this->isUsed() && ! $this->isExpired();
    }
}
