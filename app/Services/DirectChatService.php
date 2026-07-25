<?php

namespace App\Services;

use App\Models\Thread;
use App\Models\ThreadParticipant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * General person-to-person chat: a direct conversation between two users that
 * is ABOUT nothing in particular (no order, no dispute).
 *
 * This is the case the generic threads system was built to also serve — a
 * thread with no subject and `member` participants. It reuses everything the
 * dispute room and operation chat use (posting rules, evidence attachments,
 * unread counts), and asks for no conduct charter and carries no retention: a
 * personal chat is kept until a party deletes it (not built yet).
 */
class DirectChatService
{
    public function __construct(
        protected ThreadService $threads
    ) {
    }

    /**
     * Open (or return) the direct chat between two users. Deduped: a second
     * "message so-and-so" must land in the same conversation, never a parallel
     * one, so the history is one thread.
     */
    public function startWith(User $me, int $otherUserId): Thread
    {
        if ($otherUserId === (int) $me->id) {
            throw ValidationException::withMessages([
                'user_id' => __('لا يمكنك بدء محادثة مع نفسك.'),
            ]);
        }

        // 404 if the recipient does not exist — you cannot message a phantom.
        User::query()->findOrFail($otherUserId);

        $existing = $this->directBetween((int) $me->id, $otherUserId);

        if ($existing) {
            return $existing->load('participants');
        }

        $thread = Thread::create([
            'status' => Thread::STATUS_OPEN,
            'requires_conduct' => false,
        ]);

        $this->threads->addParticipant($thread, (int) $me->id, ThreadParticipant::ROLE_MEMBER);
        $this->threads->addParticipant($thread, $otherUserId, ThreadParticipant::ROLE_MEMBER);

        return $thread->load('participants');
    }

    /** The existing 1-to-1 subjectless thread between two users, if any. */
    public function directBetween(int $userA, int $userB): ?Thread
    {
        return Thread::query()
            ->whereNull('subject_type')
            ->whereHas('participants', fn ($q) => $q->where('user_id', $userA))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $userB))
            ->has('participants', '=', 2)
            ->first();
    }

    /** Only a participant may read or post; a stranger gets 404, never 403. */
    public function assertParticipant(Thread $thread, int $userId): void
    {
        $thread->loadMissing('participants');

        abort_if($thread->participantFor($userId) === null, 404);
    }

    /**
     * My conversations, most-recently-active first, each with the other
     * participant(s), the last line, and how many messages I have not read.
     */
    public function listFor(User $me): Collection
    {
        $threads = Thread::query()
            ->whereNull('subject_type')
            ->whereHas('participants', fn ($q) => $q->where('user_id', $me->id))
            ->with(['participants.user:id,name', 'messages' => fn ($q) => $q->latest('id')->limit(1)])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();

        $unread = $this->threads->unreadCounts((int) $me->id, $threads->pluck('id')->all());

        return $threads->map(function (Thread $thread) use ($me, $unread) {
            $last = $thread->messages->first();

            return [
                'id' => (int) $thread->id,
                'participants' => $thread->participants
                    ->where('user_id', '!=', (int) $me->id)
                    ->map(fn ($p) => [
                        'user_id' => (int) $p->user_id,
                        'name' => $p->user?->name,
                    ])->values(),
                'last_message' => $last ? [
                    'body' => $last->body,
                    'is_mine' => (int) $last->sender_id === (int) $me->id,
                    'created_at' => optional($last->created_at)->toIso8601String(),
                ] : null,
                'unread_count' => (int) ($unread[$thread->id] ?? 0),
                'last_message_at' => optional($thread->last_message_at)->toIso8601String(),
            ];
        });
    }
}
