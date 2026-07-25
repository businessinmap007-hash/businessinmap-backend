<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\ThreadMessageResource;
use App\Models\Thread;
use App\Services\DirectChatService;
use App\Services\Media\ImageUploadService;
use App\Services\ThreadService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * General person-to-person chat (direct messages). A conversation about
 * nothing in particular — the plain messaging case the threads system was
 * built to also serve, reusing the same posting rules and evidence
 * attachments as the dispute room and operation chat.
 */
class ChatController extends Controller
{
    public function __construct(
        protected DirectChatService $chats,
        protected ThreadService $threads
    ) {
    }

    /** GET /api/v2/chats — my conversations, most-recently-active first. */
    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->chats->listFor($request->user()),
        ]);
    }

    /** POST /api/v2/chats — start (or return) a direct chat with a user. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
        ]);

        $thread = $this->chats->startWith($request->user(), (int) $data['user_id']);

        return response()->json([
            'success' => true,
            'data' => $this->threadMeta($thread, (int) $request->user()->id),
        ], 201);
    }

    /** GET /api/v2/chats/{thread} — read a conversation. */
    public function show(Request $request, Thread $thread)
    {
        $this->assertDirect($thread);
        $this->chats->assertParticipant($thread, (int) $request->user()->id);

        $data = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $messages = $thread->messages()
            ->with('sender:id,name', 'attachments')
            ->orderByDesc('id')
            ->paginate((int) ($data['per_page'] ?? 30))
            ->appends($request->query());

        $this->threads->markRead($thread, (int) $request->user()->id);

        return ThreadMessageResource::collection($messages)->additional([
            'meta' => ['thread' => $this->threadMeta($thread, (int) $request->user()->id)],
        ]);
    }

    /** POST /api/v2/chats/{thread}/messages — say something, with optional files. */
    public function postMessage(Request $request, Thread $thread)
    {
        $this->assertDirect($thread);
        $this->chats->assertParticipant($thread, (int) $request->user()->id);

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

        $message = $this->threads->post($thread, (int) $request->user()->id, (string) ($data['body'] ?? ''), $files);
        $message->loadMissing('sender:id,name', 'attachments');

        return response()->json([
            'success' => true,
            'data' => new ThreadMessageResource($message),
        ], 201);
    }

    /** A general chat only — never a dispute room or an operation chat. */
    private function assertDirect(Thread $thread): void
    {
        abort_if($thread->subject_type !== null, 404);
    }

    private function threadMeta(Thread $thread, int $viewerId): array
    {
        $thread->loadMissing('participants.user:id,name');

        return [
            'id' => (int) $thread->id,
            'participants' => $thread->participants
                ->where('user_id', '!=', $viewerId)
                ->map(fn ($p) => ['user_id' => (int) $p->user_id, 'name' => $p->user?->name])
                ->values(),
        ];
    }
}
