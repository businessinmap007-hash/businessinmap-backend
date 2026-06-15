<?php

namespace App\Models;

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

    public const TYPE_PERCENT = 'percent';
    public const TYPE_FIXED = 'fixed';

    protected $fillable = [
        'business_id',
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
}
