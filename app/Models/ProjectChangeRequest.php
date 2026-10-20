<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Something the business asks the customer to approve after the plan was agreed: more time (`extension`, payload
 * {grace_until}) or a different amount of work (`scope`, payload {item_id, field, to, task_id…}). Approval applies it and
 * writes a new agreement version; a rejection changes nothing.
 */
class ProjectChangeRequest extends Model
{
    public const EXTENSION = 'extension';
    public const SCOPE = 'scope';
    public const SHIFT = 'shift';

    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    protected $fillable = ['project_id', 'type', 'payload', 'note', 'status', 'requested_by', 'decided_by', 'decided_at', 'decision_note'];

    protected $casts = ['payload' => 'array', 'decided_at' => 'datetime'];
}
