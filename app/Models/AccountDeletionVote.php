<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A moderator's vote on a deletion proposal. Never shown with the moderator's identity. */
class AccountDeletionVote extends Model
{
    public const APPROVE = 'approve';
    public const REJECT = 'reject';

    protected $fillable = ['proposal_id', 'moderator_id', 'round', 'vote', 'note'];
}
