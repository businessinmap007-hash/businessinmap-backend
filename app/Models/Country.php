<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class Country extends Model
{
    protected $fillable = [
        'name_en',
        'name_ar',
        'iso2',
        'iso3',
        'phone_code',
        'currency',
        'flag',
    ];

    public function governorates()
    {
        return $this->hasMany(Governorate::class);
    }
}
