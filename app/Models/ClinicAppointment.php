<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A patient↔clinic appointment (see the create migration). Readable only by the
 * clinic and the patient.
 */
class ClinicAppointment extends Model implements \App\Contracts\HasVisitDeposit
{
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';

    public const STATUSES = [
        self::STATUS_REQUESTED,
        self::STATUS_CONFIRMED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
        self::STATUS_NO_SHOW,
    ];

    /** Statuses that still occupy the calendar (block an overlapping slot). */
    public const ACTIVE_STATUSES = [self::STATUS_REQUESTED, self::STATUS_CONFIRMED];

    protected $fillable = [
        'clinic_id',
        'physician_id',
        'patient_id',
        'service_price_id',
        'visit_fee',
        'installment_amount',
        'created_by',
        'scheduled_at',
        'duration_minutes',
        'status',
        'reason',
        'attendee_name',
        'attendee_phone',
        'notes',
        'reminded_day_at',
        'reminded_soon_at',
        'is_walk_in',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'duration_minutes' => 'integer',
        'service_price_id' => 'integer',
        'visit_fee' => 'float',
        'installment_amount' => 'float',
        'reminded_day_at' => 'datetime',
        'reminded_soon_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'is_walk_in' => 'boolean',
        'queue_priority_override' => 'integer',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'clinic_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    /**
     * The kind of visit — «كشف», «استشارة» — as the clinic priced it. The
     * appointment's LENGTH is inherited from here when it is set, which is how
     * a clinic says «كشف ٣٠ دقيقة، استشارة ٢٠» once instead of on every slot.
     */
    public function servicePrice(): BelongsTo
    {
        return $this->belongsTo(BusinessServicePrice::class, 'service_price_id');
    }

    /** The services the patient named when booking (dental, dermatology…), in the order he named them. */
    public function items(): HasMany
    {
        return $this->hasMany(ClinicAppointmentItem::class, 'clinic_appointment_id')->orderBy('sort_order')->orderBy('id');
    }

    public function visitDepositClientId(): int
    {
        return (int) $this->patient_id;
    }

    public function visitDepositBusinessId(): int
    {
        return (int) $this->clinic_id;
    }

    /** The visit's fee plus the services named — what the patient is expected to pay here. */
    public function visitDepositValue(): float
    {
        return round((float) $this->visit_fee + (float) $this->items()->sum('price'), 2);
    }

    public function visitDepositOpen(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    /** What the clinic recorded as paid against this visit, in the order paid. */
    public function payments(): HasMany
    {
        return $this->hasMany(ClinicAppointmentPayment::class, 'clinic_appointment_id')->orderBy('paid_at')->orderBy('id');
    }

    /**
     * The booked services as both sides read them: the live (localized) name while the service exists, the name kept at
     * booking once it is gone; the price is always the one asked. An empty list when none was named.
     *
     * @return list<array{id:?int,name:string,price:?float}>
     */
    public function servicesSummary(): array
    {
        return $this->items->map(fn (ClinicAppointmentItem $i) => [
            'id' => $i->specialty_service_id ? (int) $i->specialty_service_id : null,
            'name' => $i->service?->label() ?: (string) $i->name,
            'price' => $i->price,
        ])->values()->all();
    }

    /** The prescription written during this visit, if any. */
    public function prescription(): HasOne
    {
        return $this->hasOne(Prescription::class, 'appointment_id');
    }

    public function endsAt()
    {
        return $this->scheduled_at?->copy()->addMinutes((int) $this->duration_minutes);
    }

    public function isParty(int $userId): bool
    {
        return in_array($userId, [(int) $this->clinic_id, (int) $this->patient_id], true);
    }

    /**
     * The visit's kind for queue-pattern purposes — booking_examination,
     * booking_consultation, … — from what the clinic priced this visit as.
     * A walk-in with no priced visit type defaults to a plain كشف.
     */
    public function visitKind(): string
    {
        return $this->servicePrice?->bookable_item_type ?: 'booking_examination';
    }
}
