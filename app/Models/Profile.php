<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;

class Profile extends Model implements TranslatableContract
{
    use Translatable;
    protected $dates =  ['passport_issuance_date','passport_expired_date'];
    protected $fillable = [
        'ssn_no','job','nationality','passport_no','visa_no','passport_issuance_date','passport_expired_date','passport_issuance_location'
    ];
}
