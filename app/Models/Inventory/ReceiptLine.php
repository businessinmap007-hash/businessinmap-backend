<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

/** Table `inv_receipt_lines` — see the inventory suite migration (2026_11_23). */
class ReceiptLine extends Model
{
    protected $table = 'inv_receipt_lines';

    protected $guarded = ['id'];

    protected $casts = ['unit_factor' => 'float', 'qty' => 'float', 'damaged_qty' => 'float', 'unit_price' => 'float'];
}
