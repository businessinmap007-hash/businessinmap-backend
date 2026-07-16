<?php

namespace App\Models;

use App\DTO\FeeContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BIM-3.5 — one dynamic fee rule. See the create_service_fee_rules migration
 * for the layering and why conditions are JSON.
 *
 * A rule knows two things: whether it applies to an operation (matches) and what
 * it does to the fee (applyTo). Selecting and ordering rules is the engine's job.
 */
class ServiceFeeRule extends Model
{
    protected $table = 'service_fee_rules';

    public const PAYER_BUSINESS = 'business';
    public const PAYER_CLIENT = 'client';
    public const PAYER_ANY = 'any';

    public const EFFECT_PERCENT_ADJUST = 'percent_adjust';
    public const EFFECT_FIXED_ADJUST = 'fixed_adjust';
    public const EFFECT_MULTIPLY = 'multiply';
    public const EFFECT_OVERRIDE_FIXED = 'override_fixed';
    public const EFFECT_OVERRIDE_PERCENT = 'override_percent';
    public const EFFECT_WAIVE = 'waive';

    public const EFFECTS = [
        self::EFFECT_PERCENT_ADJUST,
        self::EFFECT_FIXED_ADJUST,
        self::EFFECT_MULTIPLY,
        self::EFFECT_OVERRIDE_FIXED,
        self::EFFECT_OVERRIDE_PERCENT,
        self::EFFECT_WAIVE,
    ];

    protected $fillable = [
        'name',
        'platform_service_id',
        'category_id',
        'child_id',
        'payer',
        'fee_code',
        'priority',
        'stop_on_match',
        'conditions',
        'effect',
        'effect_value',
        'min_fee',
        'max_fee',
        'starts_at',
        'ends_at',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'platform_service_id' => 'integer',
        'category_id' => 'integer',
        'child_id' => 'integer',
        'priority' => 'integer',
        'stop_on_match' => 'boolean',
        'conditions' => 'array',
        'effect_value' => 'decimal:2',
        'min_fee' => 'decimal:2',
        'max_fee' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function platformService(): BelongsTo
    {
        return $this->belongsTo(PlatformService::class, 'platform_service_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Rules whose start/end window contains $at (null bounds are open). */
    public function scopeRunningAt(Builder $query, ?\DateTimeInterface $at = null): Builder
    {
        $at = $at ?? now();

        return $query
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $at));
    }

    /**
     * Rules whose scope columns either match the context or are null ("any").
     * This is only a coarse narrowing — matches() makes the real decision.
     */
    public function scopeForContext(Builder $query, FeeContext $context): Builder
    {
        return $query
            ->where(fn (Builder $q) => $q->whereNull('platform_service_id')->orWhere('platform_service_id', $context->serviceId))
            ->where(fn (Builder $q) => $q->whereNull('category_id')->orWhere('category_id', $context->categoryId))
            ->where(fn (Builder $q) => $q->whereNull('child_id')->orWhere('child_id', $context->childId))
            ->where(fn (Builder $q) => $q->whereNull('fee_code')->orWhere('fee_code', $context->feeCode))
            ->whereIn('payer', [self::PAYER_ANY, $context->payer]);
    }

    public function scopeOrderedForApply(Builder $query): Builder
    {
        return $query->orderBy('priority')->orderBy('id');
    }

    /**
     * Does every condition hold for this operation? An absent condition means
     * "don't care", so a rule with no conditions matches its whole scope.
     *
     * Supported keys (all optional):
     *   min_base_amount / max_base_amount   — by operation value
     *   governorate_ids / city_ids          — by geography
     *   days_of_week                        — [0..6], 0=Sunday
     *   time_from / time_to ("HH:MM")       — peak window; wraps past midnight
     *   min_success_operations / max_success_operations
     *   max_disputed_operations
     *   subscribed (bool)                   — business subscription state
     *   service_keys                        — by service kind
     */
    public function matches(FeeContext $context): bool
    {
        $conditions = is_array($this->conditions) ? $this->conditions : [];

        foreach ($conditions as $key => $value) {
            if (! $this->conditionHolds((string) $key, $value, $context)) {
                return false;
            }
        }

        return true;
    }

    private function conditionHolds(string $key, mixed $value, FeeContext $context): bool
    {
        return match ($key) {
            'min_base_amount' => $context->baseAmount >= (float) $value,
            'max_base_amount' => $context->baseAmount <= (float) $value,

            'governorate_ids' => $context->governorateId !== null
                && in_array((int) $context->governorateId, array_map('intval', (array) $value), true),
            'city_ids' => $context->cityId !== null
                && in_array((int) $context->cityId, array_map('intval', (array) $value), true),

            'days_of_week' => $context->dayOfWeek() !== null
                && in_array((int) $context->dayOfWeek(), array_map('intval', (array) $value), true),
            'time_from', 'time_to' => $this->timeWindowHolds($context),

            'min_success_operations' => $context->successOperations >= (int) $value,
            'max_success_operations' => $context->successOperations <= (int) $value,
            'max_disputed_operations' => $context->disputedOperations <= (int) $value,

            'subscribed' => $context->isSubscribed === (bool) $value,

            'service_keys' => $context->serviceKey !== null
                && in_array($context->serviceKey, array_map('strval', (array) $value), true),

            // An unknown key must never silently widen a rule.
            default => false,
        };
    }

    /**
     * Peak windows are given as time_from/time_to and evaluated together, so a
     * window like 22:00→02:00 that crosses midnight still reads as one span.
     */
    private function timeWindowHolds(FeeContext $context): bool
    {
        $now = $context->timeOfDay();

        if ($now === null) {
            return false;
        }

        $conditions = is_array($this->conditions) ? $this->conditions : [];
        $from = $conditions['time_from'] ?? null;
        $to = $conditions['time_to'] ?? null;

        if ($from === null) {
            return $now <= (string) $to;
        }

        if ($to === null) {
            return $now >= (string) $from;
        }

        $from = (string) $from;
        $to = (string) $to;

        return $from <= $to
            ? ($now >= $from && $now <= $to)
            : ($now >= $from || $now <= $to); // wraps midnight
    }

    /**
     * This rule's effect on the running fee, then its own min/max clamps.
     * Never returns a negative fee.
     */
    public function applyTo(float $amount, FeeContext $context): float
    {
        $value = (float) ($this->effect_value ?? 0);

        $result = match ((string) $this->effect) {
            self::EFFECT_PERCENT_ADJUST => $amount + ($amount * $value / 100),
            self::EFFECT_FIXED_ADJUST => $amount + $value,
            self::EFFECT_MULTIPLY => $amount * $value,
            self::EFFECT_OVERRIDE_FIXED => $value,
            self::EFFECT_OVERRIDE_PERCENT => $context->baseAmount * $value / 100,
            self::EFFECT_WAIVE => 0.0,
            default => $amount,
        };

        if ($this->min_fee !== null) {
            $result = max($result, (float) $this->min_fee);
        }

        if ($this->max_fee !== null) {
            $result = min($result, (float) $this->max_fee);
        }

        return round(max($result, 0.0), 2);
    }
}
