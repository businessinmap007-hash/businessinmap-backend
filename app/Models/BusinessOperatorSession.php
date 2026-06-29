<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BusinessOperatorSession extends Model
{
    public const STATUS_ONLINE = 'online';
    public const STATUS_OFFLINE = 'offline';

    protected $table = 'business_operator_sessions';

    protected $fillable = [
        'business_id',
        'user_id',
        'service_type',
        'screen',
        'status',
        'started_at',
        'expected_until',
        'ended_at',
        'last_activity_at',
        'meta',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'user_id' => 'integer',
        'started_at' => 'datetime',
        'expected_until' => 'datetime',
        'ended_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'meta' => 'array',
    ];

    public function business()
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeOnline(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ONLINE)
            ->whereNull('ended_at')
            ->where(function (Builder $q) {
                $q->whereNull('expected_until')->orWhere('expected_until', '>=', now());
            });
    }

    public static function hasActiveSession(int $businessId, ?string $serviceType = null): bool
    {
        return self::query()
            ->online()
            ->where('business_id', $businessId)
            ->when($serviceType, fn (Builder $q) => $q->where('service_type', $serviceType))
            ->exists();
    }
}
