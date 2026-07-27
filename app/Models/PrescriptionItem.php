<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionItem extends Model
{
    protected $fillable = [
        'prescription_id',
        'name',
        'dosage',
        'quantity',
        'instructions',
        'frequency_per_day',
        'food_timing',
        'time_slots',
        'duration_days',
    ];

    protected $casts = [
        'frequency_per_day' => 'integer',
        'time_slots' => 'array',
        'duration_days' => 'integer',
    ];

    public const FOOD_TIMINGS = ['before', 'with', 'after'];
    public const SLOTS = ['breakfast', 'lunch', 'dinner', 'morning', 'evening'];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }
}
