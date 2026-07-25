<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Thread extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_LOCKED = 'locked';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'title',
        'created_by',
        'status',
        'requires_conduct',
        'locked_at',
        'last_message_at',
        'retain_until',
    ];

    protected $casts = [
        'requires_conduct' => 'boolean',
        'locked_at' => 'datetime',
        'last_message_at' => 'datetime',
        'retain_until' => 'datetime',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ThreadParticipant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ThreadMessage::class);
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    public function requiresConduct(): bool
    {
        return (bool) $this->requires_conduct;
    }

    /** A group is a subjectless thread with an owner; a DM has neither. */
    public function isGroup(): bool
    {
        return $this->subject_type === null && $this->created_by !== null;
    }

    public function isOwnedBy(int $userId): bool
    {
        return (int) $this->created_by === $userId;
    }

    /**
     * The retention window has passed: the conversation is kept as evidence
     * until here, and is deletable afterwards.
     */
    public function isExpired(): bool
    {
        return $this->retain_until !== null && $this->retain_until->isPast();
    }

    /**
     * Nobody leaves a dispute room: the record is evidence, and a party who
     * could walk out could also make the conversation unreadable later.
     */
    public function participantFor(int $userId): ?ThreadParticipant
    {
        return $this->participants
            ->firstWhere(fn (ThreadParticipant $p) => (int) $p->user_id === $userId);
    }
}
