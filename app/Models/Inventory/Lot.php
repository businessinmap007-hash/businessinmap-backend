<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

/** Table `inv_lots` — see the inventory suite migration (2026_11_23). */
class Lot extends Model
{
    protected $table = 'inv_lots';

    protected $guarded = ['id'];

    protected $casts = ['expires_on' => 'date'];
}
