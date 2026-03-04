<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{


    protected $fillable = ['percentage', 'times', 'expire_at', 'category', 'code','limit_months'];

    public function cat()
    {
        return $this->belongsTo(Category::class, 'category');
    }
}
