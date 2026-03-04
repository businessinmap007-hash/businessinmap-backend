<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $table = 'locations';

    protected $fillable = [
        'parent_id',
        'type',        // country | governorate | city
        'name_ar',
        'name_en',
        'lat',
        'lng',
    ];

    /* =====================
     | Relationships
     ===================== */

    public function parent()
    {
        return $this->belongsTo(Location::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Location::class, 'parent_id');
    }

    /* =====================
     | Scopes
     ===================== */

    public function scopeCountry($query)
    {
        return $query->where('type', 'country');
    }

    public function scopeGovernorate($query)
    {
        return $query->where('type', 'governorate');
    }

    public function scopeCity($query)
    {
        return $query->where('type', 'city');
    }
}
