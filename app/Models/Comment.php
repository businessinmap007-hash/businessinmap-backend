<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    protected $fillable = [
        'post_id', 'comment', 'status', 'parent_id', 'user_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function post()
    {
        return $this->belongsTo(Post::class);
    }


    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }


    public function children()
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }


    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function notifications()
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }






}
