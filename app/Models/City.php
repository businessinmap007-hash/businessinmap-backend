<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $fillable = [
        'country_id',
        'name_en',
        'name_ar',
        'latitude',
        'longitude',
        'population',
        'timezone',
        'city_code',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }
    public function governorate()
    {
        return $this->belongsTo(Governorate::class);
    }


    public function users()
    {
        return $this->hasMany(User::class);
    }
}
