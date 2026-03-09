<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Booking extends Model
{
    use SoftDeletes;

    protected $table = 'bookings';

    protected $fillable = [
        'user_id',
        'business_id',
        'service_id',
        'date',
        'time',
        'price',
        'status',
        'notes',
        'starts_at',
        'ends_at',
        'duration_value',
        'duration_unit',
        'all_day',
        'timezone',
        'quantity',
        'party_size',
        'bookable_type',
        'bookable_id',
        'meta',
    ];

    protected $casts = [
        'date' => 'date',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'all_day' => 'boolean',
        'price' => 'decimal:2',
        'meta' => 'array',
    ];

    public const STATUS_PENDING     = 'pending';
    public const STATUS_ACCEPTED    = 'accepted';
    public const STATUS_REJECTED    = 'rejected';
    public const STATUS_CANCELLED   = 'cancelled';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING     => 'Pending',
            self::STATUS_ACCEPTED    => 'Accepted',
            self::STATUS_REJECTED    => 'Rejected',
            self::STATUS_CANCELLED   => 'Cancelled',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED   => 'Completed',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function business()
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    // ✅ مهم جدًا: الخدمة الآن من platform_services
    public function service()
    {
        return $this->belongsTo(PlatformService::class, 'service_id');
    }

    public function bookable()
    {
        return $this->morphTo();
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if (!$status) {
            return $query;
        }

        return $query->where('status', $status);
    }
}