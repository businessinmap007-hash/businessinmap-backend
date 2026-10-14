<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvestigationPackageItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['package_id', 'option_id'];
}
