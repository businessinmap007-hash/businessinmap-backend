<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryDriver extends Model
{
     protected $fillable = [
        'user_id',
        'vehicle_type',
        'vehicle_number',
        'active'
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }
}
