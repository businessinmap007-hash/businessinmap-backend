<?php

namespace App\Models;

use App\Models\User;
use App\Support\Concerns\HasLocalizedFields;
use Illuminate\Database\Eloquent\Model;

class Album extends Model
{
    use HasLocalizedFields;

    protected $fillable = [
        'image',
        'title_ar',
        'title_en',
        'description_ar',
        'description_en',
    ];

    protected $appends = ['title', 'description'];

    /**
     * Falls back to whichever language IS filled in (see HasLocalizedFields)
     * instead of going blank for the other one — almost every business only
     * ever fills in title_ar, so a strict locale-only pick (the original
     * shape here) meant an English-locale viewer saw no title at all, not
     * the Arabic one it does have. Caught building the public album view
     * (2026-09-04); the same gap existed on the owner's own management
     * screen too.
     */
    public function getTitleAttribute(): ?string
    {
        return $this->loc('title');
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->loc('description');
    }

    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
