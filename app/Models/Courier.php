<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Courier extends Model
{
    protected $table = 'couriers';

    protected $fillable = [
        'user_id',
        'is_active',
        'location_lat',
        'location_lng',

        // counters
        'accepted_count',
        'delivered_count',
        'cancelled_count',
        'total_ops',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'location_lat' => 'float',
        'location_lng' => 'float',

        'accepted_count' => 'integer',
        'delivered_count' => 'integer',
        'cancelled_count' => 'integer',
        'total_ops' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
