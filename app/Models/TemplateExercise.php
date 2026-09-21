<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateExercise extends Model
{
    protected $fillable = [
        'training_plan_template_id',
        'library_exercise_id',
        'day_of_week',
        'name',
        'sets',
        'reps',
        'target_weight',
        'set_weights',
        'progress_every_weeks',
        'progress_increment_kg',
        'day_label',
        'rest_seconds',
        'notes',
        'sort_order',
    ];

    protected $casts = ['set_weights' => 'array'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(TrainingPlanTemplate::class, 'training_plan_template_id');
    }
}
