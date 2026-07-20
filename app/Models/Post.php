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

    'title', // one column, not a title_ar/title_en pair — a post is written once, in the author's own language (see 2026_07_20_120000)
    'body', // the real column (unified from body_ar/body_en, see 2026_02_16_180855) — body_ar/body_en no longer exist; listing them here silently dropped every body/description ever mass-assigned (AdminV2\JobPostController::store/update included)

    // Job fields (type='job' only — see 2026_08_08_000000_add_job_fields_to_posts).
    'category_id',
    'category_child_id',
    'salary',
    'requirements',
    'interview_starts_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expire_at' => 'datetime',
        'interview_starts_at' => 'datetime',
    ];

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

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function categoryChild()
    {
        return $this->belongsTo(CategoryChild::class, 'category_child_id');
    }

    public function scopeJobs($query)
    {
        return $query->where('type', 'job');
    }

    public function scopeOpenJobs($query)
    {
        return $query->jobs()->where('is_active', 1)
            ->where(function ($w) {
                $w->whereNull('expire_at')->orWhere('expire_at', '>=', now());
            });
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
