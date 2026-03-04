<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $fillable = [
        'user_id',
        'location_id',     // قديم
        'country_id',
        'governorate_id',
        'city_id',
        'zip_code',
        'address_line',
        'lat',
        'lng',
        'latitude',        // قديم
        'longitude',       // قديم
        'is_primary',
    ];

    public function country()
    {
        return $this->belongsTo(Location::class, 'country_id');
    }

    public function governorate()
    {
        return $this->belongsTo(Location::class, 'governorate_id');
    }

    public function city()
    {
        return $this->belongsTo(Location::class, 'city_id');
    }
}
