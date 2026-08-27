<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A medical prescription (see the create_prescriptions migration). Doctor →
 * patient → (optionally) pharmacy. Readable only by those three parties.
 */
class Prescription extends Model
{
    public const STATUS_ISSUED = 'issued';
    public const STATUS_SENT = 'sent_to_pharmacy';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_READY = 'ready';
    public const STATUS_DISPENSED = 'dispensed';
    public const STATUS_CANCELLED = 'cancelled';

    public const FULFILLMENT_DELIVERY = 'delivery';
    public const FULFILLMENT_PICKUP = 'pickup';

    public const FULFILLMENTS = [self::FULFILLMENT_DELIVERY, self::FULFILLMENT_PICKUP];

    protected $fillable = [
        'doctor_id',
        'patient_id',
        'appointment_id',
        'revises_prescription_id',
        'pharmacy_id',
        'status',
        'fulfillment_type',
        'diagnosis',
        'patient_condition',
        'notes',
        'delivery_address',
        'issued_at',
        'dispensed_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'dispensed_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pharmacy_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(ClinicAppointment::class, 'appointment_id');
    }

    /** The older prescription this one amends, if it is a revision. */
    public function revises(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revises_prescription_id');
    }

    /** The newer prescription that replaced this one, if any. */
    public function revisedBy(): HasMany
    {
        return $this->hasMany(self::class, 'revises_prescription_id');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(PrescriptionShare::class);
    }

    /** Doctors a patient or the original doctor shared read access with. */
    public function sharedDoctorIds(): array
    {
        return $this->shares->pluck('doctor_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * The user ids allowed to READ this prescription — the three original
     * parties plus any doctor it was explicitly shared with. Read access
     * only: shared-in doctors can never amend (see isOriginalDoctor()).
     */
    public function partyIds(): array
    {
        return array_values(array_unique(array_merge(
            array_filter([
                (int) $this->doctor_id,
                (int) $this->patient_id,
                $this->pharmacy_id ? (int) $this->pharmacy_id : null,
            ]),
            $this->sharedDoctorIds()
        )));
    }

    public function isParty(int $userId): bool
    {
        return in_array($userId, $this->partyIds(), true);
    }

    /**
     * A shared-in second doctor may read but never amend — only the doctor
     * who actually wrote it may («يقدر يتصرف؟ لا، الطبيب الاصلى فقط»).
     */
    public function isOriginalDoctor(int $userId): bool
    {
        return (int) $this->doctor_id === $userId;
    }
}
