<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member of a shared (group) cart — the host plus everyone who joined via the
 * share token. See CustomerCartService and the 2026_07_15 migration.
 */
class OrderParticipant extends Model
{
    public const ROLE_HOST = 'host';
    public const ROLE_MEMBER = 'member';

    protected $fillable = [
        'order_id',
        'user_id',
        'role',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isHost(): bool
    {
        return $this->role === self::ROLE_HOST;
    }
}
