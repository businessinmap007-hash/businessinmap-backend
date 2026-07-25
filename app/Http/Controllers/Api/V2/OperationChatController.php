<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\ThreadMessageResource;
use App\Services\Media\ImageUploadService;
use App\Services\OperationChatService;
use App\Services\ThreadService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * The customer↔business chat on an operation (order or booking). The trusted,
 * in-app alternative to attaching forgeable screenshots from elsewhere.
 *
 * A chat is a thread; the heavy lifting (participants, posting rules, evidence
 * attachments, notifications) is the same generic machinery the dispute room
 * uses. This controller only resolves the operation, checks the caller is a
 * party, and hands off.
 */
class OperationChatController extends Controller
{
    public function __construct(
        protected OperationChatService $chats,
        protected ThreadService $threads
    ) {
    }

    /** GET /api/v2/operation-chats/{type}/{id} — open the chat and read it. */
    public function show(Request $request, string $type, int $id)
    {
        $operation = $this->chats->resolve($type, $id);
        $this->chats->assertParty($operation, (int) $request->user()->id);

        $data = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $thread = $this->chats->open($operation);

        $messages = $thread->messages()
            ->with('sender:id,name', 'attachments')
            ->orderByDesc('id')
            ->paginate((int) ($data['per_page'] ?? 30))
            ->appends($request->query());

        // Opening the chat is reading it.
        $this->threads->markRead($thread, (int) $request->user()->id);

        return ThreadMessageResource::collection($messages)->additional([
            'meta' => [
                'thread' => [
                    'id' => (int) $thread->id,
                    'status' => $thread->status,
                    'locked' => $thread->isLocked(),
                    'expired' => $thread->isExpired(),
                    'retain_until' => optional($thread->retain_until)->toIso8601String(),
                    'participants' => $thread->participants->map(fn ($p) => [
                        'user_id' => (int) $p->user_id,
                        'role' => $p->role,
                    ])->values(),
                ],
            ],
        ]);
    }

    /**
     * POST /api/v2/operation-chats/{type}/{id}/messages — say something, with
     * optional evidence files (multipart: body + attachments[]).
     *
     * Body is optional WHEN files are sent; a post with neither is refused. A
     * locked (expired) chat refuses new messages — it is now a kept record.
     */
    public function postMessage(Request $request, string $type, int $id)
    {
        $operation = $this->chats->resolve($type, $id);
        $this->chats->assertParty($operation, (int) $request->user()->id);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:' . ThreadService::MAX_ATTACHMENTS],
            'attachments.*' => ImageUploadService::validationRules(),
        ]);

        $files = array_values(array_filter(
            (array) $request->file('attachments', []),
            fn ($f) => $f instanceof UploadedFile
        ));

        if (trim((string) ($data['body'] ?? '')) === '' && $files === []) {
            throw ValidationException::withMessages([
                'body' => __('اكتب رسالة أو أرفق ملفًا.'),
            ]);
        }

        $thread = $this->chats->open($operation);

        // ThreadService refuses a locked chat; it comes back as 422.
        $message = $this->threads->post($thread, (int) $request->user()->id, (string) ($data['body'] ?? ''), $files);

        $message->loadMissing('sender:id,name', 'attachments');

        return response()->json([
            'success' => true,
            'data' => new ThreadMessageResource($message),
        ], 201);
    }
}
