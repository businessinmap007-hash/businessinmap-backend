<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessServicePrice extends Model
{
    protected $table = 'business_service_prices';

    protected $fillable = [
        'business_id',
        'service_id',
        'bookable_item_type',
        'price',
        'currency',
        'is_active',
        'deposit_enabled',
        'deposit_percent',
        'discount_enabled',
        'discount_percent',
    ];

    protected $casts = [
        'business_id'        => 'integer',
        'service_id'         => 'integer',
        'bookable_item_type' => 'string',
        'price'              => 'decimal:2',
        'currency'           => 'string',
        'is_active'          => 'boolean',
        'deposit_enabled'    => 'boolean',
        'deposit_percent'    => 'integer',
        'discount_enabled'   => 'boolean',
        'discount_percent'   => 'integer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(PlatformService::class, 'service_id');
    }

    public function getDisplayNameAttribute(): string
    {
        $business = $this->business?->name ?: 'Business';
        $service  = $this->service?->name_ar ?: $this->service?->name_en ?: $this->service?->key ?: 'Service';
        $type     = $this->bookable_item_type ?: 'category';

        return "{$business} / {$service} / {$type}";
    }
}