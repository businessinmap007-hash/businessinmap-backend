<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Booking extends Model
{
    use SoftDeletes;

    protected $table = 'bookings';

    public const STATUS_PENDING     = 'pending';
    public const STATUS_ACCEPTED    = 'accepted';
    public const STATUS_REJECTED    = 'rejected';
    public const STATUS_CANCELLED   = 'cancelled';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';

    public const DEFAULT_CURRENCY = 'EGP';

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
        'duration_unit' => 'string',
        'meta' => 'array',
        'deleted_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */

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

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }
    public function isFinalStatus(): bool
    {
        return in_array((string) $this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_REJECTED,
        ], true);
    }

    public function canMoveToInProgress(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_ACCEPTED,
        ], true);
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

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

    public function platformService(): BelongsTo
    {
        return $this->service();
    }

    public function bookable(): MorphTo
    {
        return $this->morphTo();
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'reference_id', 'id')
            ->where('reference_type', 'booking')
            ->orderByDesc('id');
    }

    public function deposits(): MorphMany
    {
        return $this->morphMany(Deposit::class, 'target')
            ->orderByDesc('id');
    }

    public function latestDeposit(): MorphOne
    {
        return $this->morphOne(Deposit::class, 'target')->latestOfMany();
    }

    public function disputes(): MorphMany
    {
        return $this->morphMany(Dispute::class, 'disputeable')
            ->orderByDesc('id');
    }

    public function latestDispute(): MorphOne
    {
        return $this->morphOne(Dispute::class, 'disputeable')->latestOfMany();
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

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

    public function scopeBetweenStartsAt(Builder $query, $from = null, $to = null): Builder
    {
        return $query
            ->when($from, fn ($q) => $q->where('starts_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('starts_at', '<=', $to));
    }

    /*
    |--------------------------------------------------------------------------
    | Meta Helpers
    |--------------------------------------------------------------------------
    */

    public function metaArray(): array
    {
        return is_array($this->meta ?? null) ? $this->meta : [];
    }

    public function pricingMeta(): array
    {
        $pricing = data_get($this->metaArray(), 'pricing');

        return is_array($pricing) ? $pricing : [];
    }

    public function depositPolicyMeta(): array
    {
        $deposit = data_get($this->metaArray(), 'deposit_policy');

        return is_array($deposit) ? $deposit : [];
    }

    public function executionFeeMeta(): array
    {
        $fee = data_get($this->metaArray(), '_execution_fee');

        return is_array($fee) ? $fee : [];
    }

    public function serviceFeesSnapshot(): array
    {
        $snapshot = data_get($this->metaArray(), 'service_fees_snapshot');

        return is_array($snapshot) ? $snapshot : [];
    }

    public function platformServiceMeta(): array
    {
        $service = data_get($this->metaArray(), 'platform_service');

        return is_array($service) ? $service : [];
    }

    public function businessContextMeta(): array
    {
        $context = data_get($this->metaArray(), 'business_context');

        return is_array($context) ? $context : [];
    }

    public function bookableMeta(): array
    {
        $bookable = data_get($this->metaArray(), 'bookable_item');

        return is_array($bookable) ? $bookable : [];
    }

    /*
    |--------------------------------------------------------------------------
    | Pricing Helpers
    |--------------------------------------------------------------------------
    */

    public function currencyCode(): string
    {
        $currency = strtoupper(trim((string) data_get($this->pricingMeta(), 'currency', self::DEFAULT_CURRENCY)));

        return $currency !== '' ? $currency : self::DEFAULT_CURRENCY;
    }

    public function originalPriceAmount(): float
    {
        return round((float) data_get($this->pricingMeta(), 'original_price', $this->price ?? 0), 2);
    }

    public function finalPriceAmount(): float
    {
        return round((float) data_get($this->pricingMeta(), 'final_price', $this->price ?? 0), 2);
    }

    public function discountAmount(): float
    {
        return round((float) data_get($this->pricingMeta(), 'discount_amount', 0), 2);
    }

    public function discountPercent(): int
    {
        return (int) data_get($this->pricingMeta(), 'discount_percent', 0);
    }

    public function depositAmount(): float
    {
        $policy = $this->depositPolicyMeta();

        return round((float) data_get($policy, 'amount', data_get($policy, 'hold', 0)), 2);
    }

    public function remainingAfterDeposit(): float
    {
        return max(round($this->finalPriceAmount() - $this->depositAmount(), 2), 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Execution Fee Helpers
    |--------------------------------------------------------------------------
    */

    public function executionFeeChargedAt(): ?string
    {
        $chargedAt = data_get($this->executionFeeMeta(), 'charged_at');

        return $chargedAt ? (string) $chargedAt : null;
    }

    public function hasExecutionFeeCharged(): bool
    {
        return ! empty($this->executionFeeChargedAt());
    }

    public function executionClientAmount(): float
    {
        return round((float) data_get($this->executionFeeMeta(), 'client_amount', 0), 2);
    }

    public function executionBusinessAmount(): float
    {
        return round((float) data_get($this->executionFeeMeta(), 'business_amount', 0), 2);
    }

    public function executionTransactionsSnapshot(): array
    {
        $transactions = data_get($this->executionFeeMeta(), 'transactions', []);

        return is_array($transactions) ? $transactions : [];
    }

    public function clientFeeSnapshot(): ?array
    {
        $snapshot = data_get($this->serviceFeesSnapshot(), 'client');

        if (! is_array($snapshot)) {
            $snapshot = data_get($this->executionFeeMeta(), 'snapshot.client');
        }

        return is_array($snapshot) ? $snapshot : null;
    }

    public function businessFeeSnapshot(): ?array
    {
        $snapshot = data_get($this->serviceFeesSnapshot(), 'business');

        if (! is_array($snapshot)) {
            $snapshot = data_get($this->executionFeeMeta(), 'snapshot.business');
        }

        return is_array($snapshot) ? $snapshot : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Display Helpers
    |--------------------------------------------------------------------------
    */

    public function getStatusLabelAttribute(): string
    {
        return self::statusOptions()[$this->status] ?? (string) $this->status;
    }

    public function getDisplayNameAttribute(): string
    {
        return 'Booking #' . $this->id;
    }
}