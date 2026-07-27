<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanProgressLog extends Model
{
    protected $fillable = [
        'training_plan_id',
        'client_id',
        'logged_on',
        'weight',
        'notes',
    ];

    protected $casts = [
        'logged_on' => 'date',
        'weight' => 'decimal:2',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TrainingPlan::class, 'training_plan_id');
    }
}
