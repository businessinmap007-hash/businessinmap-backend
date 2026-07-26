<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One bar on a project's timeline. Its place on the timeline is derived from
 * the finish-to-start dependency graph (dependencies()), not stored.
 */
class ProjectTask extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_DONE = 'done';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_BLOCKED,
        self::STATUS_DONE,
    ];

    protected $fillable = [
        'project_id',
        'parent_id',
        'title',
        'notes',
        'status',
        'starts_on',
        'ends_on',
        'progress',
        'requires_photo',
        'sort_order',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'progress' => 'integer',
        'requires_photo' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** Tasks that must finish before this one may start. */
    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'project_task_dependencies',
            'task_id',
            'depends_on_id'
        )->withTimestamps();
    }

    /** Tasks waiting on this one. */
    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'project_task_dependencies',
            'depends_on_id',
            'task_id'
        )->withTimestamps();
    }

    /** Camera-captured progress evidence, newest first. */
    public function photos(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable')->latest('id');
    }

    /** Own duration in whole days (inclusive), min 1. */
    public function durationDays(): int
    {
        if ($this->starts_on && $this->ends_on) {
            return max(1, $this->starts_on->diffInDays($this->ends_on) + 1);
        }

        return 1;
    }

    public function isDone(): bool
    {
        return $this->status === self::STATUS_DONE;
    }
}
