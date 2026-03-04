<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Bank extends Model
{

    public $translatedAttributes = ['name', 'account_name'];
    protected $fillable = ['name','iban_number', 'is_published'];


    public function scopeIsActive($query)
    {
        return $query->whereIsActive(1);
    }
}
