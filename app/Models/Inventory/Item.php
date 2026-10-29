<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A stock item of one business, counted in ONE base unit (علبة، جرام، مل، قطعة); bigger units are only factors over it
 * (`units`: كرتونة = 24، بالتة = 1920). Its balance is never stored here — it is the sum of its moves ({@see Level}).
 * `avg_cost` is the weighted average cost of one base unit, recomputed on every receipt.
 */
class Item extends Model
{
    protected $table = 'inv_items';

    protected $guarded = ['id'];

    protected $casts = [
        'reorder_level' => 'float',
        'avg_cost' => 'float',
        'track_lots' => 'boolean',
        'is_active' => 'boolean',
    ];

    public const KINDS = ['count', 'weight', 'volume', 'length'];

    public function units(): HasMany
    {
        return $this->hasMany(ItemUnit::class, 'item_id')->orderBy('factor');
    }

    public function levels(): HasMany
    {
        return $this->hasMany(Level::class, 'item_id');
    }

    public function onHand(): float
    {
        return (float) $this->levels()->sum('qty');
    }
}
