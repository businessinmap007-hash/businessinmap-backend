<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A service a specialty clinic offers, with the clinic's own price (null = «السعر بعد الكشف»). */
class ClinicSpecialtyService extends Model
{
    protected $table = 'clinic_specialty_services';

    protected $fillable = ['clinic_id', 'specialty_service_id', 'price'];

    protected $casts = ['price' => 'float'];

    public function service(): BelongsTo
    {
        return $this->belongsTo(SpecialtyService::class, 'specialty_service_id');
    }
}
