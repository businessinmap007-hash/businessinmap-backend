<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Thread;
use App\Models\ThreadParticipant;
use App\Services\Notifications\InAppNotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * A native customer↔business chat tied to one operation (an order or a
 * booking).
 *
 * Why it exists: the trustworthy alternative to a party pasting screenshots
 * from another app, which can be forged. A conversation that happened INSIDE
 * the platform, stored server-side, is evidence an arbitrator can rely on.
 *
 * It is a thin, operation-aware layer over the generic threads system (the
 * same one the dispute room uses): a chat is a thread whose subject is the
 * operation, seating the buyer and the seller. It asks for NO conduct charter
 * (that is a dispute concern) and is kept until RETENTION_DAYS after the
 * operation completes, then locked and made deletable — see the sweep.
 */
class OperationChatService
{
    /** How long a chat is kept after its operation completes. */
    public const RETENTION_DAYS = 7;

    /**
     * How long a party has, after the retention window ends, to delete the
     * chat themselves before it is auto-deleted — so the server does not fill
     * with tens of thousands of finished, valueless conversations. Only ever
     * applies when there is NO dispute (a dispute keeps the chat as evidence).
     */
    public const AUTO_DELETE_GRACE_DAYS = 7;

    /** The operation types a chat can hang off, by the token used in the API. */
    public const TYPES = [
        'order' => Order::class,
        'booking' => Booking::class,
    ];

    public function __construct(
        protected ThreadService $threads,
        protected InAppNotificationService $notifications
    ) {
    }

    /** Resolve a {type,id} pair to its operation model, or 404. */
    public function resolve(string $type, int $id): Model
    {
        $class = self::TYPES[$type] ?? null;

        abort_if($class === null, 404);

        return $class::query()->findOrFail($id);
    }

    /** @return array{0:int,1:int} [clientId, businessId] */
    public function partiesFor(Model $operation): array
    {
        return [(int) $operation->user_id, (int) $operation->business_id];
    }

    /** The caller must be the buyer or the business on the operation. */
    public function assertParty(Model $operation, int $userId): void
    {
        // 404, not 403: a stranger must not learn the operation exists.
        abort_if(! in_array($userId, $this->partiesFor($operation), true), 404);
    }

    /**
     * Open (or return) the chat for an operation, seating both sides. The first
     * time, it says why it is here.
     */
    public function open(Model $operation): Thread
    {
        [$clientId, $businessId] = $this->partiesFor($operation);

        $existed = Thread::query()
            ->where('subject_type', $operation->getMorphClass())
            ->where('subject_id', $operation->getKey())
            ->exists();

        $thread = $this->threads->forSubject($operation, [
            ['user_id' => $clientId, 'role' => ThreadParticipant::ROLE_CLIENT],
            ['user_id' => $businessId, 'role' => ThreadParticipant::ROLE_BUSINESS],
        ], false);

        if (! $existed) {
            $this->threads->system(
                $thread,
                'فُتحت محادثة العملية بين العميل والتاجر. تُحفظ المحادثة كدليل موثوق داخل التطبيق.'
            );
        }

        return $thread;
    }

    /** Whether the operation is finished, which starts the retention clock. */
    public function isComplete(Model $operation): bool
    {
        if ($operation instanceof Booking) {
            return (string) $operation->status === Booking::STATUS_COMPLETED;
        }

        if ($operation instanceof Order) {
            return (string) $operation->status === 'completed';
        }

        return false;
    }

    /**
     * Whether a dispute was raised on this operation. A disputed chat is kept
     * as evidence — never notified for deletion, never auto-deleted.
     */
    public function hasDispute(Model $operation): bool
    {
        return Dispute::query()
            ->where('disputeable_type', $operation->getMorphClass())
            ->where('disputeable_id', $operation->getKey())
            ->exists();
    }

    /**
     * Advance every operation chat's retention state. Idempotent, so it is safe
     * to run on a schedule:
     *   1. a chat whose operation has completed gets its retention deadline set
     *      (kept RETENTION_DAYS more), announced once in the room;
     *   2. a chat past that deadline is locked — read-only, and now listed for
     *      deletion in the admin panel.
     *
     * @return array{stamped:int,locked:int,purged:int}
     */
    public function sweep(): array
    {
        $types = array_values(self::TYPES);
        $stamped = 0;
        $locked = 0;
        $purged = 0;

        // 1) Start the clock on newly-completed operations' chats.
        Thread::query()
            ->whereIn('subject_type', $types)
            ->whereNull('retain_until')
            ->with('subject')
            ->chunkById(200, function ($threads) use (&$stamped) {
                foreach ($threads as $thread) {
                    $operation = $thread->subject;

                    if (! $operation || ! $this->isComplete($operation)) {
                        continue;
                    }

                    $thread->update(['retain_until' => now()->addDays(self::RETENTION_DAYS)]);
                    $this->threads->system(
                        $thread,
                        'اكتملت العملية. تبقى المحادثة متاحة ' . self::RETENTION_DAYS . ' أيام ثم تُغلق.'
                    );
                    $stamped++;
                }
            });

        // 2) The window has passed. A disputed chat stays open — it is evidence.
        //    An undisputed one is locked, and both parties are told they can
        //    delete it now or it will be auto-deleted after the grace window.
        Thread::query()
            ->whereIn('subject_type', $types)
            ->where('status', Thread::STATUS_OPEN)
            ->whereNotNull('retain_until')
            ->where('retain_until', '<=', now())
            ->with('subject', 'participants')
            ->chunkById(200, function ($threads) use (&$locked) {
                foreach ($threads as $thread) {
                    if ($thread->subject && $this->hasDispute($thread->subject)) {
                        continue;
                    }

                    $this->threads->system(
                        $thread,
                        'انتهت مدة هذه المحادثة. يمكنكما حذفها الآن، وإلا فستُحذف تلقائيًّا بعد '
                            . self::AUTO_DELETE_GRACE_DAYS . ' أيام.'
                    );
                    $this->threads->lock($thread);
                    $this->notifyEnding($thread);
                    $locked++;
                }
            });

        // 3) Auto-delete the undisputed chats whose grace window has also
        //    passed — the cleanup that keeps the server from filling with
        //    finished, valueless conversations.
        Thread::query()
            ->whereIn('subject_type', $types)
            ->whereNotNull('retain_until')
            ->where('retain_until', '<=', now()->subDays(self::AUTO_DELETE_GRACE_DAYS))
            ->with('subject')
            ->chunkById(200, function ($threads) use (&$purged) {
                foreach ($threads as $thread) {
                    if ($thread->subject && $this->hasDispute($thread->subject)) {
                        continue;
                    }

                    $this->threads->purge($thread);
                    $purged++;
                }
            });

        return ['stamped' => $stamped, 'locked' => $locked, 'purged' => $purged];
    }

    /**
     * Delete an operation chat at a party's request. Allowed only once it has
     * expired and only when there is no dispute — otherwise it is evidence and
     * is kept. Either party may do it: an undisputed, finished conversation has
     * no value that needs the other's consent to remove.
     */
    public function deleteByParty(Model $operation, int $userId): void
    {
        $this->assertParty($operation, $userId);

        $thread = Thread::query()
            ->where('subject_type', $operation->getMorphClass())
            ->where('subject_id', $operation->getKey())
            ->first();

        if (! $thread) {
            return;
        }

        if (! $thread->isExpired()) {
            throw ValidationException::withMessages([
                'thread' => __('لا يمكن حذف المحادثة قبل انتهاء مدتها.'),
            ]);
        }

        if ($this->hasDispute($operation)) {
            throw ValidationException::withMessages([
                'thread' => __('لا يمكن حذف محادثة عليها نزاع؛ فهي دليل.'),
            ]);
        }

        $this->threads->purge($thread);
    }

    /** Tell both parties the chat has ended and how it will be removed. */
    private function notifyEnding(Thread $thread): void
    {
        foreach ($thread->participants as $participant) {
            try {
                $this->notifications->create([
                    'user_id' => (int) $participant->user_id,
                    'type' => AppNotification::TYPE_SYSTEM,
                    'title_ar' => 'انتهت مدة محادثة العملية',
                    'title_en' => 'Operation chat has ended',
                    'body_ar' => 'يمكنك حذف المحادثة الآن، وإلا فستُحذف تلقائيًّا بعد '
                        . self::AUTO_DELETE_GRACE_DAYS . ' أيام.',
                    'body_en' => 'You can delete it now, or it will be auto-deleted after '
                        . self::AUTO_DELETE_GRACE_DAYS . ' days.',
                    'notifiable_type' => $thread->subject_type,
                    'notifiable_id' => $thread->subject_id !== null ? (int) $thread->subject_id : null,
                    'source_type' => Thread::class,
                    'source_id' => (int) $thread->id,
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
