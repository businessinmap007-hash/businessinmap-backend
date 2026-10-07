<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A guest's request to the hotel during a running stay: a problem with the room (`issue`) or something they want
 * brought (`service`). Reaches the hotel carrying the room number.
 */
class StayRequest extends Model
{
    public const KIND_ISSUE = 'issue';

    public const KIND_SERVICE = 'service';

    public const STATUS_NEW = 'new';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DONE = 'done';

    public const STATUS_CANCELLED = 'cancelled';

    /** still waiting on the hotel */
    public const OPEN = [self::STATUS_NEW, self::STATUS_IN_PROGRESS];

    protected $table = 'stay_requests';

    protected $fillable = [
        'booking_id', 'business_id', 'user_id', 'room_id', 'room_number',
        'kind', 'category', 'title', 'note', 'status', 'handled_at',
    ];

    protected $casts = [
        'booking_id' => 'integer',
        'business_id' => 'integer',
        'user_id' => 'integer',
        'room_id' => 'integer',
        'handled_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }
}
