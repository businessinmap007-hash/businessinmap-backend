<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Stops a banned user on every authenticated request.
 *
 * Login already refuses a banned account, but a token minted before the ban
 * would keep working until it expired. BanService revokes tokens on the spot;
 * this is the defence-in-depth for any that slip through — a re-issued token, a
 * ban applied mid-session, a code path that does not go through login.
 */
class BlockBannedUsers
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->isBanned()) {
            return response()->json([
                'success' => false,
                'message' => __('حسابك موقوف.'),
                'errors' => ['account' => [__('حسابك موقوف.')]],
            ], 403);
        }

        // A moderator froze the account (reports on it): it keeps ONE screen — the chat with the moderators — and the way out.
        if ($user && $user->isFrozen() && ! $this->frozenMayReach($request)) {
            return response()->json([
                'success' => false,
                'code' => 'account_frozen',
                'message' => __('حسابك مجمَّد مؤقتًا. تواصل مع المشرفين من شاشة التجميد.'),
                'errors' => ['account' => [__('حسابك مجمَّد مؤقتًا. تواصل مع المشرفين من شاشة التجميد.')]],
                'data' => ['frozen_until' => $user->frozen_until->toIso8601String()],
            ], 423);
        }

        return $next($request);
    }

    /** What a frozen account can still do: see itself, sign out, read/answer the moderators' chat, read its own case. */
    private function frozenMayReach(Request $request): bool
    {
        $path = trim($request->path(), '/');

        foreach (['api/v2/auth/', 'api/v2/operation-chats/moderation/', 'api/v2/moderation/my-case'] as $allowed) {
            if (str_starts_with($path . '/', $allowed) || str_starts_with($path, $allowed)) {
                return true;
            }
        }

        return false;
    }
}
