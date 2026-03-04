<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $fillable = [
     'type',
    'user_id',
    'expire_at',
    'is_active',

    'image',        // ✅ مهم
    'share_count',  // ✅ لو موجود بالجدول

    'title_ar',
    'title_en',
    'body_ar',
    'body_en',
    ];

    // ✅ نخلي appends للعنوان فقط (بدون body)
    protected $appends = ['title'];

    protected $casts = [
        'is_active' => 'boolean',
        'expire_at' => 'datetime',
    ];

    public function getTitleAttribute(): ?string
    {
        return app()->getLocale() === 'ar'
            ? ($this->attributes['title_ar'] ?? null)
            : ($this->attributes['title_en'] ?? null);
    }

    // ✅ رجع الـ body الخام من العمود (بدون ترجمة)
    public function getBodyAttribute($value): ?string
    {
        // $value هو قيمة عمود body من الداتابيز
        return $value;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ✅ صور متعددة من جدول images
    public function images()
    {
        return $this->morphMany(\App\Models\Image::class, 'imageable')
            ->orderBy('id', 'asc');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class)->where('parent_id', 0);
    }

    public function applies()
    {
        return $this->hasMany(Apply::class);
    }

    public function likes()
    {
        return $this->hasMany(Like::class)->where('like', 1);
    }

    public function dislikes()
    {
        return $this->hasMany(Like::class)->where('like', -1);
    }

    public function notifications()
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }
}
