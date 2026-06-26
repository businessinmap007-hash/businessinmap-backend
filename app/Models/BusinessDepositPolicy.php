<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessDepositPolicy extends Model
{
    protected $table = 'business_deposit_policies';

    public const MODE_WALLET_HOLD = 'wallet_hold';
    public const MODE_EXTERNAL_VERIFICATION = 'external_verification';
    public const MODE_BOTH = 'both';

    public const BASE_FIRST_DAY = 'first_day';
    public const BASE_TOTAL = 'total';
    public const BASE_FIRST_UNIT = 'first_unit';
    public const BASE_PER_UNIT = 'per_unit';
    public const BASE_FIXED = 'fixed';

    public const TYPE_PERCENT = 'percent';
    public const TYPE_FIXED = 'fixed';

    public const SCOPE_BUSINESS_GLOBAL = 'business_global';
    public const SCOPE_BUSINESS_SERVICE = 'business_service';
    public const SCOPE_BUSINESS_CHILD = 'business_child';
    public const SCOPE_BUSINESS_CHILD_SERVICE = 'business_child_service';

    protected $fillable = [
        'business_id',
        'platform_service_id',
        'category_child_id',
        'scope_key',
        'priority',

        'is_enabled',
        'deposit_mode',
        'calculation_base',
        'deposit_type',
        'deposit_value',
        'max_deposit_percent',
        'min_deposit_amount',
        'max_deposit_amount',
        'external_verification_enabled',
        'wallet_hold_enabled',
        'business_counter_hold_enabled',
        'business_counter_hold_percent',
        'dispute_resolution_days',
        'warning_every_days',
        'non_cooperation_fee_enabled',
        'non_cooperation_fee_type',
        'non_cooperation_fee_value',
        'currency',
        'notes',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'platform_service_id' => 'integer',
        'category_child_id' => 'integer',
        'priority' => 'integer',

        'is_enabled' => 'boolean',
        'deposit_value' => 'decimal:2',
        'max_deposit_percent' => 'decimal:2',
        'min_deposit_amount' => 'decimal:2',
        'max_deposit_amount' => 'decimal:2',
        'external_verification_enabled' => 'boolean',
        'wallet_hold_enabled' => 'boolean',
        'business_counter_hold_enabled' => 'boolean',
        'business_counter_hold_percent' => 'decimal:2',
        'dispute_resolution_days' => 'integer',
        'warning_every_days' => 'integer',
        'non_cooperation_fee_enabled' => 'boolean',
        'non_cooperation_fee_value' => 'decimal:2',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function platformService(): BelongsTo
    {
        return $this->belongsTo(PlatformService::class, 'platform_service_id');
    }

    public function categoryChild(): BelongsTo
    {
        return $this->belongsTo(CategoryChild::class, 'category_child_id');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function scopeForBusiness(Builder $query, int $businessId): Builder
    {
        return $query->where('business_id', $businessId);
    }

    public static function resolveScopeKey(?int $platformServiceId = null, ?int $categoryChildId = null): string
    {
        if ($platformServiceId && $categoryChildId) {
            return self::SCOPE_BUSINESS_CHILD_SERVICE;
        }

        if ($platformServiceId) {
            return self::SCOPE_BUSINESS_SERVICE;
        }

        if ($categoryChildId) {
            return self::SCOPE_BUSINESS_CHILD;
        }

        return self::SCOPE_BUSINESS_GLOBAL;
    }
}