<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable training template owned by a trainer (see the create migration).
 * Applied to a client, it COPIES its items into a fresh TrainingPlan.
 */
class TrainingPlanTemplate extends Model
{
    protected $fillable = [
        'trainer_id',
        'title',
        'goal',
        'notes',
    ];

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(TemplateExercise::class)->orderBy('sort_order')->orderBy('id');
    }

    public function meals(): HasMany
    {
        return $this->hasMany(TemplateMeal::class)->orderBy('sort_order')->orderBy('id');
    }
}
