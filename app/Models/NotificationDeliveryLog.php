<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationDeliveryLog extends Model
{
    public const CHANNEL_IN_APP = 'in_app';
    public const CHANNEL_REALTIME = 'realtime';
    public const CHANNEL_FIREBASE = 'firebase';

    public const STATUS_CREATED = 'created';
    public const STATUS_SENT = 'sent';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_FAILED = 'failed';

    protected $table = 'notification_delivery_logs';

    protected $fillable = [
        'notification_id',
        'user_id',
        'event_key',
        'channel',
        'status',
        'attempted_at',
        'delivered_at',
        'failed_reason',
        'provider_message_id',
        'meta',
    ];

    protected $casts = [
        'notification_id' => 'integer',
        'user_id' => 'integer',
        'attempted_at' => 'datetime',
        'delivered_at' => 'datetime',
        'meta' => 'array',
    ];

    public function notification()
    {
        return $this->belongsTo(AppNotification::class, 'notification_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
