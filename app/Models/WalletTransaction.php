<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
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
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'locked_before' => 'decimal:2',
        'locked_after' => 'decimal:2',
        'meta' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

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
     * عبر reference_type / reference_id
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'reference_id', 'id')
            ->where('wallet_transactions.reference_type', 'booking');
    }

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
        if (!$type) {
            return $query;
        }

        return $query->where('type', $type);
    }

    public function scopeForUser(Builder $query, ?int $userId): Builder
    {
        if (!$userId) {
            return $query;
        }

        return $query->where('user_id', $userId);
    }

    public function scopeForWallet(Builder $query, ?int $walletId): Builder
    {
        if (!$walletId) {
            return $query;
        }

        return $query->where('wallet_id', $walletId);
    }

    public function scopeForReference(Builder $query, ?string $referenceType, $referenceId = null): Builder
    {
        if (!$referenceType) {
            return $query;
        }

        $query->where('reference_type', $referenceType);

        if ($referenceId !== null && $referenceId !== '') {
            $query->where('reference_id', (string) $referenceId);
        }

        return $query;
    }

    public function scopePlatformFees(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_PLATFORM_FEE);
    }

    public function getIsIncomingAttribute(): bool
    {
        return $this->direction === self::DIRECTION_IN;
    }

    public function getIsOutgoingAttribute(): bool
    {
        return $this->direction === self::DIRECTION_OUT;
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
}