<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceOrderRejection extends Model
{
    protected $table = 'service_order_rejections';

    protected $fillable = [
        'provider_id',
        'target_type',
        'target_id',
        'reason',
    ];
}
