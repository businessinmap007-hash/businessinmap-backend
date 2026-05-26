<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserServiceFeeConsent extends Model
{
    protected $table = 'user_service_fee_consents';

    protected $fillable = [
        'user_id',
        'fee_auto_charge_enabled',
        'rating_enabled',
        'stats_enabled',
        'enabled_at',
        'disabled_at',
        'notes',
    ];

    protected $casts = [
        'user_id'                 => 'integer',
        'fee_auto_charge_enabled' => 'boolean',
        'rating_enabled'          => 'boolean',
        'stats_enabled'           => 'boolean',
        'enabled_at'              => 'datetime',
        'disabled_at'             => 'datetime',
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

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeFeeEnabled(Builder $query): Builder
    {
        return $query->where('fee_auto_charge_enabled', true);
    }

    public function scopeFeeDisabled(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('fee_auto_charge_enabled', false)
                ->orWhereNull('fee_auto_charge_enabled');
        });
    }

    public function scopeRatingEnabled(Builder $query): Builder
    {
        return $query->where('rating_enabled', true);
    }

    public function scopeStatsEnabled(Builder $query): Builder
    {
        return $query->where('stats_enabled', true);
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
    | State Helpers
    |--------------------------------------------------------------------------
    */

    public function isActiveForCharging(): bool
    {
        return (bool) $this->fee_auto_charge_enabled;
    }

    public function isActiveForRating(): bool
    {
        return (bool) $this->rating_enabled;
    }

    public function isActiveForStats(): bool
    {
        return (bool) $this->stats_enabled;
    }

    public function isFullyDisabled(): bool
    {
        return ! $this->isActiveForCharging()
            && ! $this->isActiveForRating()
            && ! $this->isActiveForStats();
    }

    /*
    |--------------------------------------------------------------------------
    | Mutating Helpers
    |--------------------------------------------------------------------------
    */

    public function enableCharging(?string $notes = null): void
    {
        $this->forceFill([
            'fee_auto_charge_enabled' => true,
            'enabled_at' => $this->enabled_at ?: now(),
            'disabled_at' => null,
            'notes' => $notes !== null ? $notes : $this->notes,
        ])->save();
    }

    public function disableCharging(?string $notes = null): void
    {
        $this->forceFill([
            'fee_auto_charge_enabled' => false,
            'disabled_at' => now(),
            'notes' => $notes !== null ? $notes : $this->notes,
        ])->save();
    }

    public function enableRating(): void
    {
        $this->forceFill([
            'rating_enabled' => true,
        ])->save();
    }

    public function disableRating(): void
    {
        $this->forceFill([
            'rating_enabled' => false,
        ])->save();
    }

    public function enableStats(): void
    {
        $this->forceFill([
            'stats_enabled' => true,
        ])->save();
    }

    public function disableStats(): void
    {
        $this->forceFill([
            'stats_enabled' => false,
        ])->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Snapshot
    |--------------------------------------------------------------------------
    */

    public function toConsentSnapshot(): array
    {
        return [
            'id' => (int) $this->id,
            'user_id' => (int) $this->user_id,

            'fee_auto_charge_enabled' => (bool) $this->fee_auto_charge_enabled,
            'rating_enabled' => (bool) $this->rating_enabled,
            'stats_enabled' => (bool) $this->stats_enabled,

            'enabled_at' => optional($this->enabled_at)->toDateTimeString(),
            'disabled_at' => optional($this->disabled_at)->toDateTimeString(),

            'notes' => $this->notes,
        ];
    }
}