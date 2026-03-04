<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\WalletService;

class CheckWalletPin
{
    public function handle($request, Closure $next)
    {
        if (!$request->pin_code || strlen($request->pin_code) !== 6) {
            return response()->json(['status' => 400, 'message' => 'PIN code is required'], 400);
        }

        $wallet = app(WalletService::class);

        if (!$wallet->verifyPin($request->user(), $request->pin_code)) {
            return response()->json(['status' => 401, 'message' => 'Invalid PIN'], 401);
        }

        return $next($request);
    }
}
