<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformService extends Model
{
    protected $table = 'platform_services';

    protected $fillable = [
        'key','name_ar','name_en',
        'is_active',
        'supports_deposit','max_deposit_percent',
        'fee_type','fee_value',
        'rules',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'supports_deposit' => 'boolean',
        'max_deposit_percent' => 'integer',
        'fee_value' => 'decimal:2',
        'rules' => 'array',
    ];
}