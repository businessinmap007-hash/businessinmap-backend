<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model

{
    protected $fillable = [
        'user_id',
        'title',
        'body',
        'notifiable_id',
        'notifiable_type',
        'created_by',
        'read_at'
    ];

    protected $dates = ['read_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function notifiable()
    {
        return $this->morphTo();
    }

    

}
