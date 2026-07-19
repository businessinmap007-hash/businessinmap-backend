<?php

namespace App\Models;

use App\Support\Concerns\HasLocalizedFields;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasLocalizedFields;

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
