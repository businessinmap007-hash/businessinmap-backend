<?php

namespace App\Models\Concerns;

use App\Models\Assignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Put this on a model that a business can hand to one of its staff (a booking, an order…). It adds nothing to the
 * table: the assignment lives in `assignments`, and AssignmentService is the only writer.
 */
trait HasAssignee
{
    public function assignment(): MorphOne
    {
        return $this->morphOne(Assignment::class, 'assignable');
    }

    /** Items assigned to one user. */
    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->whereIn($this->qualifyColumn($this->getKeyName()), Assignment::query()
            ->select('assignable_id')
            ->where('assignable_type', $this->getMorphClass())
            ->where('assignee_id', $userId));
    }
}
