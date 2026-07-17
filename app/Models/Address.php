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

    /*
     * These pointed at Location — the tree whose 71 rows are all countries with
     * empty names and which has no governorate or city rows at all. So
     * $address->governorate resolved against the wrong table entirely, and
     * $address->city could only ever be null.
     *
     * The live tables are countries / governorates / cities: the v1 pickers, the
     * scheduling service and the BIM-3.5 fee-rule admin all read them, and
     * ServiceFeeRuleEngine compares addresses.governorate_id against ids that
     * came from `governorates`. Repointing here is what makes those agree.
     *
     * Safe to change outright: the addresses table had zero rows, because
     * neither writer could ever produce one.
     */
    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function governorate()
    {
        return $this->belongsTo(Governorate::class, 'governorate_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }
}
