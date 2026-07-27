<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * The secret token behind a user's calendar subscription URL (see migration).
 */
class AgendaFeedToken extends Model
{
    protected $fillable = ['user_id', 'token'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The user's token, creating one on first use. */
    public static function forUser(int $userId): self
    {
        $row = static::query()->firstOrNew(['user_id' => $userId]);
        if (! $row->exists) {
            $row->token = self::freshToken();
            $row->save();
        }

        return $row;
    }

    /** Replace the token, invalidating the old subscription URL. */
    public function rotate(): self
    {
        $this->update(['token' => self::freshToken()]);

        return $this;
    }

    private static function freshToken(): string
    {
        do {
            $token = Str::random(48);
        } while (static::query()->where('token', $token)->exists());

        return $token;
    }
}
