<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One confirmed round (set) of an exercise on a given day — the trainee's only
 * progress action in the training section. See the create migration.
 */
class PlanExerciseRound extends Model
{
    protected $fillable = [
        'plan_exercise_id',
        'training_plan_id',
        'client_id',
        'for_date',
        'round_number',
        'completed_at',
    ];

    protected $casts = [
        'for_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(PlanExercise::class, 'plan_exercise_id');
    }
}
