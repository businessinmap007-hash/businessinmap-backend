<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Car extends Model
{
    protected $fillable = [
        'driver_id',   // user_id للسائق
        'car_type',
        'car_model',
        'car_number',
        'color',
        'year',
        'image',
    ];

    // العلاقة مع المستخدم (السائق)
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
