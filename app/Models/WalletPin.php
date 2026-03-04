<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletPin extends Model
{
    protected $fillable = ['user_id', 'pin_hash', 'attempts', 'locked_until'];

    protected $casts = [
        'locked_until' => 'datetime',
    ];
}
