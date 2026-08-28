<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A restaurant's menu billing settings — whether menu prices already include
 * the service fee / tax. See MenuBillingService + the 2026_07_16 migration.
 */
class BusinessMenuSetting extends Model
{
    protected $fillable = [
        'business_id',
        'prices_include_service',
        'prices_include_tax',
        'tax_rate_percent',
        'min_order_amount',
        'default_margin_percent',
        'deposit_required_above',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'prices_include_service' => 'boolean',
        'prices_include_tax' => 'boolean',
        'tax_rate_percent' => 'float',
        'min_order_amount' => 'float',
        'default_margin_percent' => 'float',
        'deposit_required_above' => 'float',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }
}
