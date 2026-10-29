<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

/** Table `inv_settings` — see the inventory suite migration (2026_11_23). */
class Setting extends Model
{
    protected $table = 'inv_settings';

    protected $guarded = ['id'];

    protected $casts = ['enabled' => 'boolean', 'features_off' => 'array', 'po_approval_threshold' => 'float'];
}
