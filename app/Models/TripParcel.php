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
    /** Still on the car when the run ended: not handed over. */
    public const STATUS_RETURNED = 'returned';

    /** Rating-ledger / dispute operation type of a parcel (a trip reservation is `trip`). */
    public const OP_PARCEL = 'trip_parcel';

    /** Who pays the driver, in cash: the sender at pickup, the receiver at delivery. */
    public const PAYER_SENDER = 'sender';
    public const PAYER_RECEIVER = 'receiver';
    public const PAYERS = [self::PAYER_SENDER, self::PAYER_RECEIVER];

    /** Statuses in which a parcel occupies seats of the leg (when the driver says it takes any). */
    public const SEAT_HOLDING = [self::STATUS_ACCEPTED, self::STATUS_LOADED, self::STATUS_DELIVERED];

    /** Wrong codes a driver may try before the parcel stops answering to him. */
    public const MAX_CODE_ATTEMPTS = 5;

    protected $fillable = [
        'trip_schedule_id',
        'trip_run_id',
        'payer',
        'paid_at',
        'expected_arrival_at',
        'pickup_code',
        'pickup_code_hash',
        'pickup_attempts',
        'picked_up_at',
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
        'sealed_ack_at',
        'seal_number',
        'seal_photo',
        'seal_photo_at',
        'refused_unsealed_at',
        'seal_state',
        'seal_checked_at',
    ];

    public const SEAL_INTACT = 'intact';
    public const SEAL_BROKEN = 'broken';

    protected $casts = [
        'trip_schedule_id' => 'integer',
        'business_id' => 'integer',
        'sender_id' => 'integer',
        'receiver_user_id' => 'integer',
        'price' => 'decimal:2',
        'seats' => 'integer',
        'code_attempts' => 'integer',
        'delivery_code' => 'encrypted',
        'pickup_code' => 'encrypted',
        'pickup_attempts' => 'integer',
        'sealed_ack_at' => 'datetime',
        'seal_photo_at' => 'datetime',
        'refused_unsealed_at' => 'datetime',
        'seal_checked_at' => 'datetime',
        'paid_at' => 'datetime',
        'expected_arrival_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'loaded_at' => 'datetime',
        'delivered_at' => 'datetime',
        'delivered_by' => 'integer',
    ];

    protected $hidden = ['delivery_code', 'delivery_code_hash', 'pickup_code', 'pickup_code_hash'];

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

    /** Sent in a sealed bag (every parcel asked for since the rule; older ones were not). */
    public function isSealed(): bool
    {
        return $this->sealed_ack_at !== null;
    }
}
