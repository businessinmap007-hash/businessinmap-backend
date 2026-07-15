<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One published trip leg in the scheduling/routes service. See the
 * create_trip_schedules migration for the full domain notes.
 */
class TripSchedule extends Model
{
    protected $table = 'trip_schedules';

    // Modes — the primary sub-type; one entity, four businesses.
    public const MODE_FREIGHT = 'freight';          // شحن بضائع/طرود
    public const MODE_PASSENGER = 'passenger';      // نقل ركاب
    public const MODE_LIMOUSINE = 'limousine';      // ليموزين خاص
    public const MODE_DISTRIBUTION = 'distribution'; // توزيع مصانع/موزعين

    public const PATTERN_WEEKLY = 'weekly';
    public const PATTERN_ONE_OFF = 'one_off';
    public const PATTERN_ON_DEMAND = 'on_demand';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public const SCOPE_DOMESTIC = 'domestic';
    public const SCOPE_INTERNATIONAL = 'international';

    protected $fillable = [
        'business_id',
        'mode',
        'vehicle_type_id',
        'vehicle_label',
        'scope',
        'origin_country_id',
        'destination_country_id',
        'origin_governorate_id',
        'origin_city_id',
        'destination_governorate_id',
        'destination_city_id',
        'schedule_pattern',
        'day_of_week',
        'trip_date',
        'departure_time',
        'return_time',
        'capacity',
        'capacity_unit',
        'price',
        'currency',
        'is_return_leg',
        'parent_trip_id',
        'notes',
        'status',
        'meta',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'vehicle_type_id' => 'integer',
        'origin_country_id' => 'integer',
        'destination_country_id' => 'integer',
        'origin_governorate_id' => 'integer',
        'origin_city_id' => 'integer',
        'destination_governorate_id' => 'integer',
        'destination_city_id' => 'integer',
        'day_of_week' => 'integer',
        'trip_date' => 'date',
        'capacity' => 'integer',
        'price' => 'decimal:2',
        'is_return_leg' => 'boolean',
        'parent_trip_id' => 'integer',
        'meta' => 'array',
    ];

    public static function modes(): array
    {
        return [self::MODE_FREIGHT, self::MODE_PASSENGER, self::MODE_LIMOUSINE, self::MODE_DISTRIBUTION];
    }

    public static function patterns(): array
    {
        return [self::PATTERN_WEEKLY, self::PATTERN_ONE_OFF, self::PATTERN_ON_DEMAND];
    }

    public static function scopes(): array
    {
        return [self::SCOPE_DOMESTIC, self::SCOPE_INTERNATIONAL];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(PlatformServiceItemType::class, 'vehicle_type_id');
    }

    public function originCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'origin_country_id');
    }

    public function destinationCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'destination_country_id');
    }

    public function originGovernorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class, 'origin_governorate_id');
    }

    public function destinationGovernorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class, 'destination_governorate_id');
    }

    public function parentTrip(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_trip_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Schedules that serve a route on a given weekday: weekly rows on that day,
     * one-off rows dated to that day, and on-demand rows (always available).
     */
    public function scopeMatchingDay(Builder $query, int $dayOfWeek, ?string $date = null): Builder
    {
        return $query->where(function (Builder $q) use ($dayOfWeek, $date) {
            $q->where(function (Builder $w) use ($dayOfWeek) {
                $w->where('schedule_pattern', self::PATTERN_WEEKLY)
                    ->where('day_of_week', $dayOfWeek);
            })->orWhere('schedule_pattern', self::PATTERN_ON_DEMAND);

            if ($date !== null) {
                $q->orWhere(function (Builder $w) use ($date) {
                    $w->where('schedule_pattern', self::PATTERN_ONE_OFF)
                        ->whereDate('trip_date', $date);
                });
            }
        });
    }
}
