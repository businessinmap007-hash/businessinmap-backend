<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessTrustedClient extends Model
{
    protected $table = 'business_client_allowlist';

    public const TYPE_TRUSTED = 'trusted';
    public const TYPE_VIP = 'vip';
    public const TYPE_BLOCKED = 'blocked';

    protected $fillable = [
        'business_id',
        'client_id',
        'is_active',
        'list_type',
        'skip_deposit',
        'skip_guarantee',
        'max_active_bookings',
        'max_booking_value',
        'notes',
        'approved_by',
        'approved_at',
        'meta',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'client_id' => 'integer',
        'is_active' => 'boolean',
        'skip_deposit' => 'boolean',
        'skip_guarantee' => 'boolean',
        'max_active_bookings' => 'integer',
        'max_booking_value' => 'decimal:2',
        'approved_by' => 'integer',
        'approved_at' => 'datetime',
        'meta' => 'array',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isBlocked(): bool
    {
        return (string) $this->list_type === self::TYPE_BLOCKED;
    }

    public function isVip(): bool
    {
        return (string) $this->list_type === self::TYPE_VIP;
    }
}
