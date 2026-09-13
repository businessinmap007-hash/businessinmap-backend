<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class BusinessGift extends Model
{
    protected $fillable = ['commission_months', 'limit_months', 'free_months'];


    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
