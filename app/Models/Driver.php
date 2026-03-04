<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $fillable = [
        'business_id',
        'status',
        'availability',
    ];

    public function business()
    {
        // نفترض إن Model البزنس هو User ونفرّق بالـ type في الـ app
        return $this->belongsTo(User::class, 'business_id');
    }

    public function car()
    {
        return $this->hasOne(Car::class);
    }

    public function location()
    {
        return $this->hasOne(DriverLocation::class);
    }

    public function rides()
    {
        return $this->hasMany(Ride::class);
    }
}
