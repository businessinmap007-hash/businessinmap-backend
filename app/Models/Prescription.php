<?php

namespace App\Models;

use App\Models\Concerns\HasOwnedImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A medical prescription (see the create_prescriptions migration). Doctor →
 * patient → (optionally) pharmacy. Readable only by those three parties.
 */
class Prescription extends Model
{
    use HasOwnedImages;

    /** A scan of the original paper prescription, or a doctor's supporting note. */
    public const MAX_IMAGES = 5;

    public const STATUS_ISSUED = 'issued';
    public const STATUS_SENT = 'sent_to_pharmacy';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_READY = 'ready';
    public const STATUS_DISPENSED = 'dispensed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_ISSUED,
        self::STATUS_SENT,
        self::STATUS_PREPARING,
        self::STATUS_READY,
        self::STATUS_DISPENSED,
        self::STATUS_CANCELLED,
    ];

    public const FULFILLMENT_DELIVERY = 'delivery';
    public const FULFILLMENT_PICKUP = 'pickup';

    public const FULFILLMENTS = [self::FULFILLMENT_DELIVERY, self::FULFILLMENT_PICKUP];

    /**
     * The health-root children that are an actual physician's practice —
     * مستشفى/عيادة/مركز طبي. NOT معمل تحاليل (163, tests) or صيدلية (215,
     * dispenses) or مراكز أشعة (252, imaging) or مركز حجامة (542, not a
     * doctor prescribing pharmaceutical drugs). Hardcoded ids, matching this
     * codebase's existing convention for a specific child
     * ({@see \App\Support\BusinessPanelNav::PHARMACY_CHILD_ID}) — only ROOTS
     * are looked up by slug here, because children keep their id.
     */
    public const DOCTOR_CHILD_IDS = [513, 514, 515];

    /** True for a business account that is actually a physician's practice. */
    public static function isDoctorBusiness(?User $user): bool
    {
        return $user
            && $user->isBusiness()
            && in_array((int) ($user->category_child_id ?? 0), self::DOCTOR_CHILD_IDS, true);
    }

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
        'delivery_address_id',
        'issued_at',
        'dispensed_at',
        'medicine_total',
        'priced_at',
        'delivery_driver_id',
        'delivery_stage',
        'pickup_token',
        'delivery_token',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'dispensed_at' => 'datetime',
        'medicine_total' => 'decimal:2',
        'priced_at' => 'datetime',
        'delivery_driver_id' => 'integer',
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

    /** The saved address-book entry the delivery was snapshotted from, if any. */
    public function deliveryAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'delivery_address_id');
    }

    /** The same driver pool menu orders use — a driver isn't order-specific. */
    public function deliveryDriver(): BelongsTo
    {
        return $this->belongsTo(DeliveryDriver::class, 'delivery_driver_id');
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
