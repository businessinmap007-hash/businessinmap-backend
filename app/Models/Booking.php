<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Booking extends Model
{
    use SoftDeletes;

    protected $table = 'bookings';

    protected $fillable = [
        'user_id',
        'business_id',
        'service_id',
        'date',
        'time',
        'price',
        'status',
        'notes',
        'starts_at',
        'ends_at',
        'duration_value',
        'duration_unit',
        'all_day',
        'timezone',
        'quantity',
        'party_size',
        'bookable_type',
        'bookable_id',
        'meta',
    ];

    protected $casts = [
        'date' => 'date',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'all_day' => 'boolean',
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'party_size' => 'integer',
        'duration_value' => 'integer',
        'meta' => 'array',
        'deleted_at' => 'datetime',
    ];

    public const STATUS_PENDING     = 'pending';
    public const STATUS_ACCEPTED    = 'accepted';
    public const STATUS_REJECTED    = 'rejected';
    public const STATUS_CANCELLED   = 'cancelled';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING     => 'Pending',
            self::STATUS_ACCEPTED    => 'Accepted',
            self::STATUS_REJECTED    => 'Rejected',
            self::STATUS_CANCELLED   => 'Cancelled',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED   => 'Completed',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(PlatformService::class, 'service_id');
    }

    public function bookable(): MorphTo
    {
        return $this->morphTo();
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'reference_id', 'id')
            ->where('reference_type', 'booking');
    }

    public function deposits(): MorphMany
    {
        return $this->morphMany(Deposit::class, 'target');
    }

    public function latestDeposit(): MorphOne
    {
        return $this->morphOne(Deposit::class, 'target')->latestOfMany();
    }

    public function disputes(): MorphMany
    {
        return $this->morphMany(Dispute::class, 'disputeable');
    }

    public function latestDispute(): MorphOne
    {
        return $this->morphOne(Dispute::class, 'disputeable')->latestOfMany();
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if (! $status) {
            return $query;
        }

        return $query->where('status', $status);
    }

    public function scopeForBusiness(Builder $query, ?int $businessId): Builder
    {
        if (! $businessId) {
            return $query;
        }

        return $query->where('business_id', $businessId);
    }

    public function scopeForClient(Builder $query, ?int $userId): Builder
    {
        if (! $userId) {
            return $query;
        }

        return $query->where('user_id', $userId);
    }

    public function scopeForService(Builder $query, ?int $serviceId): Builder
    {
        if (! $serviceId) {
            return $query;
        }

        return $query->where('service_id', $serviceId);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusOptions()[$this->status] ?? (string) $this->status;
    }
}