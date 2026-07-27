<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\ThreadMessageResource;
use App\Models\Thread;
use App\Models\TrainingPlan;
use App\Services\ThreadService;
use App\Services\Training\TrainingChatService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;

/**
 * The direct, text-only chat between a trainer and their trainee, scoped to a
 * training plan. The trainee reaches it under auth (their plan); the trainer
 * under business.member:training (a plan their business owns) — and a delegate
 * posts AS the business, the seated party. No attachments here by design.
 */
class TrainingChatController extends Controller
{
    public function __construct(
        private readonly TrainingChatService $chat,
        private readonly ThreadService $threads,
    ) {
    }

    /* ------------------------------------------------------------- client */

    /** GET /api/v2/training-plans/{plan}/chat */
    public function clientShow(Request $request, int $plan)
    {
        $row = $this->clientPlanOrFail($request, $plan);

        return $this->render($request, $row, (int) $request->user()->id);
    }

    /** POST /api/v2/training-plans/{plan}/chat/messages */
    public function clientPost(Request $request, int $plan)
    {
        $row = $this->clientPlanOrFail($request, $plan);

        return $this->send($request, $row, (int) $request->user()->id);
    }

    /* ------------------------------------------------------------ trainer */

    /** GET /api/v2/business/training-plans/{plan}/chat */
    public function trainerShow(Request $request, int $plan)
    {
        $row = $this->trainerPlanOrFail($request, $plan);

        return $this->render($request, $row, BusinessContext::id($request));
    }

    /** POST /api/v2/business/training-plans/{plan}/chat/messages */
    public function trainerPost(Request $request, int $plan)
    {
        $row = $this->trainerPlanOrFail($request, $plan);

        return $this->send($request, $row, BusinessContext::id($request));
    }

    /* ------------------------------------------------------------- shared */

    private function render(Request $request, TrainingPlan $plan, int $readerId)
    {
        $data = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        $thread = $this->chat->open($plan);

        $messages = $thread->messages()
            ->with('sender:id,name')
            ->orderByDesc('id')
            ->paginate((int) ($data['per_page'] ?? 30))
            ->appends($request->query());

        $this->threads->markRead($thread, $readerId);

        return ThreadMessageResource::collection($messages)->additional([
            'meta' => ['thread' => ['id' => (int) $thread->id, 'attachments_allowed' => false]],
        ]);
    }

    private function send(Request $request, TrainingPlan $plan, int $senderId)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $thread = $this->chat->open($plan);
        $message = $this->chat->post($thread, $senderId, (string) $data['body']);
        $message->loadMissing('sender:id,name');

        return response()->json(['success' => true, 'data' => new ThreadMessageResource($message)], 201);
    }

    private function clientPlanOrFail(Request $request, int $planId): TrainingPlan
    {
        return TrainingPlan::query()
            ->where('id', $planId)
            ->where('client_id', (int) $request->user()->id)
            ->firstOrFail();
    }

    private function trainerPlanOrFail(Request $request, int $planId): TrainingPlan
    {
        return TrainingPlan::query()
            ->where('id', $planId)
            ->where('trainer_id', BusinessContext::id($request))
            ->firstOrFail();
    }
}
