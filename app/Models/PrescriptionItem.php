<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionItem extends Model
{
    protected $fillable = [
        'prescription_id',
        'medicine_id',
        'name',
        'dosage',
        'quantity',
        'instructions',
        'frequency_per_day',
        'food_timing',
        'time_slots',
        'duration_days',
        'duration_unit',
        'duration_value',
    ];

    protected $casts = [
        'medicine_id' => 'integer',
        'frequency_per_day' => 'integer',
        'time_slots' => 'array',
        'duration_days' => 'integer',
        'duration_value' => 'integer',
    ];

    public const FOOD_TIMINGS = ['before', 'with', 'after'];
    public const SLOTS = ['breakfast', 'lunch', 'dinner', 'morning', 'evening'];

    /** How many days one unit of duration actually is — the scheduler only ever reads duration_days. */
    public const DURATION_UNIT_DAYS = ['days' => 1, 'weeks' => 7, 'months' => 30];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    /**
     * The real, dictionary-verified drug this line names — required for every
     * NEW prescription (see PrescriptionController::store); nullable only
     * because older rows were free-text `name` before this existed.
     */
    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }
}
