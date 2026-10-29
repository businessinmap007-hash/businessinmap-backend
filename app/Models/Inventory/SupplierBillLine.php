<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

/** Table `inv_supplier_bill_lines` — see the inventory suite migration (2026_11_23). */
class SupplierBillLine extends Model
{
    protected $table = 'inv_supplier_bill_lines';

    protected $guarded = ['id'];

    protected $casts = ['qty' => 'float', 'unit_price' => 'float'];
}
