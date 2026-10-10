<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A patient's request for a procedure at a hospital. The name and the price are kept as they were when the patient
 * asked — a later price change must not rewrite a request that is already being worked.
 */
class ProcedureRequest extends Model
{
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'patient_id', 'hospital_id', 'procedure_id', 'kind', 'name', 'price', 'preferred_date', 'notes',
        'status', 'scheduled_at', 'hospital_note',
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

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hospital_id');
    }
}
