<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BusinessOnly
{
    /**
     * Handle an incoming request.
     *
     * يسمح فقط لمستخدمي business بالدخول
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // إذا المستخدم لم يسجل دخول
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'Unauthorized: Please login first.',
            ], 401);
        }

        // التحقق من نوع الحساب
        if ($user->account_type !== 'business') {
            return response()->json([
                'status' => 403,
                'message' => 'Access Denied: Business accounts only.',
            ], 403);
        }

        return $next($request);
    }
}
