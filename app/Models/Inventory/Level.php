<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

/** Table `inv_levels` — see the inventory suite migration (2026_11_23). */
class Level extends Model
{
    protected $table = 'inv_levels';

    protected $guarded = ['id'];

    protected $casts = ['qty' => 'float'];
}
