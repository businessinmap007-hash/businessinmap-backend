<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WalletHold extends Model
{
    protected $table = 'wallet_holds';

    public const STATUS_HELD     = 'held';
    public const STATUS_RELEASED = 'released';
    public const STATUS_VOID     = 'void';
    public const STATUS_DISPUTED = 'disputed';

    protected $fillable = [
        'wallet_id',
        'user_id',
        'amount',
        'status',
        'context',
        'reference_type',
        'reference_id',
        'meta',
    ];

    protected $casts = [
        'wallet_id' => 'integer',
        'user_id' => 'integer',
        'reference_id' => 'integer',
        'amount' => 'decimal:2',
        'meta' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeHeld(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_HELD);
    }

    public function scopeReleased(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RELEASED);
    }

    public function scopeDisputed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DISPUTED);
    }

    public function scopeForUser(Builder $query, ?int $userId): Builder
    {
        if (! $userId) {
            return $query;
        }

        return $query->where('user_id', (int) $userId);
    }

    public function scopeForWallet(Builder $query, ?int $walletId): Builder
    {
        if (! $walletId) {
            return $query;
        }

        return $query->where('wallet_id', (int) $walletId);
    }

    public function scopeForContext(Builder $query, ?string $context): Builder
    {
        $context = trim((string) $context);

        if ($context === '') {
            return $query;
        }

        return $query->where('context', $context);
    }

    public function scopeForReference(Builder $query, ?string $referenceType, $referenceId = null): Builder
    {
        $referenceType = trim((string) $referenceType);

        if ($referenceType === '') {
            return $query;
        }

        $query->where('reference_type', $referenceType);

        if ($referenceId !== null && $referenceId !== '') {
            $query->where('reference_id', (int) $referenceId);
        }

        return $query;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_HELD,
            self::STATUS_DISPUTED,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isHeld(): bool
    {
        return (string) $this->status === self::STATUS_HELD;
    }

    public function isReleased(): bool
    {
        return (string) $this->status === self::STATUS_RELEASED;
    }

    public function isDisputed(): bool
    {
        return (string) $this->status === self::STATUS_DISPUTED;
    }

    public function isVoid(): bool
    {
        return (string) $this->status === self::STATUS_VOID;
    }

    public function isOpen(): bool
    {
        return $this->isHeld() || $this->isDisputed();
    }

    public function amountFloat(): float
    {
        return round((float) $this->amount, 2);
    }

    public function mergeMeta(array $payload): void
    {
        $meta = is_array($this->meta) ? $this->meta : [];

        $this->meta = array_replace_recursive($meta, $payload);
    }
}