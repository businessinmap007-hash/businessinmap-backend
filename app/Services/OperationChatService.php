<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Order;
use App\Models\Thread;
use App\Models\ThreadParticipant;
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

    /** The operation types a chat can hang off, by the token used in the API. */
    public const TYPES = [
        'order' => Order::class,
        'booking' => Booking::class,
    ];

    public function __construct(
        protected ThreadService $threads
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
     * Advance every operation chat's retention state. Idempotent, so it is safe
     * to run on a schedule:
     *   1. a chat whose operation has completed gets its retention deadline set
     *      (kept RETENTION_DAYS more), announced once in the room;
     *   2. a chat past that deadline is locked — read-only, and now listed for
     *      deletion in the admin panel.
     *
     * @return array{stamped:int,locked:int}
     */
    public function sweep(): array
    {
        $types = array_values(self::TYPES);
        $stamped = 0;
        $locked = 0;

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

        // 2) Lock the ones whose window has passed.
        Thread::query()
            ->whereIn('subject_type', $types)
            ->where('status', Thread::STATUS_OPEN)
            ->whereNotNull('retain_until')
            ->where('retain_until', '<=', now())
            ->chunkById(200, function ($threads) use (&$locked) {
                foreach ($threads as $thread) {
                    $this->threads->system($thread, 'انتهت مدة حفظ هذه المحادثة وأصبحت للقراءة فقط.');
                    $this->threads->lock($thread);
                    $locked++;
                }
            });

        return ['stamped' => $stamped, 'locked' => $locked];
    }
}
