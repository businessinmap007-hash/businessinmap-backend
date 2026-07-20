<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    public const CHANNEL_IN_APP = 'in_app';
    public const CHANNEL_PUSH = 'push';
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';

    public const TYPE_OFFER = 'offer';
    public const TYPE_BOOKING = 'booking';
    public const TYPE_WALLET = 'wallet';
    public const TYPE_GUARANTEE = 'guarantee';
    public const TYPE_DISPUTE = 'dispute';
    public const TYPE_MESSAGE = 'message';
    public const TYPE_SYSTEM = 'system';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public const STATUS_UNREAD = 'unread';
    public const STATUS_READ = 'read';
    public const STATUS_ARCHIVED = 'archived';

    protected $table = 'app_notifications';

    protected $fillable = [
        'user_id',
        'actor_id',
        'type',
        'channel',
        'priority',
        'title_ar',
        'title_en',
        'body_ar',
        'body_en',
        'action_type',
        'action_url',
        'notifiable_type',
        'notifiable_id',
        'source_type',
        'source_id',
        'status',
        'read_at',
        'archived_at',
        'delivered_at',
        'expires_at',
        'meta',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'actor_id' => 'integer',
        'notifiable_id' => 'integer',
        'source_id' => 'integer',
        'read_at' => 'datetime',
        'archived_at' => 'datetime',
        'delivered_at' => 'datetime',
        'expires_at' => 'datetime',
        'meta' => 'array',
    ];

    public static function types(): array
    {
        return [
            self::TYPE_OFFER,
            self::TYPE_BOOKING,
            self::TYPE_WALLET,
            self::TYPE_GUARANTEE,
            self::TYPE_DISPUTE,
            self::TYPE_SYSTEM,
        ];
    }

    public static function statuses(): array
    {
        return [self::STATUS_UNREAD, self::STATUS_READ, self::STATUS_ARCHIVED];
    }

    public static function priorities(): array
    {
        return [self::PRIORITY_LOW, self::PRIORITY_NORMAL, self::PRIORITY_HIGH, self::PRIORITY_URGENT];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_UNREAD);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
        });
    }

    public function displayTitle(): string
    {
        return $this->title_ar ?: ($this->title_en ?: 'Notification');
    }

    public function displayBody(): string
    {
        return $this->body_ar ?: ($this->body_en ?: '');
    }
}
