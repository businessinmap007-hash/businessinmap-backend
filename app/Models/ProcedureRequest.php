<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A patient's request for a procedure at a hospital. The name and the price are kept as they were when the patient
 * asked — a later price change must not rewrite a request that is already being worked.
 */
class ProcedureRequest extends Model implements \App\Contracts\HasVisitDeposit
{
    /** The physician decided the patient needs it; the patient has not answered yet. */
    public const STATUS_ADVISED = 'advised';
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'patient_id', 'hospital_id', 'procedure_id', 'kind', 'name', 'price', 'preferred_date', 'notes',
        'status', 'scheduled_at', 'hospital_note', 'physician_id', 'appointment_id', 'source',
    ];

    protected $casts = [
        'price' => 'float',
        'preferred_date' => 'date',
        'scheduled_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function visitDepositClientId(): int
    {
        return (int) $this->patient_id;
    }

    public function visitDepositBusinessId(): int
    {
        return (int) $this->hospital_id;
    }

    public function visitDepositValue(): float
    {
        return round((float) $this->price, 2);
    }

    public function visitDepositOpen(): bool
    {
        return in_array($this->status, [self::STATUS_REQUESTED, self::STATUS_ACCEPTED], true);
    }

    /** The treating physician who issued the decision (null when the patient asked on his own). */
    public function physician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'physician_id');
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hospital_id');
    }
}
