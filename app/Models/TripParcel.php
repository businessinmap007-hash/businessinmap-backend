<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A parcel (شحنة) that rides with the car on a trip leg. The sender — an app account, who booked it in the app —
 * describes it; the DRIVER prices it (and says whether it takes a seat: a bulky box may, a 100 kg one may not);
 * the sender accepts; the driver loads it and hands it over against a code the sender generated and gave the receiver
 * (4 digits + 2 letters). Without that code the driver cannot mark it delivered.
 */
class TripParcel extends Model
{
    protected $table = 'trip_parcels';

    public const STATUS_REQUESTED = 'requested';
    public const STATUS_PRICED = 'priced';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_LOADED = 'loaded';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    /** Statuses in which a parcel occupies seats of the leg (when the driver says it takes any). */
    public const SEAT_HOLDING = [self::STATUS_ACCEPTED, self::STATUS_LOADED, self::STATUS_DELIVERED];

    /** Wrong codes a driver may try before the parcel stops answering to him. */
    public const MAX_CODE_ATTEMPTS = 5;

    protected $fillable = [
        'trip_schedule_id',
        'business_id',
        'sender_id',
        'receiver_user_id',
        'receiver_name',
        'receiver_phone',
        'description',
        'price',
        'seats',
        'currency',
        'status',
        'delivery_code',
        'delivery_code_hash',
        'code_attempts',
        'loaded_at',
        'delivered_at',
        'delivered_by',
    ];

    protected $casts = [
        'trip_schedule_id' => 'integer',
        'business_id' => 'integer',
        'sender_id' => 'integer',
        'receiver_user_id' => 'integer',
        'price' => 'decimal:2',
        'seats' => 'integer',
        'code_attempts' => 'integer',
        'delivery_code' => 'encrypted',
        'loaded_at' => 'datetime',
        'delivered_at' => 'datetime',
        'delivered_by' => 'integer',
    ];

    protected $hidden = ['delivery_code', 'delivery_code_hash'];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(TripSchedule::class, 'trip_schedule_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_user_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }
}
