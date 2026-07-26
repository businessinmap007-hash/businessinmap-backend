<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Reads the acting-business context the `business.member` middleware stashed on
 * the request. Retrofitted business controllers call these instead of
 * `$request->user()->id`, so a delegated staff member transparently acts as the
 * employer account. When no context was set (a plain `business` route), it
 * falls back to the authenticated user — safe for owner-only surfaces.
 */
final class BusinessContext
{
    public const BUSINESS = 'acting_business';
    public const IS_OWNER = 'acting_is_owner';
    public const CAPABILITIES = 'acting_capabilities';

    /** The business the caller is acting for. */
    public static function business(Request $request): ?User
    {
        return $request->attributes->get(self::BUSINESS) ?: $request->user();
    }

    /** The acting business's id. */
    public static function id(Request $request): int
    {
        return (int) optional(self::business($request))->id;
    }

    public static function isOwner(Request $request): bool
    {
        return (bool) $request->attributes->get(self::IS_OWNER, true);
    }

    /** @return array<string> */
    public static function capabilities(Request $request): array
    {
        return (array) $request->attributes->get(self::CAPABILITIES, []);
    }
}
