<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One live execution of a TripSchedule. See the create_trip_runs migration
 * for the domain notes and App\Services\Schedules\TripRunService for the
 * state machine.
 */
class TripRun extends Model
{
    protected $table = 'trip_runs';

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_AWAITING_RECONCILIATION = 'awaiting_reconciliation';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'trip_schedule_id',
        'business_id',
        'started_by',
        'status',
        'passenger_count',
        'started_at',
        'completed_at',
        'notes',
        'meta',
    ];

    protected $casts = [
        'trip_schedule_id' => 'integer',
        'business_id' => 'integer',
        'started_by' => 'integer',
        'passenger_count' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'meta' => 'array',
    ];

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_IN_PROGRESS => 'الرحلة جارية',
            self::STATUS_AWAITING_RECONCILIATION => 'بانتظار تسوية المنتجات',
            self::STATUS_COMPLETED => 'اكتملت المهمة',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(TripSchedule::class, 'trip_schedule_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function stops(): HasMany
    {
        return $this->hasMany(TripRunStop::class, 'trip_run_id')->orderBy('sequence');
    }

    public function manifestItems(): HasMany
    {
        return $this->hasMany(TripRunManifestItem::class, 'trip_run_id');
    }

    /** The one stop currently `heading` or `arrived` — never more than one. */
    public function currentStop(): ?TripRunStop
    {
        return $this->stops
            ->first(fn (TripRunStop $s) => in_array($s->status, [TripRunStop::STATUS_HEADING, TripRunStop::STATUS_ARRIVED], true));
    }
}
