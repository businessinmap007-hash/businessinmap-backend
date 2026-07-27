<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanMeal extends Model
{
    public const TYPES = ['breakfast', 'lunch', 'dinner', 'snack'];

    protected $fillable = [
        'training_plan_id',
        'meal_type',
        'name',
        'calories',
        'notes',
        'sort_order',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TrainingPlan::class, 'training_plan_id');
    }
}
