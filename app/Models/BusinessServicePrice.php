<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessServicePrice extends Model
{
    protected $table = 'business_service_prices';

    protected $fillable = [
        'business_id','service_id','price','is_active'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'bool',
    ];
}