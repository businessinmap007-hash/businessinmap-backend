<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OfferTrackingEvent extends Model
{
    public const EVENT_VIEW = 'view';
    public const EVENT_CLICK = 'click';
    public const EVENT_LEAD = 'lead';
    public const EVENT_CONVERSION = 'conversion';
    public const EVENT_SHARE = 'share';
    public const EVENT_SAVE = 'save';

    protected $table = 'offer_tracking_events';

    protected $fillable = [
        'offer_id',
        'user_id',
        'business_id',
        'event_type',
        'source',
        'session_id',
        'ip_address',
        'user_agent',
        'quantity',
        'value_amount',
        'currency',
        'reference_type',
        'reference_id',
        'occurred_at',
        'meta',
    ];

    protected $casts = [
        'offer_id' => 'integer',
        'user_id' => 'integer',
        'business_id' => 'integer',
        'quantity' => 'integer',
        'value_amount' => 'decimal:2',
        'occurred_at' => 'datetime',
        'meta' => 'array',
    ];

    public static function eventTypes(): array
    {
        return [
            self::EVENT_VIEW,
            self::EVENT_CLICK,
            self::EVENT_LEAD,
            self::EVENT_CONVERSION,
            self::EVENT_SHARE,
            self::EVENT_SAVE,
        ];
    }

    public function offer()
    {
        return $this->belongsTo(CommercialOffer::class, 'offer_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function business()
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function scopeForBusiness(Builder $query, int $businessId): Builder
    {
        return $query->where('business_id', $businessId);
    }

    public function scopeType(Builder $query, ?string $type): Builder
    {
        $type = trim((string) $type);

        if ($type === '') {
            return $query;
        }

        return $query->where('event_type', $type);
    }
}
