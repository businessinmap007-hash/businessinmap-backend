<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

/** Table `inv_stocktake_lines` — see the inventory suite migration (2026_11_23). */
class StocktakeLine extends Model
{
    protected $table = 'inv_stocktake_lines';

    protected $guarded = ['id'];

    protected $casts = ['expected_qty' => 'float', 'counted_qty' => 'float'];
}
