<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceFee extends Model
{
    protected $table = 'service_fees';

    protected $fillable = [
        'business_id',
        'service_id',
        'price',
        'is_active',
        'fee_type',
        'fee_value',
        'rules',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'fee_value' => 'decimal:2',
        'rules' => 'array',
    ];

    public function business()
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
}