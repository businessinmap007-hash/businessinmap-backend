<?php

namespace App\Models;

use App\Models\Concerns\HasOwnedImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanExercise extends Model
{
    /**
     * Illustrative photos the CAPTAIN attaches — the machine, the grip, the
     * position. Never the client's: there is no client-side route that writes
     * one, which is the enforcement. A plan is two people's private business
     * and a photo uploaded into it would be the one thing in it neither of them
     * agreed to.
     */
    use HasOwnedImages;

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

    public function rounds(): HasMany
    {
        return $this->hasMany(PlanExerciseRound::class);
    }
}
