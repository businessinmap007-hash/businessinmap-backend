<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class BusinessServicePrice extends Model
{
    protected $table = 'business_service_prices';

    protected $fillable = [
        'business_id','platform_service_id',
        'price','is_active',
        'fee_type','fee_value',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'fee_value' => 'decimal:2',
    ];

    public function business()
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function service()
    {
        return $this->belongsTo(PlatformService::class, 'platform_service_id');
    }
}