<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Thread;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One place for an admin/judge to see every conversation on the platform —
 * dispute rooms, operation chats, direct messages and groups — merged into a
 * single moderation view.
 *
 * Access is deliberately narrow: the routes are gated on the DISPUTES (judge)
 * ability, and conversation text is encrypted at rest, so only someone trusted
 * to rule on a case can read what was said. This screen is read-only — it does
 * not post; ruling and the consented dispute-room purge stay on their own
 * screens.
 */
class ChatAdminController extends Controller
{
    /** GET admin/chats — every thread, filterable by type. */
    public function index(Request $request): View
    {
        $type = (string) $request->query('type', '');

        $rows = Thread::query()
            ->when($type !== '', fn ($q) => $this->scopeType($q, $type))
            ->withCount(['messages', 'participants'])
            ->with(['participants.user:id,name'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin-v2.chats.index', ['rows' => $rows, 'type' => $type]);
    }

    /** GET admin/chats/{thread} — read one conversation (decrypted). */
    public function show(Thread $thread): View
    {
        $thread->load(['participants.user:id,name', 'messages.sender:id,name', 'messages.attachments']);

        return view('admin-v2.chats.show', [
            'thread' => $thread,
            'kind' => $this->kindOf($thread),
        ]);
    }

    /** Human label for a thread's kind, from its subject/owner. */
    public function kindOf(Thread $thread): string
    {
        if ($thread->subject_type === (new Dispute())->getMorphClass()) {
            return __('غرفة نزاع');
        }
        if (in_array($thread->subject_type, [(new Order())->getMorphClass(), (new Booking())->getMorphClass()], true)) {
            return __('محادثة عملية');
        }
        if ($thread->created_by !== null) {
            return __('مجموعة');
        }

        return __('محادثة مباشرة');
    }

    private function scopeType($query, string $type)
    {
        return match ($type) {
            'dispute' => $query->where('subject_type', (new Dispute())->getMorphClass()),
            'operation' => $query->whereIn('subject_type', [(new Order())->getMorphClass(), (new Booking())->getMorphClass()]),
            'group' => $query->whereNull('subject_type')->whereNotNull('created_by'),
            'direct' => $query->whereNull('subject_type')->whereNull('created_by'),
            default => $query,
        };
    }
}
