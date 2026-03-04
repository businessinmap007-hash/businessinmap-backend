<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverLocation extends Model
{
    protected $fillable = [
        'driver_id',
        'lat',
        'lng',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}
