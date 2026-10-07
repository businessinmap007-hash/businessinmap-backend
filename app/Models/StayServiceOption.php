<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One thing a hotel lets its guests order during a stay — the hotel's own list. */
class StayServiceOption extends Model
{
    protected $table = 'stay_service_options';

    protected $fillable = ['business_id', 'title', 'is_active', 'sort_order'];

    protected $casts = [
        'business_id' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
