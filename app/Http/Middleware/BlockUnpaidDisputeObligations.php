<?php

namespace App\Http\Middleware;

use App\Services\DisputeCollectionService;
use Closure;
use Illuminate\Http\Request;

/**
 * Was: stops someone starting NEW business while they owe a ruling. Since 2026-10-28 (owner: «لا توقف الحساب في حالة النزاع
 * ولكن تجميد الضمان أو العربون») an unpaid ruling freezes the debtor's guarantee instead and isBlocked() is always false — the
 * middleware stays on its routes as the one place the rule would come back.
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

        // A deposit still owed outside the platform stops NOTHING here (المالك، 2026-10-20): the account works as
        // usual and may book anywhere. Only an opened dispute (above) holds an account back.
        return $next($request);
    }
}
