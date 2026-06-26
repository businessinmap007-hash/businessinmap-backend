<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserGuarantee extends Model
{
    protected $table = 'user_guarantees';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PENDING_OPERATIONS = 'pending_operations';
    public const STATUS_UNDERFUNDED = 'underfunded';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_DOWNGRADED = 'downgraded';

    protected $fillable = [
        'user_id',
        'target_type',
        'purchased_level_id',
        'effective_level_id',
        'status',
        'locked_amount',
        'pending_coverage_amount',
        'active_coverage_amount',
        'current_coverage_amount',
        'used_coverage_amount',
        'completed_operations_count',
        'cancelled_operations_count',
        'late_cancellations_count',
        'disputes_opened_count',
        'disputes_lost_count',
        'trust_score',
        'grace_until',
        'activated_at',
        'upgraded_at',
        'downgraded_at',
        'cancelled_at',
        'meta',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'purchased_level_id' => 'integer',
        'effective_level_id' => 'integer',
        'locked_amount' => 'decimal:2',
        'pending_coverage_amount' => 'decimal:2',
        'active_coverage_amount' => 'decimal:2',
        'current_coverage_amount' => 'decimal:2',
        'used_coverage_amount' => 'decimal:2',
        'completed_operations_count' => 'integer',
        'cancelled_operations_count' => 'integer',
        'late_cancellations_count' => 'integer',
        'disputes_opened_count' => 'integer',
        'disputes_lost_count' => 'integer',
        'trust_score' => 'decimal:2',
        'grace_until' => 'datetime',
        'activated_at' => 'datetime',
        'upgraded_at' => 'datetime',
        'downgraded_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchasedLevel(): BelongsTo
    {
        return $this->belongsTo(GuaranteeLevel::class, 'purchased_level_id');
    }

    public function effectiveLevel(): BelongsTo
    {
        return $this->belongsTo(GuaranteeLevel::class, 'effective_level_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(GuaranteeTransaction::class, 'user_guarantee_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_ACTIVE,
            self::STATUS_PENDING_OPERATIONS,
            self::STATUS_UNDERFUNDED,
        ]);
    }

    public function availableCoverage(): float
    {
        return max(
            round((float) $this->current_coverage_amount - (float) $this->used_coverage_amount, 2),
            0
        );
    }

    public function covers(float $amount): bool
    {
        return $this->availableCoverage() >= round($amount, 2);
    }

    public function isUsable(): bool
    {
        return in_array($this->status, [
            self::STATUS_ACTIVE,
            self::STATUS_PENDING_OPERATIONS,
            self::STATUS_UNDERFUNDED,
        ], true);
    }
}