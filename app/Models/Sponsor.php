<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sponsor extends Model
{
    protected $fillable = [
        'user_id',
        'image',
        'price',
        'expire_at',
        'type',
        'activated_at',
    ];

    protected $casts = [
        'expire_at'    => 'datetime',
        'activated_at' => 'datetime',
    ];

    /* ================= Relations ================= */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }
}
