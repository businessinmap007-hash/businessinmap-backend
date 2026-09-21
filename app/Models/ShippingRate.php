<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A shipping company's fixed price from its own governorate to one other governorate. */
class ShippingRate extends Model
{
    protected $fillable = ['company_id', 'to_governorate_id', 'price', 'days'];

    protected $casts = ['price' => 'float', 'days' => 'array'];
}
