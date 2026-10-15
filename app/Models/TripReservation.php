<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reservation of capacity on a trip leg. See the create_trip_reservations
 * migration for the domain notes and lifecycle.
 */
class TripReservation extends Model implements \App\Contracts\HasVisitDeposit, \App\Contracts\HasOwnDepositTerms
{
    protected $table = 'trip_reservations';

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_BLOCKED = 'blocked'; // carrier's offline seat hold
    public const STATUS_NO_SHOW = 'no_show';  // the customer did not turn up for a confirmed reservation

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
            self::STATUS_NO_SHOW => 'لم يحضر',
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

    // ── the deposit core's view of a reservation ──────────────────────────────────────────────────────────────

    public function visitDepositClientId(): int
    {
        return (int) $this->client_id;
    }

    public function visitDepositBusinessId(): int
    {
        return (int) $this->business_id;
    }

    public function visitDepositValue(): float
    {
        return round((float) $this->total_price, 2);
    }

    /** Placed and not yet over (an offline hold has no customer, so never carries a deposit). */
    public function visitDepositOpen(): bool
    {
        return $this->client_id !== null
            && in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED], true);
    }

    /**
     * The carrier's own deposit per unit stands for the WHOLE of it (the customer's guarantee first, his wallet for the
     * rest, frozen when the carrier confirms). A carrier that set none falls back to its «شروط الحجز» on the
     * reservation's value.
     *
     * @return array<string,mixed>|null
     */
    public function depositTerms(): ?array
    {
        $svc = app(\App\Services\VisitDepositService::class);
        $perUnit = round((float) ($this->schedule?->deposit_per_unit ?? 0), 2);

        if ($perUnit > 0) {
            return $svc->wholeCoverPolicy((int) $this->business_id, $perUnit * max(1, (int) $this->units), 'trip_deposit');
        }

        return $svc->termsFor((int) $this->business_id, (float) $this->total_price);
    }

    public function scopeHoldingCapacity(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }
}
