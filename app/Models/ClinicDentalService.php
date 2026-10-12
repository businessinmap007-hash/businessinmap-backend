<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A dental service a clinic offers, with the clinic's own price (null = «السعر بعد الكشف»). */
class ClinicDentalService extends Model
{
    protected $fillable = ['clinic_id', 'dental_service_id', 'price'];

    protected $casts = ['price' => 'float'];

    public function service(): BelongsTo
    {
        return $this->belongsTo(DentalService::class, 'dental_service_id');
    }
}
