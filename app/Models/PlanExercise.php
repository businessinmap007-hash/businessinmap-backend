<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanExercise extends Model
{
    protected $fillable = [
        'training_plan_id',
        'day_of_week',
        'name',
        'sets',
        'reps',
        'rest_seconds',
        'notes',
        'sort_order',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TrainingPlan::class, 'training_plan_id');
    }
}
