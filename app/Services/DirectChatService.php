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
            ->whereNull('created_by') // a DM, never a two-person group
            ->whereHas('participants', fn ($q) => $q->where('user_id', $userA))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $userB))
            ->has('participants', '=', 2)
            ->first();
    }

    /** How many people one group may hold. */
    public const MAX_GROUP_MEMBERS = 50;

    /**
     * Create a group: a titled, owned, subjectless thread seating the creator
     * plus the given members. The creator owns it (rename / add / delete).
     *
     * @param  list<int>  $memberIds  other users to seat (self is ignored)
     */
    public function createGroup(User $creator, string $title, array $memberIds): Thread
    {
        $title = trim($title);

        if ($title === '') {
            throw ValidationException::withMessages(['title' => __('اسم المجموعة مطلوب.')]);
        }

        $others = collect($memberIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $id !== (int) $creator->id)
            ->unique()
            ->values();

        if ($others->isEmpty()) {
            throw ValidationException::withMessages(['user_ids' => __('أضف عضوًا واحدًا على الأقل.')]);
        }

        // Every named member must be a real user.
        $existing = User::query()->whereIn('id', $others)->pluck('id')->map(fn ($id) => (int) $id);
        $missing = $others->diff($existing);

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(['user_ids' => __('بعض الأعضاء غير موجودين.')]);
        }

        if ($others->count() + 1 > self::MAX_GROUP_MEMBERS) {
            throw ValidationException::withMessages(['user_ids' => __('عدد الأعضاء يتجاوز الحد المسموح.')]);
        }

        $thread = Thread::create([
            'title' => mb_substr($title, 0, 120),
            'created_by' => (int) $creator->id,
            'status' => Thread::STATUS_OPEN,
            'requires_conduct' => false,
        ]);

        $this->threads->addParticipant($thread, (int) $creator->id, ThreadParticipant::ROLE_MEMBER);
        foreach ($others as $id) {
            $this->threads->addParticipant($thread, $id, ThreadParticipant::ROLE_MEMBER);
        }

        $this->threads->system($thread, 'أنشأ ' . ($creator->name ?: 'عضو') . ' المجموعة «' . $thread->title . '».');

        return $thread->load('participants');
    }

    /** Only the group's owner may add members. */
    public function addMember(Thread $thread, User $actor, int $userId): ThreadParticipant
    {
        $this->assertGroupOwner($thread, (int) $actor->id);

        User::query()->findOrFail($userId);

        if ($thread->participants()->count() >= self::MAX_GROUP_MEMBERS) {
            throw ValidationException::withMessages(['user_id' => __('عدد الأعضاء يتجاوز الحد المسموح.')]);
        }

        $already = $thread->participants()->where('user_id', $userId)->exists();
        $seat = $this->threads->addParticipant($thread, $userId, ThreadParticipant::ROLE_MEMBER);

        if (! $already) {
            $name = User::query()->whereKey($userId)->value('name');
            $this->threads->system($thread, 'أُضيف ' . ($name ?: 'عضو') . ' إلى المجموعة.');
        }

        return $seat;
    }

    /**
     * Leave a group. Deleting the seat is fine here — a personal group is not
     * evidence, unlike a dispute room where nobody leaves. When the last member
     * leaves, the empty group is purged rather than left dangling.
     */
    public function leave(Thread $thread, User $actor): void
    {
        if (! $thread->isGroup()) {
            throw ValidationException::withMessages(['thread' => __('لا يمكن مغادرة هذه المحادثة.')]);
        }

        $seat = $thread->participants()->where('user_id', $actor->id)->first();

        if (! $seat) {
            abort(404);
        }

        $seat->delete();

        if ($thread->participants()->count() === 0) {
            $this->threads->purge($thread);

            return;
        }

        $this->threads->system($thread, 'غادر ' . ($actor->name ?: 'عضو') . ' المجموعة.');
    }

    private function assertGroupOwner(Thread $thread, int $userId): void
    {
        abort_if(! $thread->isGroup(), 404);

        if (! $thread->isOwnedBy($userId)) {
            throw ValidationException::withMessages(['thread' => __('لا يملك هذا الإجراء إلا منشئ المجموعة.')]);
        }
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
                'type' => $thread->isGroup() ? 'group' : 'direct',
                'title' => $thread->title,
                // For a DM this is the one other person; for a group, everyone
                // but me.
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
