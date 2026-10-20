<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A moderator's file on a reported account — see the create_account_cases migration. Its chat (the moderator asking what the
 * report is about, the account answering) is the thread whose subject is this row.
 */
class AccountCase extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = ['user_id', 'opened_by', 'status', 'reason', 'frozen_until', 'outcome', 'closed_by', 'closed_at'];

    protected $casts = ['frozen_until' => 'datetime', 'closed_at' => 'datetime'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /** The two people in the chat: the account and the moderator who opened it. */
    public function parties(): array
    {
        return [(int) $this->user_id, (int) $this->opened_by];
    }
}
