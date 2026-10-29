<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

/** Table `inv_supplier_payments` — see the inventory suite migration (2026_11_23). */
class SupplierPayment extends Model
{
    protected $table = 'inv_supplier_payments';

    protected $guarded = ['id'];

    protected $casts = ['amount' => 'float', 'paid_on' => 'date'];
}
