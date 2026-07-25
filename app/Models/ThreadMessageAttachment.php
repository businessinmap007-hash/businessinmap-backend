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

    /**
     * The file is private (storage/, not public/), so both URLs point at an
     * AUTHENTICATED streaming route, not the file itself:
     *  - apiUrl: the app, as a party to the conversation (sanctum).
     *  - adminUrl: the admin/judge moderation screen (web session + DISPUTES).
     */
    public function apiUrl(): string
    {
        return url('api/v2/thread-attachments/' . $this->id);
    }

    public function adminUrl(): string
    {
        return url('admin/chat-attachments/' . $this->id);
    }
}
