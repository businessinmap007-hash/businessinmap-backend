<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuaranteeLevel extends Model
{
    protected $table = 'guarantee_levels';

    public const TARGET_CLIENT = 'client';
    public const TARGET_BUSINESS = 'business';

    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'target_type',
        'required_locked_amount',
        'pending_coverage_amount',
        'active_coverage_amount',
        'boost_coverage_amount',
        'boost_min_operations',
        'boost_min_success_rate',
        'boost_max_dispute_rate',
        'required_completed_operations',
        'required_trust_score',
        'max_lost_disputes',
        'max_late_cancellations',
        'priority',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'required_locked_amount' => 'decimal:2',
        'pending_coverage_amount' => 'decimal:2',
        'active_coverage_amount' => 'decimal:2',
        'boost_coverage_amount' => 'decimal:2',
        'boost_min_operations' => 'integer',
        'boost_min_success_rate' => 'decimal:2',
        'boost_max_dispute_rate' => 'decimal:2',
        'required_completed_operations' => 'integer',
        'required_trust_score' => 'decimal:2',
        'max_lost_disputes' => 'integer',
        'max_late_cancellations' => 'integer',
        'priority' => 'integer',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public function userGuarantees(): HasMany
    {
        return $this->hasMany(UserGuarantee::class, 'purchased_level_id');
    }

    public function effectiveUserGuarantees(): HasMany
    {
        return $this->hasMany(UserGuarantee::class, 'effective_level_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForTarget(Builder $query, string $targetType): Builder
    {
        return $query->where('target_type', $targetType);
    }

    public function getDisplayNameAttribute(): string
    {
        return (string) ($this->name_ar ?: $this->name_en ?: $this->code);
    }
}