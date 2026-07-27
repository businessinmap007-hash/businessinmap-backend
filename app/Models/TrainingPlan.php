<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A trainer's program for a client (see the create_training_plans migration):
 * a workout (exercises) + a nutrition plan (meals), tracked by the client's
 * progress logs. Readable only by the trainer and the client.
 */
class TrainingPlan extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_PAUSED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'trainer_id',
        'client_id',
        'title',
        'goal',
        'status',
        'starts_on',
        'ends_on',
        'notes',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(PlanExercise::class)->orderBy('sort_order')->orderBy('id');
    }

    public function meals(): HasMany
    {
        return $this->hasMany(PlanMeal::class)->orderBy('sort_order')->orderBy('id');
    }

    public function progressLogs(): HasMany
    {
        return $this->hasMany(PlanProgressLog::class)->latest('logged_on')->latest('id');
    }

    public function isParty(int $userId): bool
    {
        return in_array($userId, [(int) $this->trainer_id, (int) $this->client_id], true);
    }
}
