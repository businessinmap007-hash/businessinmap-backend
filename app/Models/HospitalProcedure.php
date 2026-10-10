<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A procedure a hospital offers, with the hospital's own price (null = «السعر بعد التقييم»). */
class HospitalProcedure extends Model
{
    protected $fillable = ['hospital_id', 'procedure_id', 'price', 'notes'];

    protected $casts = ['price' => 'float'];

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(MedicalProcedure::class, 'procedure_id');
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hospital_id');
    }
}
