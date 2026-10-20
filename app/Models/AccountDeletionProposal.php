<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A request to delete an account after its fifth recent freeze. Not one moderator's decision: a panel votes on it
 * ({@see AccountDeletionVote}); once approved it waits out a grace period in which the account may object in writing.
 */
class AccountDeletionProposal extends Model
{
    public const STATUS_PENDING = 'pending';     // the panel has not decided (yet, or again after an objection)
    public const STATUS_APPROVED = 'approved';   // approved; runs at execute_after unless the account objects
    public const STATUS_EXECUTED = 'executed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = ['user_id', 'case_id', 'proposed_by', 'status', 'round', 'execute_after', 'objection', 'objected_at', 'decided_at'];

    protected $casts = ['execute_after' => 'datetime', 'objected_at' => 'datetime', 'decided_at' => 'datetime'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(AccountDeletionVote::class, 'proposal_id');
    }

    /** The votes of the current round. */
    public function roundVotes(): HasMany
    {
        return $this->votes()->where('round', $this->round);
    }
}
