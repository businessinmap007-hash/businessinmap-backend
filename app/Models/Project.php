<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A business's project timeline (see the create_projects migration). Owned by a
 * business; broken into dated, dependent tasks whose schedule the timeline
 * computes. `progress` is a cached rollup kept fresh by ProjectService.
 */
class Project extends Model
{
    public const STATUS_PLANNING = 'planning';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ON_HOLD = 'on_hold';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PLANNING,
        self::STATUS_ACTIVE,
        self::STATUS_ON_HOLD,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    public const VISIBILITY_PRIVATE = 'private';
    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITIES = [self::VISIBILITY_PRIVATE, self::VISIBILITY_PUBLIC];

    protected $fillable = [
        'business_id',
        'title',
        'description',
        'status',
        'visibility',
        'reference',
        'starts_on',
        'due_on',
        'progress',
        'operation_type',
        'operation_id',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'due_on' => 'date',
        'progress' => 'integer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Top-level stages only (no parent). */
    public function rootTasks(): HasMany
    {
        return $this->tasks()->whereNull('parent_id');
    }

    public function operation(): MorphTo
    {
        return $this->morphTo('operation');
    }

    public function followers(): HasMany
    {
        return $this->hasMany(ProjectFollower::class);
    }

    public function isPublic(): bool
    {
        return $this->visibility === self::VISIBILITY_PUBLIC;
    }

    public function scopeForBusiness(Builder $query, int $businessId): Builder
    {
        return $query->where('business_id', $businessId);
    }

    public function isOverdue(): bool
    {
        return $this->due_on
            && $this->status !== self::STATUS_COMPLETED
            && $this->status !== self::STATUS_CANCELLED
            && $this->due_on->isPast();
    }
}
