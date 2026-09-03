<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Thread;
use App\Models\ThreadAccessConsent;
use App\Models\ThreadParticipant;
use App\Services\Chat\ThreadAccessGateService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A chat participant's own say over whether admins may ever read this
 * conversation. Not scoped to a dispute — the same decision applies whether
 * an admin is reviewing for support reasons or an arbitrator wants it as
 * evidence; see ThreadAccessGateService for the actual gate.
 */
class ThreadAccessController extends Controller
{
    public function __construct(private readonly ThreadAccessGateService $access)
    {
    }

    /** GET /api/v2/threads/{thread}/access-status */
    public function show(Request $request, Thread $thread)
    {
        $this->assertRealParty($thread, (int) $request->user()->id);

        $decisions = $this->access->partyDecisions($thread);

        return response()->json(['success' => true, 'data' => [
            'my_decision' => $decisions[(int) $request->user()->id] ?? null,
            'pending' => in_array(null, $decisions, true),
        ]]);
    }

    /** POST /api/v2/threads/{thread}/access-consent */
    public function store(Request $request, Thread $thread)
    {
        $this->assertRealParty($thread, (int) $request->user()->id);

        $data = $request->validate([
            'decision' => ['required', Rule::in([ThreadAccessConsent::APPROVED, ThreadAccessConsent::DECLINED])],
        ]);

        $this->access->recordPartyConsent($thread, $request->user(), $data['decision']);

        return response()->json(['success' => true]);
    }

    private function assertRealParty(Thread $thread, int $userId): void
    {
        $seat = $thread->participantFor($userId);

        // 404, not 403: a stranger must not learn the thread exists.
        abort_if($seat === null || $seat->role === ThreadParticipant::ROLE_ARBITRATOR, 404);
    }
}
