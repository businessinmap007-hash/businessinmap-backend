<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

/** Table `inv_supplier_returns` — see the inventory suite migration (2026_11_23). */
class SupplierReturn extends Model
{
    protected $table = 'inv_supplier_returns';

    protected $guarded = ['id'];

    protected $casts = ['total' => 'float'];
}
