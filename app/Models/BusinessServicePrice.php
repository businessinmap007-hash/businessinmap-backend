<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessServicePrice extends Model
{
    protected $table = 'business_service_prices';

    protected $fillable = [
        'business_id',
        'service_id',
        'price',
        'is_active',
        'deposit_enabled',
        'deposit_percent',
        'discount_enabled',
        'discount_percent',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'service_id' => 'integer',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'deposit_enabled' => 'boolean',
        'deposit_percent' => 'integer',
        'discount_enabled' => 'boolean',
        'discount_percent' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(PlatformService::class, 'service_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getDiscountAmountAttribute(): float
    {
        if (!$this->discount_enabled || (int) $this->discount_percent <= 0) {
            return 0.0;
        }

        return round(((float) $this->price * (int) $this->discount_percent) / 100, 2);
    }

    public function getPriceAfterDiscountAttribute(): float
    {
        return round((float) $this->price - (float) $this->discount_amount, 2);
    }

    public function getDepositAmountAttribute(): float
    {
        if (!$this->deposit_enabled || (int) $this->deposit_percent <= 0) {
            return 0.0;
        }

        return round(((float) $this->price_after_discount * (int) $this->deposit_percent) / 100, 2);
    }

    public function getRemainingAmountAttribute(): float
    {
        return round((float) $this->price_after_discount - (float) $this->deposit_amount, 2);
    }
}