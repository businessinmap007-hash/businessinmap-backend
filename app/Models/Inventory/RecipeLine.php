<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

/** Table `inv_recipe_lines` — see the inventory suite migration (2026_11_23). */
class RecipeLine extends Model
{
    protected $table = 'inv_recipe_lines';

    protected $guarded = ['id'];

    protected $casts = ['qty' => 'float', 'waste_pct' => 'float'];
}
