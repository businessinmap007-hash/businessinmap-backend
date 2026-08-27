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

    /** The user ids allowed to see this prescription. */
    public function partyIds(): array
    {
        return array_values(array_filter([
            (int) $this->doctor_id,
            (int) $this->patient_id,
            $this->pharmacy_id ? (int) $this->pharmacy_id : null,
        ]));
    }

    public function isParty(int $userId): bool
    {
        return in_array($userId, $this->partyIds(), true);
    }
}
