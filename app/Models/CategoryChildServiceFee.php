<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryChildServiceFee extends Model
{
    protected $table = 'category_child_service_fees';

    public const PAYER_BUSINESS = 'business';
    public const PAYER_CLIENT   = 'client';

    public const FEE_TYPE_BUSINESS = 'business_fee';
    public const FEE_TYPE_CLIENT   = 'client_fee';

    public const DEFAULT_CURRENCY = 'EGP';

    protected $fillable = [
        'child_id',
        'platform_service_id',
        'business_fee_enabled',
        'business_fee_amount',
        'client_fee_enabled',
        'client_fee_amount',
        'currency',
        'is_active',
        'sort_order',
        'notes',
    ];

    protected $casts = [
        'child_id'              => 'integer',
        'platform_service_id'   => 'integer',
        'business_fee_enabled'  => 'boolean',
        'business_fee_amount'   => 'decimal:2',
        'client_fee_enabled'    => 'boolean',
        'client_fee_amount'     => 'decimal:2',
        'is_active'             => 'boolean',
        'sort_order'            => 'integer',
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

        return $query->where('child_id', $childId);
    }

    public function scopeForService(Builder $query, ?int $serviceId): Builder
    {
        if (! $serviceId) {
            return $query;
        }

        return $query->where('platform_service_id', $serviceId);
    }

    public function scopeForPair(Builder $query, ?int $childId, ?int $serviceId): Builder
    {
        return $query
            ->forChild($childId)
            ->forService($serviceId);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderByRaw('COALESCE(sort_order, 999999) ASC')
            ->orderBy('id');
    }

    public function scopeChargeable(Builder $query): Builder
    {
        return $query->where('is_active', 1)
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
    | Mutators
    |--------------------------------------------------------------------------
    */

    public function setCurrencyAttribute($value): void
    {
        $currency = strtoupper(trim((string) $value));

        $this->attributes['currency'] = $currency !== ''
            ? mb_substr($currency, 0, 3)
            : self::DEFAULT_CURRENCY;
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
        return match ($payer) {
            self::PAYER_BUSINESS => (bool) $this->is_active && $this->hasBusinessFee(),
            self::PAYER_CLIENT   => (bool) $this->is_active && $this->hasClientFee(),
            default              => false,
        };
    }

    public function amountFor(string $payer): float
    {
        return match ($payer) {
            self::PAYER_BUSINESS => $this->hasBusinessFee()
                ? round((float) $this->business_fee_amount, 2)
                : 0.00,

            self::PAYER_CLIENT => $this->hasClientFee()
                ? round((float) $this->client_fee_amount, 2)
                : 0.00,

            default => 0.00,
        };
    }

    public function feeTypeFor(string $payer): ?string
    {
        return match ($payer) {
            self::PAYER_BUSINESS => self::FEE_TYPE_BUSINESS,
            self::PAYER_CLIENT   => self::FEE_TYPE_CLIENT,
            default              => null,
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

    public function toFeeSnapshot(string $payer): ?array
    {
        if (! $this->isChargeableFor($payer)) {
            return null;
        }

        return [
            'id' => (int) $this->id,
            'payer' => $payer,
            'fee_type' => $this->feeTypeFor($payer),
            'calc_type' => 'fixed',
            'amount' => $this->amountFor($payer),
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
        string $feeCode = 'booking_execution'
    ): ?array {
        if (! $this->isChargeableFor($payer)) {
            return null;
        }

        return [
            'payer' => $payer,
            'user_id' => $userId,

            'category_child_service_fee_id' => (int) $this->id,
            'service_fee_id' => (int) $this->id,

            'fee_code' => $feeCode,
            'fee_type' => $this->feeTypeFor($payer),
            'calc_type' => 'fixed',

            'amount' => $this->amountFor($payer),
            'currency' => $this->currencyCode(),
            'base_amount' => round((float) $baseAmount, 2),

            'booking_id' => $bookingId,
            'service_id' => (int) $this->platform_service_id,
            'platform_service_id' => (int) $this->platform_service_id,

            'business_id' => $businessId,
            'client_id' => $clientId,
            'child_id' => (int) $this->child_id,

            'rules' => null,
            'notes' => $this->notes,
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