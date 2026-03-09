<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\PlatformService;

class ServiceFee extends Model
{
    protected $table = 'service_fees';

    protected $fillable = [
        'code',
        'service_id',
        'amount',
        'rules',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
        'rules' => 'array',
    ];

    public function service()
    {
        return $this->belongsTo(PlatformService::class, 'service_id');
    }
}