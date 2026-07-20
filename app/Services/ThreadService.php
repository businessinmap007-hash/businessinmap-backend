<?php

namespace App\Services;

use App\Models\Thread;
use App\Models\ThreadMessage;
use App\Models\ThreadParticipant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Conversations with a participant list — the shape a third-party arbitrator
 * needs and the legacy 1-to-1 `conversations` table cannot hold.
 *
 * Nothing here knows about disputes. A dispute room is a thread whose subject
 * is a Dispute; DisputeService decides when one is opened, who joins and when
 * it locks. Keeping that knowledge out of here is what stops this from becoming
 * a dispute-only messaging system that a second one has to be built beside.
 */
class ThreadService
{
    /**
     * The thread for a subject, created on first ask.
     *
     * @param  array<int, array{user_id: int, role: string}>  $participants
     */
    public function forSubject(Model $subject, array $participants = []): Thread
    {
        return DB::transaction(function () use ($subject, $participants) {
            $thread = Thread::query()
                ->where('subject_type', $subject->getMorphClass())
                ->where('subject_id', $subject->getKey())
                ->lockForUpdate()
                ->first();

            if (! $thread) {
                $thread = Thread::create([
                    'subject_type' => $subject->getMorphClass(),
                    'subject_id' => $subject->getKey(),
                    'status' => Thread::STATUS_OPEN,
                ]);
            }

            foreach ($participants as $participant) {
                $this->addParticipant($thread, (int) $participant['user_id'], $participant['role']);
            }

            return $thread->fresh(['participants']);
        });
    }

    /**
     * Seat someone, or return the seat they already have.
     *
     * The role is NOT updated for an existing participant: a client who is
     * later also an admin must not silently become the arbitrator of their own
     * dispute.
     */
    public function addParticipant(Thread $thread, int $userId, string $role): ThreadParticipant
    {
        $existing = ThreadParticipant::query()
            ->where('thread_id', $thread->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            return $existing;
        }

        return ThreadParticipant::create([
            'thread_id' => (int) $thread->id,
            'user_id' => $userId,
            'role' => $role,
            'joined_at' => now(),
        ]);
    }

    /** A message from a person. Refused if they are not seated, or it is locked. */
    public function post(Thread $thread, int $senderId, string $body): ThreadMessage
    {
        $thread->loadMissing('participants');

        if (! $thread->participantFor($senderId)) {
            throw ValidationException::withMessages([
                'thread' => __('لست طرفًا في هذه المحادثة.'),
            ]);
        }

        if ($thread->isLocked()) {
            throw ValidationException::withMessages([
                'thread' => __('أُغلقت هذه المحادثة ولا يمكن إضافة رسائل إليها.'),
            ]);
        }

        return $this->write($thread, $senderId, ThreadMessage::KIND_MESSAGE, $body);
    }

    /**
     * The platform narrating what happened, in the same stream the parties
     * read. Allowed on a locked thread on purpose: the ruling that locks a room
     * is itself the last thing that must be said in it.
     */
    public function system(Thread $thread, string $body): ThreadMessage
    {
        return $this->write($thread, null, ThreadMessage::KIND_SYSTEM, $body);
    }

    public function lock(Thread $thread): Thread
    {
        if (! $thread->isLocked()) {
            $thread->update(['status' => Thread::STATUS_LOCKED, 'locked_at' => now()]);
        }

        return $thread;
    }

    public function markRead(Thread $thread, int $userId): void
    {
        ThreadParticipant::query()
            ->where('thread_id', $thread->id)
            ->where('user_id', $userId)
            ->update(['last_read_at' => now()]);
    }

    /** Messages the caller has not read yet, per thread id. */
    public function unreadCounts(int $userId, array $threadIds): array
    {
        if ($threadIds === []) {
            return [];
        }

        $seats = ThreadParticipant::query()
            ->where('user_id', $userId)
            ->whereIn('thread_id', $threadIds)
            ->get()
            ->keyBy('thread_id');

        $counts = [];

        foreach ($threadIds as $threadId) {
            $seat = $seats->get($threadId);

            $counts[$threadId] = ThreadMessage::query()
                ->where('thread_id', $threadId)
                // Your own messages are not unread news to you.
                ->where(fn ($q) => $q->whereNull('sender_id')->orWhere('sender_id', '!=', $userId))
                ->when(
                    $seat?->last_read_at,
                    fn ($q) => $q->where('created_at', '>', $seat->last_read_at)
                )
                ->count();
        }

        return $counts;
    }

    private function write(Thread $thread, ?int $senderId, string $kind, string $body): ThreadMessage
    {
        $body = trim($body);

        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => __('لا يمكن إرسال رسالة فارغة.'),
            ]);
        }

        return DB::transaction(function () use ($thread, $senderId, $kind, $body) {
            $message = ThreadMessage::create([
                'thread_id' => (int) $thread->id,
                'sender_id' => $senderId,
                'kind' => $kind,
                'body' => $body,
            ]);

            $thread->update(['last_message_at' => $message->created_at]);

            return $message;
        });
    }
}
