<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reservation of capacity on a trip leg. See the create_trip_reservations
 * migration for the domain notes and lifecycle.
 */
class TripReservation extends Model
{
    protected $table = 'trip_reservations';

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_BLOCKED = 'blocked'; // carrier's offline seat hold

    // Where the reservation came from.
    public const SOURCE_APP = 'app';
    public const SOURCE_OFFLINE = 'offline';

    /** Statuses that hold capacity against the leg. */
    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_COMPLETED,
        self::STATUS_BLOCKED,
    ];

    /** Rating operation type — folds trips into the universal reputation. */
    public const OP_TRIP = 'trip';

    /**
     * Arabic labels, held here so the carrier's panel and the admin's oversight
     * can never describe the same status differently.
     *
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'بانتظار التأكيد',
            self::STATUS_CONFIRMED => 'مؤكد',
            self::STATUS_COMPLETED => 'مكتمل',
            self::STATUS_CANCELLED => 'ملغي',
            self::STATUS_BLOCKED => 'حجز يدوي',
        ];
    }

    /** @return array<string, string> */
    public static function sourceLabels(): array
    {
        return [
            self::SOURCE_APP => 'التطبيق',
            self::SOURCE_OFFLINE => 'خارج التطبيق',
        ];
    }

    protected $fillable = [
        'trip_schedule_id',
        'business_id',
        'client_id',
        'units',
        'unit_price',
        'total_price',
        'deposit_held',
        'currency',
        'source',
        'status',
        'notes',
        'meta',
    ];

    protected $casts = [
        'trip_schedule_id' => 'integer',
        'business_id' => 'integer',
        'client_id' => 'integer',
        'units' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'deposit_held' => 'decimal:2',
        'meta' => 'array',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(TripSchedule::class, 'trip_schedule_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function scopeHoldingCapacity(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }
}
