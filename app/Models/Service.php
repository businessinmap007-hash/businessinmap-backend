<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Service extends Model
{
    protected $table = 'services';

    protected $fillable = [
        'business_id',
        'name_ar',
        'name_en',
        'price',
        'duration',
        'description',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration' => 'int',
    ];

    public function business()
    {
        // business هو User.type=business
        return $this->belongsTo(User::class, 'business_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'service_id');
    }

    public function scopeForBusiness(Builder $q, int $businessId): Builder
    {
        return $q->where('business_id', $businessId);
    }

    public function getDisplayNameAttribute(): string
    {
        // عرض اسم الخدمة (مفيد في Admin)
        return (string)($this->name_ar ?: $this->name_en ?: ('Service #'.$this->id));
    }
}