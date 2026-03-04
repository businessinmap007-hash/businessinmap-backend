<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserHasLocation
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if ($user) {
            if (
                is_null($user->country_id) ||
                is_null($user->governorate_id) ||
                is_null($user->city_id)
            ) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'يرجى تحديد الموقع أولاً',
                    'action'  => 'SET_LOCATION'
                ], 403);
            }
        }

        return $next($request);
    }
}
