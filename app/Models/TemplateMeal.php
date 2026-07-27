<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateMeal extends Model
{
    protected $fillable = [
        'training_plan_template_id',
        'meal_type',
        'name',
        'calories',
        'notes',
        'sort_order',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(TrainingPlanTemplate::class, 'training_plan_template_id');
    }
}
