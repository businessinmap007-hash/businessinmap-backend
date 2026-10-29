<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

/** Table `inv_devices` — see the inventory suite migration (2026_11_23). */
class Device extends Model
{
    protected $table = 'inv_devices';

    protected $guarded = ['id'];

    protected $casts = ['screens' => 'array', 'is_hub' => 'boolean', 'last_seen_at' => 'datetime', 'revoked_at' => 'datetime'];
}
