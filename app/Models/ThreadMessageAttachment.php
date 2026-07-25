<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One file attached to a thread message (evidence in a dispute room).
 *
 * Stores a public-relative path; `url()` turns it into an absolute URL at read
 * time via asset(), so it is correct whatever host serves the request rather
 * than being frozen to whatever APP_URL was when it was uploaded.
 */
class ThreadMessageAttachment extends Model
{
    protected $fillable = [
        'thread_message_id',
        'path',
        'original_name',
        'mime',
        'size',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(ThreadMessage::class, 'thread_message_id');
    }

    public function url(): string
    {
        return asset((string) $this->path);
    }
}
