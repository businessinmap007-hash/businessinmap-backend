<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One way of paying an item on instalments: over [installment_months] months, [installment_down] paid
 * with the first one (per unit), at [markup_percent] over the cash price. Cash is the item's own price.
 */
class MenuItemPaymentPlan extends Model
{
    protected $table = 'menu_item_payment_plans';

    protected $fillable = ['menu_item_id', 'installment_months', 'installment_down', 'markup_percent', 'is_active'];

    protected $casts = [
        'menu_item_id' => 'integer',
        'installment_months' => 'integer',
        'installment_down' => 'decimal:2',
        'markup_percent' => 'decimal:8',
        'is_active' => 'boolean',
    ];

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** What ONE unit costs on this plan, given what it costs in cash. */
    public function unitPrice(float $cash): float
    {
        return round($cash * (1 + (float) $this->markup_percent / 100), 2);
    }
}
