<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisputeRuleVersion extends Model
{
    protected $fillable = [
        'version',
        'title',
        'sections',
        'published_by',
        'published_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'sections' => 'array',
        'published_at' => 'datetime',
    ];

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /** The version in force: the highest published one, or null before any. */
    public static function active(): ?self
    {
        return static::query()
            ->whereNotNull('published_at')
            ->orderByDesc('version')
            ->first();
    }
}
