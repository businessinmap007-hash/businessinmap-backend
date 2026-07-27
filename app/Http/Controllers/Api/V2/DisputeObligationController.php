<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\DisputeObligation;
use App\Services\DisputeCollectionService;
use Illuminate\Http\Request;

/**
 * A party's view of what a ruling decided they owe — and the "pay to unblock"
 * action.
 *
 * Rulings can block a party from new operations until they settle
 * (BlockUnpaidDisputeObligations), but nothing told them what they owed, when it
 * is due, or how to clear it — the block was silent. And a top-up made inside the
 * grace window would not lift the block until the scheduled sweep ran after the
 * deadline, even with the money in hand. This is the door to both.
 *
 * These routes are deliberately NOT behind the block middleware: a blocked user
 * must be able to see and pay their debt.
 */
class DisputeObligationController extends Controller
{
    public function __construct(private readonly DisputeCollectionService $collections)
    {
    }

    /** GET /api/v2/me/dispute-obligations — what I owe, what I'm owed, am I blocked. */
    public function index(Request $request)
    {
        $userId = (int) $request->user()->id;

        return response()->json([
            'success' => true,
            'data' => [
                'blocked' => $this->collections->isBlocked($userId),
                'outstanding' => $this->collections->outstandingFor($userId),
                'owed_by_me' => $this->collections->pendingOwedBy($userId)
                    ->map(fn (DisputeObligation $o) => $this->serialize($o))->values(),
                'owed_to_me' => $this->collections->pendingOwedTo($userId)
                    ->map(fn (DisputeObligation $o) => $this->serialize($o))->values(),
            ],
        ]);
    }

    /**
     * POST /api/v2/me/dispute-obligations/settle — pay my pending debts from my
     * wallet now, to lift the block after topping up.
     */
    public function settle(Request $request)
    {
        $summary = $this->collections->settleForUser((int) $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => $summary['blocked']
                ? __('سُدِّد ما أمكن؛ رصيدك لا يكفي باقي المستحقات.')
                : __('تم سداد مستحقات النزاع.'),
            'data' => $summary,
        ]);
    }

    private function serialize(DisputeObligation $o): array
    {
        return [
            'id' => (int) $o->id,
            'dispute_id' => (int) $o->dispute_id,
            'type' => (string) $o->type,
            'amount' => (float) $o->amount,
            'status' => (string) $o->status,
            'due_at' => optional($o->due_at)->toIso8601String(),
            'is_due' => $o->isDue(),
            'settled_from' => $o->settled_from,
            'paid_at' => optional($o->paid_at)->toIso8601String(),
        ];
    }
}
