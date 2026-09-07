<?php

namespace App\Support;

use App\Models\User;

/** Shared "find a registered user by phone or email" lookup — the same free-text
 * identifier field used by a shared-cart invite and a contact-group member add. */
final class UserLookup
{
    public static function byIdentifier(string $identifier): ?User
    {
        $identifier = trim($identifier);

        return User::query()
            ->where('phone', $identifier)
            ->orWhere('email', $identifier)
            ->first();
    }
}
