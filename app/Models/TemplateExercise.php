<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateExercise extends Model
{
    protected $fillable = [
        'training_plan_template_id',
        'day_of_week',
        'name',
        'sets',
        'reps',
        'rest_seconds',
        'notes',
        'sort_order',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(TrainingPlanTemplate::class, 'training_plan_template_id');
    }
}
