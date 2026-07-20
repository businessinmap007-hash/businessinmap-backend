<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\ConductViolation;
use App\Models\Thread;
use App\Models\ThreadMessage;
use App\Models\ThreadParticipant;
use App\Models\User;
use App\Services\Notifications\InAppNotificationService;
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
    public function __construct(
        protected InAppNotificationService $notifications
    ) {
    }

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

    /**
     * The conduct rules, and their version.
     *
     * Bumping the version invalidates every existing acceptance, on purpose: a
     * rewritten charter is a different promise, and someone who agreed to the
     * old wording has not agreed to the new one.
     */
    public const CONDUCT_VERSION = 2;

    /**
     * ONE document in two sections, accepted once.
     *
     * Conduct and arbitration terms could have been two charters with two
     * acceptances, two versions and two screens — and then the day one is
     * updated and the other is not, nobody can say what a given party actually
     * agreed to. One version number over one text is the only thing that stays
     * answerable.
     */
    public function conductCharter(): array
    {
        return [
            'version' => self::CONDUCT_VERSION,
            'title' => __('شروط غرفة النزاع والتحكيم'),
            'sections' => [
                [
                    'title' => __('قواعد السلوك'),
                    'clauses' => [
                        __('التزم بالموضوع: اذكر ما حدث والأدلة، لا أوصاف الطرف الآخر.'),
                        __('التعدي بالألفاظ أو الإهانة أو التهديد مخالفة يقدّرها المحكّم.'),
                        __('قد تخسر الجلسة بسبب المخالفة حتى لو كان الحق معك في أصل النزاع.'),
                        __('قد تُفرض عليك غرامة منصة بسبب المخالفة، مستقلة عن نتيجة النزاع.'),
                        __('محتوى الغرفة محفوظ بالكامل ولا يُحذف، ويُستخدم كدليل عند الفصل.'),
                    ],
                ],
                [
                    'title' => __('شروط التحكيم'),
                    'clauses' => [
                        __('قرار المحكّم نهائي ومُلزم للطرفين، ويُنفَّذ على مبلغ الضمان مباشرة.'),
                        __('للمحكّم أن يحكم بتعويض يُدفع من محفظة الطرف الخاسر إلى الطرف الآخر.'),
                        __('رسم الجلسة يتحمله الطرف الخاسر وحده، ويُعلَن مقداره قبل بدء النظر.'),
                        __('عدم الخضوع للحكم أو الامتناع عن سداد ما حُكم به يُعرِّضك لغرامة منصة.'),
                        __('من لا يقبل هذه الشروط يسقط حقه في المرافعة، ويحكم المحكّم بما حضر أمامه من أدلة.'),
                    ],
                ],
            ],
        ];
    }

    /** Has this person agreed to the CURRENT charter? */
    public function hasAcceptedConduct(Thread $thread, int $userId): bool
    {
        $seat = $thread->participantFor($userId);

        return $seat !== null
            && $seat->conduct_accepted_at !== null
            && (int) $seat->conduct_version >= self::CONDUCT_VERSION;
    }

    public function acceptConduct(Thread $thread, int $userId): ThreadParticipant
    {
        $thread->loadMissing('participants');

        $seat = $thread->participantFor($userId);

        if (! $seat) {
            throw ValidationException::withMessages([
                'thread' => __('لست طرفًا في هذه المحادثة.'),
            ]);
        }

        $seat->update([
            'conduct_accepted_at' => now(),
            'conduct_version' => self::CONDUCT_VERSION,
            // Accepting after a refusal is allowed: someone who reads it again
            // and changes their mind should get their voice back.
            'conduct_declined_at' => null,
        ]);

        return $seat->fresh();
    }

    /**
     * Refusing the terms.
     *
     * Recorded rather than inferred from silence, because refusing and not
     * having opened the app are different facts and an arbitrator weighs them
     * differently. The consequence is losing the right to argue — the room
     * closes to you and the arbitrator rules on what is in front of them. It is
     * NOT an automatic loss: nobody wins a case because the other side went
     * quiet, and a default that harsh would fall on whoever simply was not
     * reading their phone.
     */
    public function declineConduct(Thread $thread, int $userId): ThreadParticipant
    {
        $thread->loadMissing('participants');

        $seat = $thread->participantFor($userId);

        if (! $seat) {
            throw ValidationException::withMessages([
                'thread' => __('لست طرفًا في هذه المحادثة.'),
            ]);
        }

        $seat->update([
            'conduct_declined_at' => now(),
            'conduct_accepted_at' => null,
            'conduct_version' => null,
        ]);

        $this->system($thread, 'رفض أحد الأطراف شروط التحكيم، وسقط حقه في المرافعة.');

        return $seat->fresh();
    }

    /**
     * Record that someone broke the rules they agreed to.
     *
     * A row pointing at a message, not a counter: the party must be able to see
     * exactly what is held against them, and a ruling that cannot be argued
     * with is not a ruling. Recording it is all this does — no automatic loss,
     * no automatic fine. The charter is consent to the arbitrator's JUDGEMENT,
     * not to a machine deciding what counts as an insult.
     */
    public function recordViolation(
        Thread $thread,
        int $againstUserId,
        int $recordedByUserId,
        string $reason,
        ?int $messageId = null
    ): ConductViolation {
        $thread->loadMissing('participants');

        if (! $thread->participantFor($againstUserId)) {
            throw ValidationException::withMessages([
                'against_user_id' => __('هذا المستخدم ليس طرفًا في هذه المحادثة.'),
            ]);
        }

        if ($messageId !== null) {
            $belongs = ThreadMessage::query()
                ->whereKey($messageId)
                ->where('thread_id', $thread->id)
                ->exists();

            if (! $belongs) {
                throw ValidationException::withMessages([
                    'thread_message_id' => __('الرسالة المحددة ليست في هذه المحادثة.'),
                ]);
            }
        }

        $violation = ConductViolation::create([
            'thread_id' => (int) $thread->id,
            'thread_message_id' => $messageId,
            'against_user_id' => $againstUserId,
            'recorded_by_user_id' => $recordedByUserId,
            'reason' => trim($reason),
        ]);

        // Said out loud in the room. A mark recorded in silence is one the
        // party first learns about from the ruling.
        $this->system($thread, 'سجّل المحكّم مخالفة سلوك على أحد الأطراف: ' . trim($reason));

        return $violation;
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, ConductViolation> */
    public function violations(Thread $thread)
    {
        return ConductViolation::query()
            ->with(['against:id,name', 'recordedBy:id,name'])
            ->where('thread_id', $thread->id)
            ->orderByDesc('id')
            ->get();
    }

    /** A message from a person. Refused if they are not seated, or it is locked. */
    public function post(Thread $thread, int $senderId, string $body): ThreadMessage
    {
        $thread->loadMissing('participants');

        $seat = $thread->participantFor($senderId);

        if (! $seat) {
            throw ValidationException::withMessages([
                'thread' => __('لست طرفًا في هذه المحادثة.'),
            ]);
        }

        // The charter binds the parties, not the arbitrator: its clauses are
        // about losing the case and being fined, neither of which can happen to
        // the person deciding it. Staff conduct is a staffing matter.
        if ($seat->role !== ThreadParticipant::ROLE_ARBITRATOR && ! $this->hasAcceptedConduct($thread, $senderId)) {
            throw ValidationException::withMessages([
                'conduct' => __('لا بد من الموافقة على قواعد السلوك قبل الكتابة في الغرفة.'),
            ]);
        }

        if ($thread->isLocked()) {
            throw ValidationException::withMessages([
                'thread' => __('أُغلقت هذه المحادثة ولا يمكن إضافة رسائل إليها.'),
            ]);
        }

        $message = $this->write($thread, $senderId, ThreadMessage::KIND_MESSAGE, $body);

        $this->notifyOthers($thread, $message);

        return $message;
    }

    /**
     * Tell everyone else in the room that something was said.
     *
     * The notification points at the thread's SUBJECT, not the thread: the app
     * has a dispute screen, not a thread screen, and this is the only place
     * ThreadService needs to know that a thread is about something.
     */
    private function notifyOthers(Thread $thread, ThreadMessage $message): void
    {
        $senderName = User::query()->whereKey($message->sender_id)->value('name');

        // A long message becomes a preview; the room holds the full text.
        $preview = mb_substr($message->body, 0, 120)
            . (mb_strlen($message->body) > 120 ? '…' : '');

        foreach ($thread->participants as $participant) {
            if ((int) $participant->user_id === (int) $message->sender_id) {
                continue;
            }

            try {
                $this->notifications->create([
                    'user_id' => (int) $participant->user_id,
                    'actor_id' => (int) $message->sender_id,
                    'type' => AppNotification::TYPE_MESSAGE,
                    'title_ar' => 'رسالة جديدة من ' . ($senderName ?: 'أحد الأطراف'),
                    'title_en' => 'New message from ' . ($senderName ?: 'a participant'),
                    'body_ar' => $preview,
                    'body_en' => $preview,
                    'notifiable_type' => $thread->subject_type,
                    'notifiable_id' => $thread->subject_id !== null ? (int) $thread->subject_id : null,
                    'source_type' => Thread::class,
                    'source_id' => (int) $thread->id,
                ]);
            } catch (\Throwable $e) {
                // The message is already stored. A failed notification must not
                // undo someone's words.
                report($e);
            }
        }
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
