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
        'user_id'                   => 'integer',
        'fee_auto_charge_enabled'   => 'boolean',
        'rating_enabled'            => 'boolean',
        'stats_enabled'             => 'boolean',
        'enabled_at'                => 'datetime',
        'disabled_at'               => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeFeeEnabled(Builder $query): Builder
    {
        return $query->where('fee_auto_charge_enabled', true);
    }

    public function scopeRatingEnabled(Builder $query): Builder
    {
        return $query->where('rating_enabled', true);
    }

    public function scopeStatsEnabled(Builder $query): Builder
    {
        return $query->where('stats_enabled', true);
    }

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
}