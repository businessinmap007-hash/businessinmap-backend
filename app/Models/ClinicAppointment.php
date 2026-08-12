<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A patient↔clinic appointment (see the create migration). Readable only by the
 * clinic and the patient.
 */
class ClinicAppointment extends Model
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
        'patient_id',
        'service_price_id',
        'created_by',
        'scheduled_at',
        'duration_minutes',
        'status',
        'reason',
        'notes',
        'reminded_day_at',
        'reminded_soon_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'duration_minutes' => 'integer',
        'service_price_id' => 'integer',
        'reminded_day_at' => 'datetime',
        'reminded_soon_at' => 'datetime',
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
}
