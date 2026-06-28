<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OfferFollowNotification extends Model
{
    public const STATUS_UNREAD = 'unread';
    public const STATUS_READ = 'read';
    public const STATUS_ARCHIVED = 'archived';

    protected $table = 'offer_follow_notifications';

    protected $fillable = [
        'user_id',
        'follow_id',
        'offer_id',
        'match_type',
        'match_score',
        'status',
        'read_at',
        'meta',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'follow_id' => 'integer',
        'offer_id' => 'integer',
        'match_score' => 'decimal:4',
        'read_at' => 'datetime',
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function follow()
    {
        return $this->belongsTo(OfferFollow::class, 'follow_id');
    }

    public function offer()
    {
        return $this->belongsTo(CommercialOffer::class, 'offer_id');
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_UNREAD);
    }
}
