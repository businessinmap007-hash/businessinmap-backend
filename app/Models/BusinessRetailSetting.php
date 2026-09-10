<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A retailer's own retail-channel settings — today just the minimum order
 * amount it may impose on a customer's cart of its own catalog listings. See
 * the 2026_09_10 migration and CustomerCartService::assertMeetsRetailMinimum().
 */
class BusinessRetailSetting extends Model
{
    protected $fillable = [
        'business_id',
        'min_order_amount',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'min_order_amount' => 'float',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }
}
