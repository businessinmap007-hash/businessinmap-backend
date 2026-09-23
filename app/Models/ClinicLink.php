<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A mutual-consent link between two of the SAME doctor's clinic accounts
 * (see the create migration). Directional while pending (requester → target),
 * meaningless once accepted — either side may then unlink it.
 */
class ClinicLink extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';

    protected $fillable = [
        'requester_id',
        'target_id',
        'status',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_id');
    }

    public function isParty(int $userId): bool
    {
        return in_array($userId, [(int) $this->requester_id, (int) $this->target_id], true);
    }

    /** The OTHER clinic id in this link, from $userId's point of view. */
    public function otherId(int $userId): int
    {
        return (int) $this->requester_id === $userId ? (int) $this->target_id : (int) $this->requester_id;
    }
}
