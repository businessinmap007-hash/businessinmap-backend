<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A user's switch for one notification category: off = silent (see App\Support\NotificationCategories). */
class NotificationPreference extends Model
{
    protected $fillable = ['user_id', 'category', 'enabled'];

    protected $casts = ['enabled' => 'boolean'];
}
