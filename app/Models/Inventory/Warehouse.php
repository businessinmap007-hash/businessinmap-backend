<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

/** Table `inv_warehouses` — see the inventory suite migration (2026_11_23). */
class Warehouse extends Model
{
    protected $table = 'inv_warehouses';

    protected $guarded = ['id'];

    protected $casts = ['is_default' => 'boolean', 'is_active' => 'boolean'];
}
