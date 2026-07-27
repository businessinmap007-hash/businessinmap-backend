<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One entry on a user's personal agenda (see the create migration). A blocking
 * entry holds a span and cannot overlap another blocking entry for the same user.
 */
class AgendaItem extends Model
{
    public const KIND_APPOINTMENT = 'appointment';
    public const KIND_BOOKING = 'booking';
    public const KIND_PERSONAL = 'personal';
    public const KIND_MEDICATION = 'medication';

    public const KINDS = [self::KIND_APPOINTMENT, self::KIND_BOOKING, self::KIND_PERSONAL, self::KIND_MEDICATION];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'kind',
        'title',
        'notes',
        'starts_at',
        'ends_at',
        'blocking',
        'status',
        'source_type',
        'source_id',
        'remind',
        'reminded_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'blocking' => 'boolean',
        'remind' => 'boolean',
        'reminded_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
