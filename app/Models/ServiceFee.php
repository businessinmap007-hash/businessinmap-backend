<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceFee extends Model
{
    protected $table = 'service_fees';

    public const FEE_TYPE_PLATFORM = 'platform_fee';
    public const FEE_TYPE_BUSINESS = 'business_fee';
    public const FEE_TYPE_CLIENT   = 'client_fee';

    public const PAYER_BUSINESS = 'business';
    public const PAYER_CLIENT   = 'client';
    public const PAYER_SPLIT    = 'split';

    public const CALC_FIXED   = 'fixed';
    public const CALC_PERCENT = 'percent';

    protected $fillable = [
        'business_id',
        'service_id',
        'fee_code',
        'fee_type',
        'payer',
        'calc_type',
        'amount',
        'min_amount',
        'max_amount',
        'currency',
        'priority',
        'is_active',
        'rules',
        'notes',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'service_id' => 'integer',
        'amount' => 'decimal:2',
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'priority' => 'integer',
        'is_active' => 'boolean',
        'rules' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForFeeCode(Builder $query, ?string $feeCode): Builder
    {
        if (!$feeCode) {
            return $query;
        }

        return $query->where('fee_code', $feeCode);
    }

    public function scopeForBusiness(Builder $query, $businessId): Builder
    {
        if ($businessId === null || $businessId === '') {
            return $query->whereNull('business_id');
        }

        return $query->where('business_id', $businessId);
    }

    public function scopeForService(Builder $query, $serviceId): Builder
    {
        if ($serviceId === null || $serviceId === '') {
            return $query->whereNull('service_id');
        }

        return $query->where('service_id', $serviceId);
    }

    public function scopeForPayer(Builder $query, ?string $payer): Builder
    {
        if (!$payer) {
            return $query;
        }

        return $query->where('payer', $payer);
    }

    public function getFeeTypeLabelAttribute(): string
    {
        return match ($this->fee_type) {
            self::FEE_TYPE_PLATFORM => 'Platform Fee',
            self::FEE_TYPE_BUSINESS => 'Business Fee',
            self::FEE_TYPE_CLIENT   => 'Client Fee',
            default => (string) $this->fee_type,
        };
    }

    public function getCalcTypeLabelAttribute(): string
    {
        return match ($this->calc_type) {
            self::CALC_FIXED   => 'Fixed',
            self::CALC_PERCENT => 'Percent',
            default => (string) $this->calc_type,
        };
    }

    public function getPayerLabelAttribute(): string
    {
        return match ($this->payer) {
            self::PAYER_BUSINESS => 'Business',
            self::PAYER_CLIENT   => 'Client',
            self::PAYER_SPLIT    => 'Split',
            default => (string) $this->payer,
        };
    }
}