<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessWorkingHour extends Model
{
    protected $fillable = [
        'business_id',
        'day_of_week',
        'is_closed',
        'open_time',
        'close_time',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_closed' => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }
}
