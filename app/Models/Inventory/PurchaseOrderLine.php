<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

/** Table `inv_purchase_order_lines` — see the inventory suite migration (2026_11_23). */
class PurchaseOrderLine extends Model
{
    protected $table = 'inv_purchase_order_lines';

    protected $guarded = ['id'];

    protected $casts = ['unit_factor' => 'float', 'qty' => 'float', 'unit_price' => 'float', 'received_qty' => 'float', 'billed_qty' => 'float'];
}
