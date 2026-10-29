<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

/** Table `inv_pos_sale_lines` — see the inventory suite migration (2026_11_23). */
class PosSaleLine extends Model
{
    protected $table = 'inv_pos_sale_lines';

    protected $guarded = ['id'];

    protected $casts = ['extras' => 'array', 'qty' => 'float', 'unit_price' => 'float', 'total' => 'float'];
}
