<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    protected $table = 'wallet_transactions';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_REVERSED  = 'reversed';

    public const DIRECTION_IN  = 'in';
    public const DIRECTION_OUT = 'out';

    public const TYPE_DEPOSIT      = 'deposit';
    public const TYPE_WITHDRAW     = 'withdraw';
    public const TYPE_HOLD         = 'hold';
    public const TYPE_RELEASE      = 'release';
    public const TYPE_REFUND       = 'refund';
    public const TYPE_PLATFORM_FEE = 'platform_fee';

    public const REFERENCE_TYPE_BOOKING = 'booking';

    protected $fillable = [
        'wallet_id',
        'user_id',
        'status',
        'direction',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'locked_before',
        'locked_after',
        'reference_type',
        'reference_id',
        'idempotency_key',
        'note_id',
        'note',
        'meta',
    ];

    protected $casts = [
        'wallet_id' => 'integer',
        'user_id' => 'integer',
        'note_id' => 'integer',

        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'locked_before' => 'decimal:2',
        'locked_after' => 'decimal:2',

        'meta' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function noteTemplate(): BelongsTo
    {
        return $this->belongsTo(WalletNoteTemplate::class, 'note_id');
    }

    /**
     * علاقة اختيارية للحجز عندما تكون العملية مربوطة بـ booking
     * عبر reference_type / reference_id.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'reference_id', 'id')
            ->where('wallet_transactions.reference_type', self::REFERENCE_TYPE_BOOKING);
    }

    public function categoryChildServiceFee(): BelongsTo
    {
        return $this->belongsTo(CategoryChildServiceFee::class, 'category_child_service_fee_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeIn(Builder $query): Builder
    {
        return $query->where('direction', self::DIRECTION_IN);
    }

    public function scopeOut(Builder $query): Builder
    {
        return $query->where('direction', self::DIRECTION_OUT);
    }

    public function scopeOfType(Builder $query, ?string $type): Builder
    {
        if (! $type) {
            return $query;
        }

        return $query->where('type', $type);
    }

    public function scopeForUser(Builder $query, ?int $userId): Builder
    {
        if (! $userId) {
            return $query;
        }

        return $query->where('user_id', $userId);
    }

    public function scopeForWallet(Builder $query, ?int $walletId): Builder
    {
        if (! $walletId) {
            return $query;
        }

        return $query->where('wallet_id', $walletId);
    }

    public function scopeForReference(Builder $query, ?string $referenceType, $referenceId = null): Builder
    {
        if (! $referenceType) {
            return $query;
        }

        $query->where('reference_type', $referenceType);

        if ($referenceId !== null && $referenceId !== '') {
            $query->where('reference_id', (string) $referenceId);
        }

        return $query;
    }

    public function scopeForBooking(Builder $query, ?int $bookingId): Builder
    {
        if (! $bookingId) {
            return $query;
        }

        return $query->forReference(self::REFERENCE_TYPE_BOOKING, $bookingId);
    }

    public function scopePlatformFees(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_PLATFORM_FEE);
    }

    public function scopeForFeeCode(Builder $query, ?string $feeCode): Builder
    {
        $feeCode = trim((string) $feeCode);

        if ($feeCode === '') {
            return $query;
        }

        return $query->where('meta->fee_code', $feeCode);
    }

    public function scopeForPayer(Builder $query, ?string $payer): Builder
    {
        $payer = trim((string) $payer);

        if ($payer === '') {
            return $query;
        }

        return $query->where('meta->payer', $payer);
    }

    public function scopeBookingFees(Builder $query): Builder
    {
        return $query
            ->platformFees()
            ->forReference(self::REFERENCE_TYPE_BOOKING);
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

    public function metaValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->metaArray(), $key, $default);
    }

    public function payer(): ?string
    {
        $payer = $this->metaValue('payer');

        return $payer ? (string) $payer : null;
    }

    public function feeCode(): ?string
    {
        $code = $this->metaValue('fee_code');

        return $code ? (string) $code : null;
    }

    public function feeType(): ?string
    {
        $type = $this->metaValue('fee_type');

        return $type ? (string) $type : null;
    }

    public function bookingId(): ?int
    {
        $bookingId = $this->metaValue('booking_id');

        if ($bookingId) {
            return (int) $bookingId;
        }

        if ($this->reference_type === self::REFERENCE_TYPE_BOOKING && $this->reference_id) {
            return (int) $this->reference_id;
        }

        return null;
    }

    public function serviceId(): ?int
    {
        $id = $this->metaValue('service_id');

        return $id ? (int) $id : null;
    }

    public function businessId(): ?int
    {
        $id = $this->metaValue('business_id');

        return $id ? (int) $id : null;
    }

    public function clientId(): ?int
    {
        $id = $this->metaValue('client_id');

        return $id ? (int) $id : null;
    }

    public function childId(): ?int
    {
        $id = $this->metaValue('child_id');

        return $id ? (int) $id : null;
    }

    public function categoryChildServiceFeeId(): ?int
    {
        $id = $this->metaValue('category_child_service_fee_id');

        if (! $id) {
            $id = $this->metaValue('service_fee_id');
        }

        if (! $id) {
            $id = $this->metaValue('fee_row_id');
        }

        return $id ? (int) $id : null;
    }

    public function feeRowId(): ?int
    {
        $id = $this->metaValue('fee_row_id');

        if (! $id) {
            $id = $this->categoryChildServiceFeeId();
        }

        return $id ? (int) $id : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Boolean Helpers
    |--------------------------------------------------------------------------
    */

    public function isIncoming(): bool
    {
        return $this->direction === self::DIRECTION_IN;
    }

    public function isOutgoing(): bool
    {
        return $this->direction === self::DIRECTION_OUT;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isPlatformFee(): bool
    {
        return $this->type === self::TYPE_PLATFORM_FEE;
    }

    public function isBookingFee(): bool
    {
        return $this->isPlatformFee()
            && $this->reference_type === self::REFERENCE_TYPE_BOOKING;
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getIsIncomingAttribute(): bool
    {
        return $this->isIncoming();
    }

    public function getIsOutgoingAttribute(): bool
    {
        return $this->isOutgoing();
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING   => 'Pending',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED    => 'Failed',
            self::STATUS_REVERSED  => 'Reversed',
            default => (string) $this->status,
        };
    }

    public function getDirectionLabelAttribute(): string
    {
        return match ($this->direction) {
            self::DIRECTION_IN  => 'IN',
            self::DIRECTION_OUT => 'OUT',
            default => (string) $this->direction,
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_DEPOSIT      => 'Deposit',
            self::TYPE_WITHDRAW     => 'Withdraw',
            self::TYPE_HOLD         => 'Hold',
            self::TYPE_RELEASE      => 'Release',
            self::TYPE_REFUND       => 'Refund',
            self::TYPE_PLATFORM_FEE => 'Platform Fee',
            default => (string) $this->type,
        };
    }

    public function getPayerLabelAttribute(): string
    {
        return match ($this->payer()) {
            CategoryChildServiceFee::PAYER_CLIENT => 'Client',
            CategoryChildServiceFee::PAYER_BUSINESS => 'Business',
            default => (string) ($this->payer() ?: '—'),
        };
    }
}