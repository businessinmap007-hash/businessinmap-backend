<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One service a patient named when booking a clinic visit. The name (the platform's Arabic wording) and the price are
 * kept as they were when asked; `specialty_service_id` is null once the service itself is gone.
 */
class ClinicAppointmentItem extends Model
{
    protected $table = 'clinic_appointment_items';

    protected $fillable = ['clinic_appointment_id', 'specialty_service_id', 'name', 'price', 'final_price', 'sort_order'];

    protected $casts = ['price' => 'float', 'final_price' => 'float'];

    public function service(): BelongsTo
    {
        return $this->belongsTo(SpecialtyService::class, 'specialty_service_id');
    }
}
