<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The plan as the two sides agreed it — one immutable VERSION: the stages, places and quantities (`snapshot`), the delivery
 * date and the end of the grace. A new agreement version is written for every approved extension or scope change; old
 * versions are only ever marked `superseded`. A dispute reads these to know what was promised and when.
 */
class ProjectAgreement extends Model
{
    public const PROPOSED = 'proposed';
    public const AGREED = 'agreed';
    public const SUPERSEDED = 'superseded';
    public const REJECTED = 'rejected';

    protected $fillable = [
        'project_id', 'version', 'status', 'due_on', 'grace_until', 'snapshot', 'content_hash', 'proposed_by', 'business_agreed_at',
        'customer_id', 'customer_agreed_at', 'customer_note', 'change_request_id',
    ];

    protected $casts = [
        'due_on' => 'date',
        'grace_until' => 'date',
        'snapshot' => 'array',
        'business_agreed_at' => 'datetime',
        'customer_agreed_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** What was agreed can never be edited — only its status may move on. */
    protected static function booted(): void
    {
        static::updating(function (self $a) {
            $frozen = array_diff(array_keys($a->getDirty()), ['status', 'customer_id', 'customer_agreed_at', 'customer_note', 'updated_at']);

            if ($a->getOriginal('status') !== self::PROPOSED && $frozen !== []) {
                throw new \LogicException('An agreement version is immutable.');
            }
        });
    }
}
