<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

/** Table `inv_suppliers` — see the inventory suite migration (2026_11_23). */
class Supplier extends Model
{
    protected $table = 'inv_suppliers';

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];
}
