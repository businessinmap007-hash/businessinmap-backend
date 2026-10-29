<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

/** Table `inv_item_units` — see the inventory suite migration (2026_11_23). */
class ItemUnit extends Model
{
    protected $table = 'inv_item_units';

    protected $guarded = ['id'];

    protected $casts = ['factor' => 'float'];
}
