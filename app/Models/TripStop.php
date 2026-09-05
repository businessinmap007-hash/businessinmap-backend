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
        'sequence',
        'label',
        'address',
    ];

    protected $casts = [
        'trip_schedule_id' => 'integer',
        'sequence' => 'integer',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(TripSchedule::class, 'trip_schedule_id');
    }
}
