<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartyTrust extends Model
{
    protected $fillable = ['truster_id', 'trusted_id'];

    public static function exists(int $trusterId, int $trustedId): bool
    {
        return static::query()->where('truster_id', $trusterId)->where('trusted_id', $trustedId)->exists();
    }
}
