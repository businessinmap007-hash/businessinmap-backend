<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** «Finished today»: every exercise scheduled for a day has all its sets confirmed. */
class PlanSessionCompletion extends Model
{
    protected $fillable = [
        'training_plan_id',
        'client_id',
        'for_date',
        'exercises_count',
        'sets_count',
        'total_reps',
        'volume_kg',
        'completed_at',
    ];

    protected $casts = [
        'for_date' => 'date',
        'volume_kg' => 'float',
        'completed_at' => 'datetime',
    ];
}
