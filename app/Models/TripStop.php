<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One published waypoint on a TripSchedule. See the create_trip_stops
 * migration for why there is no lat/lng.
 */
class TripStop extends Model
{
    protected $table = 'trip_stops';

    protected $fillable = [
        'trip_schedule_id',
        'business_id',
        'sequence',
        'label',
        'address',
        'lat',
        'lng',
    ];

    protected $casts = [
        'trip_schedule_id' => 'integer',
        'business_id' => 'integer',
        'sequence' => 'integer',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(TripSchedule::class, 'trip_schedule_id');
    }

    /** The registered business this stop points at, if picked rather than typed. */
    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }
}
