<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A friend co-guaranteeing one operation with their platform-purchased
 * guarantee coverage. The coverage is frozen while active and released on
 * completion / dispute resolution — it is never charged.
 */
class OperationGuarantor extends Model
{
    public const STATUS_INVITED = 'invited';
    public const STATUS_ACCEPTED = 'accepted';   // coverage frozen for the operation
    public const STATUS_DECLINED = 'declined';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_RELEASED = 'released';    // coverage returned to the friend

    public const OP_BOOKING = 'booking';

    protected $fillable = [
        'operation_type',
        'operation_id',
        'requester_user_id',
        'guarantor_user_id',
        'user_guarantee_id',
        'covered_amount',
        'status',
        'invited_at',
        'responded_at',
        'released_at',
        'meta',
    ];

    protected $casts = [
        'operation_id' => 'integer',
        'requester_user_id' => 'integer',
        'guarantor_user_id' => 'integer',
        'user_guarantee_id' => 'integer',
        'covered_amount' => 'decimal:2',
        'invited_at' => 'datetime',
        'responded_at' => 'datetime',
        'released_at' => 'datetime',
        'meta' => 'array',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function guarantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guarantor_user_id');
    }

    public function userGuarantee(): BelongsTo
    {
        return $this->belongsTo(UserGuarantee::class, 'user_guarantee_id');
    }

    /** Accepted rows whose coverage is currently frozen for the operation. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    public function scopeForOperation(Builder $query, string $operationType, int $operationId): Builder
    {
        return $query->where('operation_type', $operationType)->where('operation_id', $operationId);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }
}
