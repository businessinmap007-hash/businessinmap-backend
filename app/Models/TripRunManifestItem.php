<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One cargo line on a freight/distribution TripRun. See
 * create_trip_run_manifest_items migration for why remaining is computed,
 * not stored.
 */
class TripRunManifestItem extends Model
{
    protected $table = 'trip_run_manifest_items';

    protected $fillable = [
        'trip_run_id',
        'label',
        'unit',
        'assigned_qty',
        'delivered_qty',
        'returned_qty',
    ];

    protected $casts = [
        'trip_run_id' => 'integer',
        'assigned_qty' => 'integer',
        'delivered_qty' => 'integer',
        'returned_qty' => 'integer',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(TripRun::class, 'trip_run_id');
    }

    /** What's still in the vehicle — never stored, always derived. */
    public function remainingQty(): int
    {
        return max(0, (int) $this->assigned_qty - (int) ($this->delivered_qty ?? 0) - (int) ($this->returned_qty ?? 0));
    }
}
