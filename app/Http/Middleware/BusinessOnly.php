<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BusinessOnly
{
    /**
     * Handle an incoming request. The single, central "business accounts only"
     * gate for the API — replaces the role checks that were duplicated inline in
     * each business controller. Uses the canonical User::isBusiness() (type
     * column), not the non-existent legacy `account_type`.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Please login first.',
            ], 401);
        }

        if (! $user->isBusiness()) {
            return response()->json([
                'success' => false,
                'message' => 'إدارة حسابات الأعمال متاحة لحسابات الأعمال فقط.',
            ], 403);
        }

        return $next($request);
    }
}
