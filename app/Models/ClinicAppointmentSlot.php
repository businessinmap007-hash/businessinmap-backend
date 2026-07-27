<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An open appointment slot a clinic published (see the create migration). Open
 * while it has no appointment yet and its start is still in the future.
 */
class ClinicAppointmentSlot extends Model
{
    protected $fillable = [
        'clinic_id',
        'appointment_id',
        'created_by',
        'starts_at',
        'duration_minutes',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'duration_minutes' => 'integer',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'clinic_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(ClinicAppointment::class, 'appointment_id');
    }

    public function isOpen(): bool
    {
        return $this->appointment_id === null && $this->starts_at && $this->starts_at->isFuture();
    }

    /** Open, still-future slots. */
    public function scopeOpen($query)
    {
        return $query->whereNull('appointment_id')->where('starts_at', '>', now());
    }
}
