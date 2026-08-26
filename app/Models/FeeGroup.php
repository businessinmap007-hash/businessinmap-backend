<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shared platform-fee rate several children carry at once — «مجموعة أبناء».
 *
 * «احدد حسب كل ابن او مجموعة ابناء» — المالك، 2026-08-26. A
 * `category_child_service_fees` row either carries its own rate, or points at
 * one of these; changing the group's numbers moves every member in one edit
 * instead of rewriting each child's row by hand.
 *
 * @see \App\Models\CategoryChildServiceFee::effectiveFeeSource()
 */
class FeeGroup extends Model
{
    protected $table = 'fee_groups';

    protected $fillable = [
        'name_ar',
        'business_fee_enabled',
        'business_fee_type',
        'business_fee_amount',
        'client_fee_enabled',
        'client_fee_type',
        'client_fee_amount',
        'currency',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'business_fee_enabled' => 'boolean',
        'business_fee_amount' => 'decimal:2',
        'client_fee_enabled' => 'boolean',
        'client_fee_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(CategoryChildServiceFee::class, 'fee_group_id');
    }

    public function activeMembers(): HasMany
    {
        return $this->members()->where('is_active', 1);
    }

    /** Same normalization `CategoryChildServiceFee` applies — one group, one rule. */
    public function setBusinessFeeTypeAttribute($value): void
    {
        $this->attributes['business_fee_type'] = CategoryChildServiceFee::normalizeCalcType($value);
    }

    public function setClientFeeTypeAttribute($value): void
    {
        $this->attributes['client_fee_type'] = CategoryChildServiceFee::normalizeCalcType($value);
    }

    public function setCurrencyAttribute($value): void
    {
        $currency = strtoupper(trim((string) $value));

        $this->attributes['currency'] = $currency !== ''
            ? mb_substr($currency, 0, 3)
            : CategoryChildServiceFee::DEFAULT_CURRENCY;
    }

    public function setBusinessFeeAmountAttribute($value): void
    {
        $this->attributes['business_fee_amount'] = round(max((float) $value, 0), 2);
    }

    public function setClientFeeAmountAttribute($value): void
    {
        $this->attributes['client_fee_amount'] = round(max((float) $value, 0), 2);
    }
}
