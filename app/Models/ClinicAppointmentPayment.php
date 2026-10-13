<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One amount a clinic recorded as paid against a visit (the visit fee, or a service — possibly in instalments). */
class ClinicAppointmentPayment extends Model
{
    public const KIND_VISIT_FEE = 'visit_fee';
    public const KIND_SERVICE = 'service';
    public const KINDS = [self::KIND_VISIT_FEE, self::KIND_SERVICE];

    protected $table = 'clinic_appointment_payments';

    protected $fillable = [
        'clinic_appointment_id', 'amount', 'kind', 'note', 'recorded_by', 'paid_at', 'patient_confirmed_at', 'disputed_at', 'dispute_note',
    ];

    protected $casts = ['amount' => 'float', 'paid_at' => 'datetime', 'patient_confirmed_at' => 'datetime', 'disputed_at' => 'datetime'];

    /** pending (the patient has not answered) | confirmed | disputed */
    public function confirmation(): string
    {
        return $this->patient_confirmed_at ? 'confirmed' : ($this->disputed_at ? 'disputed' : 'pending');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(ClinicAppointment::class, 'clinic_appointment_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
