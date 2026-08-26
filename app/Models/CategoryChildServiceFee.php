<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryChildServiceFee extends Model
{
    protected $table = 'category_child_service_fees';

    public const PAYER_BUSINESS = 'business';
    public const PAYER_CLIENT = 'client';

    public const PAYERS = [
        self::PAYER_BUSINESS,
        self::PAYER_CLIENT,
    ];

    public const FEE_TYPE_BUSINESS = 'business_fee';
    public const FEE_TYPE_CLIENT = 'client_fee';

    public const CALC_TYPE_FIXED = 'fixed';
    public const CALC_TYPE_PERCENT = 'percent';

    public const CALC_TYPES = [
        self::CALC_TYPE_FIXED,
        self::CALC_TYPE_PERCENT,
    ];

    public const DEFAULT_CURRENCY = 'EGP';

    /*
    |--------------------------------------------------------------------------
    | ملاحظة
    |--------------------------------------------------------------------------
    | لا نغيّر الكود الآن إلى platform_service_fee حتى لا نكسر أي WalletFeeService
    | يعتمد على booking_execution. سنراجعه في BIM-6.4.
    |--------------------------------------------------------------------------
    */
    public const DEFAULT_FEE_CODE = 'booking_execution';

    protected $fillable = [
        'category_id',
        'child_id',
        'fee_group_id',

        'business_fee_enabled',
        'business_fee_type',
        'business_fee_amount',

        'client_fee_enabled',
        'client_fee_type',
        'client_fee_amount',

        'currency',
        'is_active',
        'sort_order',
        'notes',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'child_id' => 'integer',
        'fee_group_id' => 'integer',

        'business_fee_enabled' => 'boolean',
        'business_fee_type' => 'string',
        'business_fee_amount' => 'decimal:2',

        'client_fee_enabled' => 'boolean',
        'client_fee_type' => 'string',
        'client_fee_amount' => 'decimal:2',

        'currency' => 'string',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'notes' => 'string',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(CategoryChild::class, 'child_id');
    }

    public function categoryChild(): BelongsTo
    {
        return $this->child();
    }

    /**
     * The shared rate this row uses instead of its own — «مجموعة أبناء».
     * One edit on the group moves every member at once.
     */
    public function feeGroup(): BelongsTo
    {
        return $this->belongsTo(FeeGroup::class, 'fee_group_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query, $value = true): Builder
    {
        if ($value === null || $value === '') {
            return $query;
        }

        return $query->where('is_active', (bool) $value);
    }

    public function scopeForChild(Builder $query, ?int $childId): Builder
    {
        if (! $childId) {
            return $query;
        }

        return $query->where('child_id', (int) $childId);
    }

    public function scopeForPayer(Builder $query, ?string $payer): Builder
    {
        $payer = self::normalizePayer($payer);

        if (! $payer) {
            return $query;
        }

        if ($payer === self::PAYER_BUSINESS) {
            return $query
                ->where('business_fee_enabled', 1)
                ->where('business_fee_amount', '>', 0);
        }

        if ($payer === self::PAYER_CLIENT) {
            return $query
                ->where('client_fee_enabled', 1)
                ->where('client_fee_amount', '>', 0);
        }

        return $query;
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderByRaw('COALESCE(sort_order, 999999) ASC')
            ->orderBy('id');
    }

    public function scopeChargeable(Builder $query): Builder
    {
        return $query
            ->where('is_active', 1)
            ->where(function ($sub) {
                $sub->where(function ($q) {
                    $q->where('business_fee_enabled', 1)
                        ->where('business_fee_amount', '>', 0);
                })->orWhere(function ($q) {
                    $q->where('client_fee_enabled', 1)
                        ->where('client_fee_amount', '>', 0);
                });
            });
    }
    public function scopeForCategory(Builder $query, ?int $categoryId): Builder
    {
        if (! $categoryId) {
            return $query;
        }

        return $query->where('category_id', (int) $categoryId);
    }

    public function scopeForRootChild(Builder $query, ?int $categoryId, ?int $childId): Builder
    {
        return $query
            ->forCategory($categoryId)
            ->forChild($childId);
    }

    /*
    |--------------------------------------------------------------------------
    | Finders
    |--------------------------------------------------------------------------
    */

    /** The one fee for this (root, child) — no more per-service axis. */
    public static function activeForRootChild(int $categoryId, int $childId): ?self
    {
        if ($categoryId <= 0 || $childId <= 0) {
            return null;
        }

        return static::query()
            ->active(1)
            ->forRootChild($categoryId, $childId)
            ->ordered()
            ->first();
    }

    /** Root unknown — whichever active row this child carries, any root. */
    public static function activeForChild(int $childId): ?self
    {
        if ($childId <= 0) {
            return null;
        }

        return static::query()
            ->active(1)
            ->forChild($childId)
            ->ordered()
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Normalizers / Mutators
    |--------------------------------------------------------------------------
    */

    public static function normalizePayer(?string $payer): ?string
    {
        $payer = strtolower(trim((string) $payer));

        return in_array($payer, self::PAYERS, true) ? $payer : null;
    }

    public static function normalizeCalcType(?string $type): ?string
    {
        $type = strtolower(trim((string) $type));

        return in_array($type, self::CALC_TYPES, true) ? $type : null;
    }

    public function setBusinessFeeTypeAttribute($value): void
    {
        $this->attributes['business_fee_type'] = self::normalizeCalcType($value);
    }

    public function setClientFeeTypeAttribute($value): void
    {
        $this->attributes['client_fee_type'] = self::normalizeCalcType($value);
    }

    public function setCurrencyAttribute($value): void
    {
        $currency = strtoupper(trim((string) $value));

        $this->attributes['currency'] = $currency !== ''
            ? mb_substr($currency, 0, 3)
            : self::DEFAULT_CURRENCY;
    }

    public function setBusinessFeeAmountAttribute($value): void
    {
        $this->attributes['business_fee_amount'] = round(max((float) $value, 0), 2);
    }

    public function setClientFeeAmountAttribute($value): void
    {
        $this->attributes['client_fee_amount'] = round(max((float) $value, 0), 2);
    }

    /*
    |--------------------------------------------------------------------------
    | Fee Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * The row whose numbers actually apply — this one's own, or its fee
     * group's when it is assigned to one. Every fee-computing method below
     * reads through this, so a group assignment needs deciding in ONE place.
     */
    protected function effectiveFeeSource(): self|FeeGroup
    {
        if ($this->fee_group_id && $this->feeGroup) {
            return $this->feeGroup;
        }

        return $this;
    }

    public function hasBusinessFee(): bool
    {
        $src = $this->effectiveFeeSource();

        return (bool) $src->business_fee_enabled
            && round((float) $src->business_fee_amount, 2) > 0;
    }

    public function hasClientFee(): bool
    {
        $src = $this->effectiveFeeSource();

        return (bool) $src->client_fee_enabled
            && round((float) $src->client_fee_amount, 2) > 0;
    }

    public function hasAnyFee(): bool
    {
        return $this->hasBusinessFee() || $this->hasClientFee();
    }

    public function isChargeable(): bool
    {
        if ($this->fee_group_id && $this->feeGroup && ! $this->feeGroup->is_active) {
            return false;
        }

        return (bool) $this->is_active && $this->hasAnyFee();
    }

    public function isChargeableFor(string $payer): bool
    {
        $payer = self::normalizePayer($payer);
        $active = $this->is_active && ! ($this->fee_group_id && $this->feeGroup && ! $this->feeGroup->is_active);

        return match ($payer) {
            self::PAYER_BUSINESS => (bool) $active && $this->hasBusinessFee(),
            self::PAYER_CLIENT => (bool) $active && $this->hasClientFee(),
            default => false,
        };
    }

    public function amountFor(string $payer, float $baseAmount = 0): float
    {
        $payer = self::normalizePayer($payer);
        $baseAmount = round(max((float) $baseAmount, 0), 2);
        $src = $this->effectiveFeeSource();

        if ($payer === self::PAYER_BUSINESS) {
            if (! $this->hasBusinessFee()) {
                return 0.00;
            }

            return $this->calculateAmountByType(
                type: $src->business_fee_type ?: self::CALC_TYPE_FIXED,
                value: (float) $src->business_fee_amount,
                baseAmount: $baseAmount
            );
        }

        if ($payer === self::PAYER_CLIENT) {
            if (! $this->hasClientFee()) {
                return 0.00;
            }

            return $this->calculateAmountByType(
                type: $src->client_fee_type ?: self::CALC_TYPE_FIXED,
                value: (float) $src->client_fee_amount,
                baseAmount: $baseAmount
            );
        }

        return 0.00;
    }

    protected function calculateAmountByType(?string $type, float $value, float $baseAmount = 0): float
    {
        $type = self::normalizeCalcType($type) ?: self::CALC_TYPE_FIXED;
        $value = round(max((float) $value, 0), 2);
        $baseAmount = round(max((float) $baseAmount, 0), 2);

        if ($value <= 0) {
            return 0.00;
        }

        if ($type === self::CALC_TYPE_FIXED) {
            return $value;
        }

        if ($type === self::CALC_TYPE_PERCENT) {
            if ($baseAmount <= 0) {
                return 0.00;
            }

            return round($baseAmount * ($value / 100), 2);
        }

        return 0.00;
    }

    public function calcTypeFor(string $payer): string
    {
        $payer = self::normalizePayer($payer);
        $src = $this->effectiveFeeSource();

        if ($payer === self::PAYER_BUSINESS) {
            return self::normalizeCalcType($src->business_fee_type)
                ?: self::CALC_TYPE_FIXED;
        }

        if ($payer === self::PAYER_CLIENT) {
            return self::normalizeCalcType($src->client_fee_type)
                ?: self::CALC_TYPE_FIXED;
        }

        return self::CALC_TYPE_FIXED;
    }

    /**
     * The raw configured rate (the percent number, or the fixed amount) —
     * from the fee group when assigned to one, same as every other read
     * here. Callers that need to solve for a base amount before they can
     * call `amountFor()` (percent math where net isn't known yet) need this
     * instead of `amountFor()`'s already-computed result.
     */
    public function rateValueFor(string $payer): float
    {
        $payer = self::normalizePayer($payer);
        $src = $this->effectiveFeeSource();

        if ($payer === self::PAYER_BUSINESS) {
            return round((float) $src->business_fee_amount, 2);
        }

        if ($payer === self::PAYER_CLIENT) {
            return round((float) $src->client_fee_amount, 2);
        }

        return 0.00;
    }

    public function feeTypeFor(string $payer): ?string
    {
        $payer = self::normalizePayer($payer);

        return match ($payer) {
            self::PAYER_BUSINESS => self::FEE_TYPE_BUSINESS,
            self::PAYER_CLIENT => self::FEE_TYPE_CLIENT,
            default => null,
        };
    }

    public function currencyCode(): string
    {
        $currency = strtoupper(trim((string) $this->effectiveFeeSource()->currency));

        return $currency !== '' ? $currency : self::DEFAULT_CURRENCY;
    }

    /*
    |--------------------------------------------------------------------------
    | Snapshot Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * @param  int|null  $serviceId  Which service the operation actually used
     *         — recorded for reporting only, since one fee now covers every
     *         service this child offers; it no longer selects the row.
     */
    public function toFeeSnapshot(string $payer, float $baseAmount = 0, ?int $serviceId = null): ?array
    {
        $payer = self::normalizePayer($payer);

        if (! $payer || ! $this->isChargeableFor($payer)) {
            return null;
        }

        $src = $this->effectiveFeeSource();

        return [
            'id' => (int) $this->id,
            'fee_row_id' => (int) $this->id,
            'source' => $this->fee_group_id ? 'fee_group' : 'category_child_override',
            'fee_group_id' => $this->fee_group_id ? (int) $this->fee_group_id : null,

            'payer' => $payer,
            'fee_type' => $this->feeTypeFor($payer),
            'calc_type' => $this->calcTypeFor($payer),
            'rate_value' => $payer === self::PAYER_BUSINESS
                ? round((float) $src->business_fee_amount, 2)
                : round((float) $src->client_fee_amount, 2),

            'amount' => $this->amountFor($payer, $baseAmount),
            'currency' => $this->currencyCode(),

            'child_id' => (int) $this->child_id,
            'category_id' => (int) $this->category_id,
            'service_id' => (int) ($serviceId ?? 0),
            'platform_service_id' => (int) ($serviceId ?? 0),

            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) ($this->sort_order ?? 0),
            'notes' => $this->notes,
        ];
    }

    /**
     * @param  int|null  $serviceId  Which service the operation actually used
     *         — recorded for reporting only, same reason as `toFeeSnapshot()`.
     */
    public function toWalletFeeLine(
        string $payer,
        int $userId,
        float $baseAmount,
        int $bookingId,
        int $businessId,
        int $clientId,
        ?string $feeCode = null,
        ?int $serviceId = null
    ): ?array {
        $payer = self::normalizePayer($payer);

        if (! $payer || ! $this->isChargeableFor($payer)) {
            return null;
        }

        $feeCode = trim((string) ($feeCode ?: self::DEFAULT_FEE_CODE));
        $src = $this->effectiveFeeSource();

        return [
            'payer' => $payer,
            'user_id' => (int) $userId,

            'category_child_service_fee_id' => (int) $this->id,
            'service_fee_id' => (int) $this->id,
            'fee_row_id' => (int) $this->id,
            'source' => $this->fee_group_id ? 'fee_group' : 'category_child_override',
            'fee_group_id' => $this->fee_group_id ? (int) $this->fee_group_id : null,

            'fee_code' => $feeCode,
            'fee_type' => $this->feeTypeFor($payer),
            'calc_type' => $this->calcTypeFor($payer),
            'rate_value' => $payer === self::PAYER_BUSINESS
                ? round((float) $src->business_fee_amount, 2)
                : round((float) $src->client_fee_amount, 2),

            'amount' => $this->amountFor($payer, $baseAmount),
            'currency' => $this->currencyCode(),
            'base_amount' => round((float) $baseAmount, 2),

            /*
            |--------------------------------------------------------------------------
            | Official wallet reference
            |--------------------------------------------------------------------------
            | الأعمدة الرسمية في wallet_transactions هي reference_type/reference_id.
            | source_type/source_id نضعها داخل meta فقط كـ alias مستقبلي.
            |--------------------------------------------------------------------------
            */
            'reference_type' => 'booking',
            'reference_id' => (int) $bookingId,

            'source_type' => 'booking',
            'source_id' => (int) $bookingId,

            'booking_id' => (int) $bookingId,
            'category_id' => (int) $this->category_id,
            'child_id' => (int) $this->child_id,

            'service_id' => (int) ($serviceId ?? 0),
            'platform_service_id' => (int) ($serviceId ?? 0),

            'business_id' => (int) $businessId,
            'client_id' => (int) $clientId,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Display Helpers
    |--------------------------------------------------------------------------
    */

    public function getDisplayNameAttribute(): string
    {
        $root = $this->category?->name_ar
            ?: $this->category?->name_en
            ?: ('Root #' . $this->category_id);

        $child = $this->child?->display_name
            ?: $this->child?->name_ar
            ?: $this->child?->name_en
            ?: ('Child #' . $this->child_id);

        return "{$root} / {$child}";
    }
}