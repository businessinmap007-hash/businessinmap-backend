<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * «التعيين»: the staff member of a business who is responsible for one item — see App\Support\Providers (who may be
 * assigned) and App\Services\Assignments\AssignmentService (how an item is assigned). One row per item.
 */
class Assignment extends Model
{
    protected $fillable = ['business_id', 'assignable_type', 'assignable_id', 'assignee_id', 'assigned_by'];

    protected $casts = [
        'business_id' => 'integer',
        'assignable_id' => 'integer',
        'assignee_id' => 'integer',
        'assigned_by' => 'integer',
    ];

    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }
}
