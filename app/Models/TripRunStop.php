<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A snapshot of one TripStop for one live TripRun. See
 * create_trip_run_stops migration for the state machine notes.
 */
class TripRunStop extends Model
{
    protected $table = 'trip_run_stops';

    public const STATUS_PENDING = 'pending';
    public const STATUS_HEADING = 'heading';
    public const STATUS_ARRIVED = 'arrived';
    public const STATUS_DONE = 'done';

    protected $fillable = [
        'trip_run_id',
        'trip_stop_id',
        'sequence',
        'label',
        'address',
        'lat',
        'lng',
        'status',
        'arrived_at',
        'completed_at',
    ];

    protected $casts = [
        'trip_run_id' => 'integer',
        'trip_stop_id' => 'integer',
        'sequence' => 'integer',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'arrived_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(TripRun::class, 'trip_run_id');
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'لم يبدأ بعد',
            self::STATUS_HEADING => 'متجه إليها',
            self::STATUS_ARRIVED => 'تم الوصول',
            self::STATUS_DONE => 'مكتملة',
        ];
    }
}
