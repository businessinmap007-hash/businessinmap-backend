<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Dispute extends Model
{
    protected $table = 'disputes';

    protected $fillable = [
        'platform_service_id',
        'disputeable_type',
        'disputeable_id',
        'opened_by_user_id',
        'against_user_id',
        'status',
        'reason_code',
        'reason_text',
        'resolution_type',
        'resolution_payload',
        'opened_at',
        'resolved_at',
        'closed_at',
        'deposit_id','type',
        'mutual_resolution_started_at',
        'mutual_resolution_deadline_at',
        'warning_every_days','last_warning_sent_at',
        'next_warning_at','warning_count','client_cooperated_at',
        'business_cooperated_at',
        'client_settlement_agreed_at','business_settlement_agreed_at',
        'client_non_cooperation_flag',
        'business_non_cooperation_flag','client_amount',
        'business_amount','platform_fee_amount','non_cooperation_fee_amount',
        'resolved_by','meta',
    ];

    protected $casts = [
        'resolution_payload' => 'array',
        'opened_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'mutual_resolution_started_at' => 'datetime',
        'mutual_resolution_deadline_at' => 'datetime',
        'last_warning_sent_at' => 'datetime',
        'next_warning_at' => 'datetime',
        'warning_count' => 'integer',
        'client_cooperated_at' => 'datetime',
        'client_settlement_agreed_at' => 'datetime',
        'business_settlement_agreed_at' => 'datetime',
        'business_cooperated_at' => 'datetime',
        'client_non_cooperation_flag' => 'boolean',
        'business_non_cooperation_flag' => 'boolean',
        'client_amount' => 'decimal:2',
        'business_amount' => 'decimal:2',
        'platform_fee_amount' => 'decimal:2',
        'non_cooperation_fee_amount' => 'decimal:2',
        'resolved_by' => 'integer',
        'meta' => 'array',
    ];

    public const STATUS_OPEN = 'open';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_MUTUAL_RESOLUTION = 'mutual_resolution';
    public const STATUS_EXPIRED = 'expired';

    public static function statusOptions(): array
    {
        return [
            self::STATUS_OPEN => 'Open',
            self::STATUS_UNDER_REVIEW => 'Under Review',
            self::STATUS_RESOLVED => 'Resolved',
            self::STATUS_CLOSED => 'Closed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_MUTUAL_RESOLUTION => 'Mutual Resolution',
            self::STATUS_EXPIRED => 'Expired',
        ];
    }

    public function disputeable(): MorphTo
    {
        return $this->morphTo();
    }

    public function platformService(): BelongsTo
    {
        return $this->belongsTo(PlatformService::class, 'platform_service_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    public function againstUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'against_user_id');
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if (! $status) {
            return $query;
        }

        return $query->where('status', $status);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
        self::STATUS_OPEN,
        self::STATUS_MUTUAL_RESOLUTION,
        self::STATUS_UNDER_REVIEW,
        ]);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isUnderReview(): bool
    {
        return $this->status === self::STATUS_UNDER_REVIEW;
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isMutualResolution(): bool
    {
        return $this->status === self::STATUS_MUTUAL_RESOLUTION;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_OPEN,
            self::STATUS_MUTUAL_RESOLUTION,
            self::STATUS_UNDER_REVIEW,
        ], true);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusOptions()[$this->status] ?? (string) $this->status;
    }
    public function deposit(): BelongsTo
{
    return $this->belongsTo(Deposit::class, 'deposit_id');
}

public function warnings(): HasMany
{
    return $this->hasMany(DisputeWarning::class, 'dispute_id')->orderByDesc('id');
}
}