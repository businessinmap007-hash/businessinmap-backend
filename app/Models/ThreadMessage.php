<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThreadMessage extends Model
{
    public const KIND_MESSAGE = 'message';
    public const KIND_SYSTEM = 'system';

    protected $fillable = [
        'thread_id',
        'sender_id',
        'kind',
        'body',
    ];

    /**
     * Conversation text is encrypted at rest: a database dump never reveals
     * what people said. It decrypts transparently on read (for the parties'
     * own API, and the admin/judge moderation screen), and the DB column holds
     * only ciphertext. Rotating APP_KEY makes every stored body unreadable —
     * back it up like the secret it now protects.
     */
    protected $casts = [
        'body' => 'encrypted',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ThreadMessageAttachment::class, 'thread_message_id');
    }

    public function isSystem(): bool
    {
        return $this->kind === self::KIND_SYSTEM;
    }
}
