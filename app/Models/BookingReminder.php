<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingReminder extends Model
{
    protected $table = 'booking_reminders';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FAILED = 'failed';

    public const CHANNEL_DATABASE = 'database';

    public const REMINDER_24H_CLIENT = 'booking.reminder_24h.client';
    public const REMINDER_24H_BUSINESS = 'booking.reminder_24h.business';
    public const REMINDER_1H_CLIENT = 'booking.reminder_1h.client';
    public const REMINDER_1H_BUSINESS = 'booking.reminder_1h.business';

    protected $fillable = [
        'booking_id',
        'service_event_id',
        'recipient_id',
        'reminder_key',
        'event_key',
        'scheduled_at',
        'sent_at',
        'status',
        'channel',
        'payload',
    ];

    protected $casts = [
        'booking_id' => 'integer',
        'service_event_id' => 'integer',
        'recipient_id' => 'integer',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'payload' => 'array',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id')->withTrashed();
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function serviceEvent(): BelongsTo
    {
        return $this->belongsTo(ServiceEvent::class, 'service_event_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query
            ->pending()
            ->where('scheduled_at', '<=', now());
    }

    public function scopeForBooking(Builder $query, int $bookingId): Builder
    {
        return $query->where('booking_id', $bookingId);
    }

    public function markSent(?int $serviceEventId = null): bool
    {
        $this->status = self::STATUS_SENT;
        $this->sent_at = now();

        if ($serviceEventId) {
            $this->service_event_id = $serviceEventId;
        }

        return $this->save();
    }

    public function markFailed(?string $message = null): bool
    {
        $payload = is_array($this->payload) ? $this->payload : [];

        if ($message) {
            $payload['_failure'] = [
                'message' => $message,
                'failed_at' => now()->toDateTimeString(),
            ];
        }

        $this->payload = $payload;
        $this->status = self::STATUS_FAILED;

        return $this->save();
    }

    public function cancel(): bool
    {
        $this->status = self::STATUS_CANCELLED;

        return $this->save();
    }
}