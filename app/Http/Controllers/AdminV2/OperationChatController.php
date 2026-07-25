<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Thread;
use App\Services\OperationChatService;
use App\Services\ThreadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Operation chats whose retention window has passed — kept as evidence for 7
 * days after the operation completed, and now deletable.
 *
 * This screen lists the expired ones and lets an admin delete a conversation.
 * Deletion is the only action: an active chat is not shown here, and nothing
 * on this page can reopen or edit a conversation — the record is only ever
 * removed whole, with its files.
 */
class OperationChatController extends Controller
{
    public function __construct(private readonly ThreadService $threads)
    {
    }

    /** GET admin/operation-chats — the expired (deletable) chats. */
    public function index(Request $request): View
    {
        $rows = Thread::query()
            ->whereIn('subject_type', array_values(OperationChatService::TYPES))
            ->whereNotNull('retain_until')
            ->where('retain_until', '<=', now())
            ->withCount(['messages', 'participants'])
            ->with(['participants.user:id,name'])
            ->orderBy('retain_until')
            ->paginate(30);

        return view('admin-v2.operation-chats.index', ['rows' => $rows]);
    }

    /** DELETE admin/operation-chats/{thread} — remove one expired chat. */
    public function destroy(Thread $thread): RedirectResponse
    {
        // Only ever an expired operation chat: never a dispute room (kept, or
        // purged only by both parties' consent) or an active chat.
        $isOperation = in_array($thread->subject_type, array_values(OperationChatService::TYPES), true);

        if (! $isOperation || ! $thread->isExpired()) {
            return back()->with('error', __('لا يمكن حذف هذه المحادثة من هنا.'));
        }

        $this->threads->purge($thread);

        return back()->with('success', __('حُذفت المحادثة.'));
    }
}
