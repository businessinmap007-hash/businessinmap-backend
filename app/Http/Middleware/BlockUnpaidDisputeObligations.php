<?php

namespace App\Http\Middleware;

use App\Services\DisputeCollectionService;
use Closure;
use Illuminate\Http\Request;

/**
 * Stops someone starting NEW business while they owe a ruling.
 *
 * Deliberately narrow. It does not freeze the wallet, because topping up is how
 * they get out, and a block that prevents payment is a trap rather than a
 * penalty. It does not suspend the account either — they must still be able to
 * read the dispute, argue it, and see what they owe. What it removes is the
 * ability to take on fresh obligations to other people while an existing one
 * is unmet.
 */
class BlockUnpaidDisputeObligations
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $collections = app(DisputeCollectionService::class);

        if ($collections->isBlocked((int) $user->id)) {
            return response()->json([
                'success' => false,
                'message' => __('حسابك موقوف عن العمليات الجديدة حتى سداد مستحقات نزاع بقيمة :amount.', [
                    'amount' => (string) $collections->outstandingFor((int) $user->id),
                ]),
                'errors' => ['dispute_obligation' => [
                    __('حسابك موقوف عن العمليات الجديدة حتى سداد مستحقات نزاع بقيمة :amount.', [
                        'amount' => (string) $collections->outstandingFor((int) $user->id),
                    ]),
                ]],
            ], 422);
        }

        return $next($request);
    }
}
