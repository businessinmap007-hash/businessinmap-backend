<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $table = 'wallets';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_BLOCKED = 'blocked';

    protected $fillable = [
        'user_id',
        'balance',
        'locked_balance',
        'total_in',
        'total_out',
        'status',
        'last_activity_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'balance' => 'decimal:2',
        'locked_balance' => 'decimal:2',
        'total_in' => 'decimal:2',
        'total_out' => 'decimal:2',
        'last_activity_at' => 'datetime',
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

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'wallet_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeBlocked(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_BLOCKED);
    }

    public function scopeForUser(Builder $query, ?int $userId): Builder
    {
        if (! $userId) {
            return $query;
        }

        return $query->where('user_id', (int) $userId);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return (string) $this->status === self::STATUS_ACTIVE;
    }

    public function isBlocked(): bool
    {
        return (string) $this->status === self::STATUS_BLOCKED;
    }

    public function availableBalance(): float
    {
        return round((float) $this->balance, 2);
    }

    public function lockedBalance(): float
    {
        return round((float) $this->locked_balance, 2);
    }

    public function totalBalance(): float
    {
        return round($this->availableBalance() + $this->lockedBalance(), 2);
    }

    public function canWithdraw(float $amount): bool
    {
        $amount = round((float) $amount, 2);

        return $this->isActive()
            && $amount > 0
            && $this->availableBalance() >= $amount;
    }

    public function canRelease(float $amount): bool
    {
        $amount = round((float) $amount, 2);

        return $this->isActive()
            && $amount > 0
            && $this->lockedBalance() >= $amount;
    }
}