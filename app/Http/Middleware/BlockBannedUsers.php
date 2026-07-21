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

        return $next($request);
    }
}
