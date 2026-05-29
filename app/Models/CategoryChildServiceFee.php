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
        'child_id',
        'platform_service_id',

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
        'child_id' => 'integer',
        'platform_service_id' => 'integer',

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

    public function child(): BelongsTo
    {
        return $this->belongsTo(CategoryChild::class, 'child_id');
    }

    public function categoryChild(): BelongsTo
    {
        return $this->child();
    }

    public function platformService(): BelongsTo
    {
        return $this->belongsTo(PlatformService::class, 'platform_service_id');
    }

    public function service(): BelongsTo
    {
        return $this->platformService();
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

    public function scopeForService(Builder $query, ?int $serviceId): Builder
    {
        if (! $serviceId) {
            return $query;
        }

        return $query->where('platform_service_id', (int) $serviceId);
    }

    public function scopeForPair(Builder $query, ?int $childId, ?int $serviceId): Builder
    {
        return $query
            ->forChild($childId)
            ->forService($serviceId);
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

    /*
    |--------------------------------------------------------------------------
    | Finders
    |--------------------------------------------------------------------------
    */

    public static function activeForPair(int $childId, int $serviceId): ?self
    {
        if ($childId <= 0 || $serviceId <= 0) {
            return null;
        }

        return static::query()
            ->active(1)
            ->forPair($childId, $serviceId)
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

    public function hasBusinessFee(): bool
    {
        return (bool) $this->business_fee_enabled
            && round((float) $this->business_fee_amount, 2) > 0;
    }

    public function hasClientFee(): bool
    {
        return (bool) $this->client_fee_enabled
            && round((float) $this->client_fee_amount, 2) > 0;
    }

    public function hasAnyFee(): bool
    {
        return $this->hasBusinessFee() || $this->hasClientFee();
    }

    public function isChargeable(): bool
    {
        return (bool) $this->is_active && $this->hasAnyFee();
    }

    public function isChargeableFor(string $payer): bool
    {
        $payer = self::normalizePayer($payer);

        return match ($payer) {
            self::PAYER_BUSINESS => (bool) $this->is_active && $this->hasBusinessFee(),
            self::PAYER_CLIENT => (bool) $this->is_active && $this->hasClientFee(),
            default => false,
        };
    }

    public function amountFor(string $payer, float $baseAmount = 0): float
    {
        $payer = self::normalizePayer($payer);
        $baseAmount = round(max((float) $baseAmount, 0), 2);

        if ($payer === self::PAYER_BUSINESS) {
            if (! $this->hasBusinessFee()) {
                return 0.00;
            }

            return $this->calculateAmountByType(
                type: $this->business_fee_type ?: self::CALC_TYPE_FIXED,
                value: (float) $this->business_fee_amount,
                baseAmount: $baseAmount
            );
        }

        if ($payer === self::PAYER_CLIENT) {
            if (! $this->hasClientFee()) {
                return 0.00;
            }

            return $this->calculateAmountByType(
                type: $this->client_fee_type ?: self::CALC_TYPE_FIXED,
                value: (float) $this->client_fee_amount,
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

        if ($payer === self::PAYER_BUSINESS) {
            return self::normalizeCalcType($this->business_fee_type)
                ?: self::CALC_TYPE_FIXED;
        }

        if ($payer === self::PAYER_CLIENT) {
            return self::normalizeCalcType($this->client_fee_type)
                ?: self::CALC_TYPE_FIXED;
        }

        return self::CALC_TYPE_FIXED;
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
        $currency = strtoupper(trim((string) $this->currency));

        return $currency !== '' ? $currency : self::DEFAULT_CURRENCY;
    }

    /*
    |--------------------------------------------------------------------------
    | Snapshot Helpers
    |--------------------------------------------------------------------------
    */

    public function toFeeSnapshot(string $payer, float $baseAmount = 0): ?array
    {
        $payer = self::normalizePayer($payer);

        if (! $payer || ! $this->isChargeableFor($payer)) {
            return null;
        }

        return [
            'id' => (int) $this->id,
            'fee_row_id' => (int) $this->id,
            'source' => 'category_child_override',

            'payer' => $payer,
            'fee_type' => $this->feeTypeFor($payer),
            'calc_type' => $this->calcTypeFor($payer),
            'rate_value' => $payer === self::PAYER_BUSINESS
                ? round((float) $this->business_fee_amount, 2)
                : round((float) $this->client_fee_amount, 2),

            'amount' => $this->amountFor($payer, $baseAmount),
            'currency' => $this->currencyCode(),

            'child_id' => (int) $this->child_id,
            'service_id' => (int) $this->platform_service_id,
            'platform_service_id' => (int) $this->platform_service_id,

            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) ($this->sort_order ?? 0),
            'notes' => $this->notes,
        ];
    }

    public function toWalletFeeLine(
        string $payer,
        int $userId,
        float $baseAmount,
        int $bookingId,
        int $businessId,
        int $clientId,
        ?string $feeCode = null
    ): ?array {
        $payer = self::normalizePayer($payer);

        if (! $payer || ! $this->isChargeableFor($payer)) {
            return null;
        }

        $feeCode = trim((string) ($feeCode ?: self::DEFAULT_FEE_CODE));

        return [
            'payer' => $payer,
            'user_id' => (int) $userId,

            'category_child_service_fee_id' => (int) $this->id,
            'service_fee_id' => (int) $this->id,
            'fee_row_id' => (int) $this->id,
            'source' => 'category_child_override',

            'fee_code' => $feeCode,
            'fee_type' => $this->feeTypeFor($payer),
            'calc_type' => $this->calcTypeFor($payer),
            'rate_value' => $payer === self::PAYER_BUSINESS
                ? round((float) $this->business_fee_amount, 2)
                : round((float) $this->client_fee_amount, 2),

            'amount' => $this->amountFor($payer, $baseAmount),
            'currency' => $this->currencyCode(),
            'base_amount' => round((float) $baseAmount, 2),

            'booking_id' => (int) $bookingId,
            'service_id' => (int) $this->platform_service_id,
            'platform_service_id' => (int) $this->platform_service_id,

            'business_id' => (int) $businessId,
            'client_id' => (int) $clientId,
            'child_id' => (int) $this->child_id,

            'rules' => null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Display Helpers
    |--------------------------------------------------------------------------
    */

    public function getDisplayNameAttribute(): string
    {
        $child = $this->child?->display_name
            ?: $this->child?->name_ar
            ?: $this->child?->name_en
            ?: ('Child #' . $this->child_id);

        $service = $this->platformService?->display_name
            ?: $this->platformService?->name_ar
            ?: $this->platformService?->name_en
            ?: $this->platformService?->key
            ?: ('Service #' . $this->platform_service_id);

        return "{$child} / {$service}";
    }
}