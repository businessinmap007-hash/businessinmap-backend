<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

/** Table `inv_pos_shifts` — see the inventory suite migration (2026_11_23). */
class PosShift extends Model
{
    protected $table = 'inv_pos_shifts';

    protected $guarded = ['id'];

    protected $casts = ['opening_cash' => 'float', 'counted_cash' => 'float', 'opened_at' => 'datetime', 'closed_at' => 'datetime'];
}
