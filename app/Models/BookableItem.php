<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BookableItem extends Model
{
    use SoftDeletes;

    protected $table = 'bookable_items';

    protected $fillable = [
        'business_id',
        'service_id',
        'item_type',
        'title',
        'code',
        'price',
        'capacity',
        'quantity',
        'is_active',
        'deposit_enabled',
        'deposit_percent',
        'meta',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'capacity' => 'integer',
        'quantity' => 'integer',
        'is_active' => 'boolean',
        'deposit_enabled' => 'boolean',
        'deposit_percent' => 'integer',
        'meta' => 'array',
    ];

    public function business()
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function service()
    {
        return $this->belongsTo(PlatformService::class, 'service_id');
    }

    public function bookings()
    {
        return $this->morphMany(Booking::class, 'bookable');
    }
}