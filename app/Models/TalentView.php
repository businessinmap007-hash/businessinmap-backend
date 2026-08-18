<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One scout's whole relationship with one talent card.
 *
 * There is exactly one row per (player, scout) — the unique index is the
 * «تحسب مرة واحدة» rule — so this is not a log of views, it is the state of a
 * pair: when he first looked, how many times since, and whether he has paid to
 * see who the boy actually is.
 */
class TalentView extends Model
{
    protected $fillable = [
        'talent_post_id',
        'scout_id',
        'first_seen_at',
        'view_count',
        'view_fee',
        'view_transaction_id',
        'revealed_at',
        'reveal_fee',
        'reveal_transaction_id',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'revealed_at' => 'datetime',
        'view_fee' => 'decimal:2',
        'reveal_fee' => 'decimal:2',
        'view_count' => 'integer',
    ];

    public function talent(): BelongsTo
    {
        return $this->belongsTo(TalentPost::class, 'talent_post_id');
    }

    public function scout(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scout_id');
    }

    /** Has this scout paid to see the boy's name and number? */
    public function isRevealed(): bool
    {
        return $this->revealed_at !== null;
    }
}
