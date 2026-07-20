<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\ArbitrationSession;
use App\Models\Booking;
use App\Models\Deposit;
use App\Models\Dispute;
use App\Models\OperationGuarantor;
use App\Models\PlatformService;
use App\Models\Thread;
use App\Models\ThreadParticipant;
use App\Services\Guarantees\OperationGuarantorService;
use App\Services\Notifications\InAppNotificationService;
use Illuminate\Validation\ValidationException;

class DisputeService
{
    public function __construct(
        protected DepositsEscrowService $depositsEscrowService,
        protected OperationGuarantorService $operationGuarantors,
        protected InAppNotificationService $notifications,
        protected ThreadService $threads,
    ) {
    }

    public function open(
        int $platformServiceId,
        string $disputeableType,
        int $disputeableId,
        int $openedByUserId,
        ?int $againstUserId = null,
        ?int $actorId = null,
        array $payload = []
    ): Dispute {
        $existing = Dispute::query()
            ->where('platform_service_id', $platformServiceId)
            ->where('disputeable_type', $disputeableType)
            ->where('disputeable_id', $disputeableId)
            ->whereIn('status', [
                Dispute::STATUS_OPEN,
                Dispute::STATUS_MUTUAL_RESOLUTION,
                Dispute::STATUS_UNDER_REVIEW,
            ])
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $policySnapshot = is_array($payload['policy_snapshot'] ?? null)
            ? $payload['policy_snapshot']
            : [];

        $resolutionDays = max((int) (
            data_get($policySnapshot, 'dispute_resolution_days')
            ?? data_get($policySnapshot, 'policy.dispute_resolution_days')
            ?? $payload['dispute_resolution_days']
            ?? 15
        ), 1);

        $warningEveryDays = max((int) (
            data_get($policySnapshot, 'warning_every_days')
            ?? data_get($policySnapshot, 'policy.warning_every_days')
            ?? $payload['warning_every_days']
            ?? 3
        ), 1);

        $now = now();

        $dispute = Dispute::create([
            'platform_service_id' => $platformServiceId,
            'disputeable_type' => $disputeableType,
            'disputeable_id' => $disputeableId,
            'opened_by_user_id' => $openedByUserId,
            'against_user_id' => $againstUserId,

            'status' => Dispute::STATUS_MUTUAL_RESOLUTION,
            'type' => $payload['type'] ?? 'deposit',
            'deposit_id' => isset($payload['deposit_id']) ? (int) $payload['deposit_id'] : null,

            'reason_code' => $payload['reason_code'] ?? null,
            'reason_text' => $payload['reason_text'] ?? null,

            'resolution_type' => null,
            'resolution_payload' => null,

            'opened_at' => $now,
            'mutual_resolution_started_at' => $now,
            'mutual_resolution_deadline_at' => $now->copy()->addDays($resolutionDays),
            'warning_every_days' => $warningEveryDays,
            'last_warning_sent_at' => null,
            'next_warning_at' => $now->copy()->addDays($warningEveryDays),
            'warning_count' => 0,

            'client_cooperated_at' => null,
            'business_cooperated_at' => null,
            'client_non_cooperation_flag' => false,
            'business_non_cooperation_flag' => false,

            'resolved_at' => null,
            'closed_at' => null,
            'resolved_by' => null,

            'meta' => [
                'actor_id' => $actorId,
                'policy_snapshot' => $policySnapshot,
                'source_payload' => $payload,
            ],
        ]);

        // The settlement window is the two parties being asked to agree, so it
        // needs somewhere for them to actually talk. The room opens with the
        // dispute, not when an arbitrator arrives.
        $this->room($dispute);

        return $dispute;
    }

    /**
     * The dispute's conversation, created on first ask, with both parties
     * seated. The arbitrator's seat is added later by joinAsArbitrator().
     */
    public function room(Dispute $dispute): Thread
    {
        $disputeable = $this->resolveDisputeable($dispute);

        // For a booking the sides are known, and the role matters: an
        // arbitrator reading the room must be able to tell who was buying.
        if ($disputeable instanceof Booking) {
            $participants = [
                ['user_id' => (int) $disputeable->user_id, 'role' => ThreadParticipant::ROLE_CLIENT],
                ['user_id' => (int) $disputeable->business_id, 'role' => ThreadParticipant::ROLE_BUSINESS],
            ];
        } else {
            $participants = array_map(
                fn (int $userId) => ['user_id' => $userId, 'role' => ThreadParticipant::ROLE_MEMBER],
                array_values(array_unique(array_filter([
                    (int) $dispute->opened_by_user_id,
                    (int) $dispute->against_user_id,
                ])))
            );
        }

        $existed = Thread::query()
            ->where('subject_type', $dispute->getMorphClass())
            ->where('subject_id', $dispute->getKey())
            ->exists();

        $thread = $this->threads->forSubject($dispute, $participants);

        if (! $existed) {
            $this->threads->system($thread, 'فُتح النزاع. أمامكما مهلة للتوصل إلى حل بالتراضي قبل تحويله إلى التحكيم.');
        }

        return $thread;
    }

    /**
     * A party declaring that they are engaging with the settlement.
     *
     * Explicit, not inferred: the flag this feeds is evidence an arbitrator
     * reads, and inferring cooperation from "sent a message" would let a single
     * hostile line count as good faith. It stays a claim the party made, on the
     * record, at a time — and the room is where it is made, so the other side
     * sees it happen.
     */
    public function recordCooperation(Dispute $dispute, int $userId): Dispute
    {
        $side = $this->sideOf($dispute, $userId);

        if ($side === null) {
            throw ValidationException::withMessages([
                'dispute' => __('لست طرفًا في هذا النزاع.'),
            ]);
        }

        $column = $side . '_cooperated_at';

        if ($dispute->{$column} !== null) {
            return $dispute; // already declared; declaring again changes nothing
        }

        $dispute->{$column} = now();
        $dispute->save();

        $this->threads->system(
            $this->room($dispute),
            $side === 'client'
                ? 'سجّل العميل استعداده للحل بالتراضي.'
                : 'سجّل النشاط استعداده للحل بالتراضي.'
        );

        return $dispute;
    }

    /** Which side of the case this user is on, or null if neither. */
    public function sideOf(Dispute $dispute, int $userId): ?string
    {
        $disputeable = $this->resolveDisputeable($dispute);

        if ($disputeable instanceof Booking) {
            if ((int) $disputeable->user_id === $userId) {
                return 'client';
            }

            if ((int) $disputeable->business_id === $userId) {
                return 'business';
            }

            return null;
        }

        // Outside bookings there are no named sides, so fall back to the two
        // people on the dispute itself.
        if ((int) $dispute->opened_by_user_id === $userId) {
            return 'client';
        }

        if ((int) $dispute->against_user_id === $userId) {
            return 'business';
        }

        return null;
    }

    /**
     * Seat an arbitrator. This is the whole reason the conversation has a
     * participant list instead of two user columns.
     */
    public function joinAsArbitrator(Dispute $dispute, int $arbitratorId): Thread
    {
        $thread = $this->room($dispute);

        // An arbitrator must not be a party to the case they are judging.
        if (in_array($arbitratorId, [
            (int) $dispute->opened_by_user_id,
            (int) $dispute->against_user_id,
        ], true)) {
            throw ValidationException::withMessages([
                'arbitrator' => __('لا يمكن لطرف في النزاع أن يكون محكِّمًا فيه.'),
            ]);
        }

        $already = $thread->participants()
            ->where('user_id', $arbitratorId)
            ->exists();

        $this->threads->addParticipant($thread, $arbitratorId, ThreadParticipant::ROLE_ARBITRATOR);

        if (! $already) {
            $this->threads->system($thread, 'انضم محكِّم إلى الغرفة للفصل في النزاع.');
        }

        return $thread->fresh(['participants']);
    }

    public function openForBooking(
        Booking $booking,
        int $openedByUserId,
        ?int $actorId = null,
        array $payload = []
    ): Dispute {
        $platformServiceId = $this->resolveBookingPlatformServiceId($booking);

        $againstUserId = $openedByUserId === (int) $booking->user_id
            ? (int) $booking->business_id
            : (int) $booking->user_id;

        return $this->open(
            platformServiceId: $platformServiceId,
            disputeableType: Booking::class,
            disputeableId: (int) $booking->id,
            openedByUserId: $openedByUserId,
            againstUserId: $againstUserId,
            actorId: $actorId,
            payload: $payload
        );
    }

    /**
     * Move every dispute whose mutual-resolution window has run out into
     * `under_review`, so an arbitrator has something to pick up.
     *
     * Until this existed the deadline was written on every dispute and read by
     * nobody: an expired window sat in `mutual_resolution` forever, waiting for
     * an admin who was never told.
     *
     * @return array<int, int> ids of the disputes escalated
     */
    public function escalateExpired(?int $limit = 100): array
    {
        $due = Dispute::query()
            ->where('status', Dispute::STATUS_MUTUAL_RESOLUTION)
            ->whereNotNull('mutual_resolution_deadline_at')
            ->where('mutual_resolution_deadline_at', '<=', now())
            ->whereNull('resolved_at')
            ->orderBy('id')
            ->limit(max((int) ($limit ?? 100), 1))
            ->get();

        $escalated = [];

        foreach ($due as $dispute) {
            if ($this->escalate($dispute)) {
                $escalated[] = (int) $dispute->id;
            }
        }

        return $escalated;
    }

    /** The only outcome the parties can reach by themselves. */
    public const RESOLUTION_MUTUAL = 'mutual_settlement';

    /**
     * A party pressing "we agreed". The dispute ends only when BOTH have.
     *
     * Allowed right up to a ruling, including once an arbitrator is already
     * involved: an agreement the two sides reached themselves should always
     * beat a decision imposed on them, and withdrawing it is possible until the
     * second tap lands.
     */
    public function agreeSettlement(Dispute $dispute, int $userId): Dispute
    {
        $side = $this->requireSide($dispute, $userId);

        if (! in_array($dispute->status, [
            Dispute::STATUS_OPEN,
            Dispute::STATUS_MUTUAL_RESOLUTION,
            Dispute::STATUS_UNDER_REVIEW,
        ], true)) {
            throw ValidationException::withMessages([
                'dispute' => __('لا يمكن تسجيل الاتفاق في الحالة الحالية للنزاع.'),
            ]);
        }

        $column = $side . '_settlement_agreed_at';

        if ($dispute->{$column} === null) {
            $dispute->{$column} = now();
            $dispute->save();

            $this->threads->system(
                $this->room($dispute),
                $side === 'client'
                    ? 'سجّل العميل أن الطرفين توصّلا إلى اتفاق. ينتظر تأكيد النشاط.'
                    : 'سجّل النشاط أن الطرفين توصّلا إلى اتفاق. ينتظر تأكيد العميل.'
            );

            $this->notifySettlementProgress($dispute, $side);
        }

        // The second tap is what ends it.
        if ($dispute->client_settlement_agreed_at !== null && $dispute->business_settlement_agreed_at !== null) {
            return $this->resolve($dispute, self::RESOLUTION_MUTUAL);
        }

        return $dispute;
    }

    /**
     * Taking it back, while it still can be taken back.
     *
     * A mis-tap must not become a settlement the moment the other side agrees,
     * so this is open until the second tap lands — after that the dispute is
     * resolved and there is nothing left to withdraw from.
     */
    public function withdrawSettlement(Dispute $dispute, int $userId): Dispute
    {
        $side = $this->requireSide($dispute, $userId);

        if ($dispute->resolved_at !== null) {
            throw ValidationException::withMessages([
                'dispute' => __('صدر القرار بالفعل ولا يمكن التراجع عن الاتفاق.'),
            ]);
        }

        $column = $side . '_settlement_agreed_at';

        if ($dispute->{$column} !== null) {
            $dispute->{$column} = null;
            $dispute->save();

            $this->threads->system(
                $this->room($dispute),
                $side === 'client'
                    ? 'تراجع العميل عن تسجيل الاتفاق.'
                    : 'تراجع النشاط عن تسجيل الاتفاق.'
            );
        }

        return $dispute;
    }

    private function requireSide(Dispute $dispute, int $userId): string
    {
        $side = $this->sideOf($dispute, $userId);

        if ($side === null) {
            throw ValidationException::withMessages([
                'dispute' => __('لست طرفًا في هذا النزاع.'),
            ]);
        }

        return $side;
    }

    /** Tell the OTHER side that they are being asked to confirm. */
    protected function notifySettlementProgress(Dispute $dispute, string $agreedSide): void
    {
        $disputeable = $this->resolveDisputeable($dispute);

        if (! $disputeable instanceof Booking) {
            return;
        }

        $recipient = $agreedSide === 'client'
            ? (int) $disputeable->business_id
            : (int) $disputeable->user_id;

        if ($recipient <= 0) {
            return;
        }

        try {
            $this->notifications->create([
                'user_id' => $recipient,
                'type' => AppNotification::TYPE_DISPUTE,
                'priority' => AppNotification::PRIORITY_HIGH,
                'title_ar' => 'الطرف الآخر سجّل الاتفاق',
                'title_en' => 'The other party marked this as agreed',
                'body_ar' => 'أكّد الاتفاق لإنهاء النزاع وفكّ مبلغ الضمان.',
                'body_en' => 'Confirm to close the dispute and release the escrow.',
                'notifiable_type' => Dispute::class,
                'notifiable_id' => (int) $dispute->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * A party asking for a judge, instead of waiting out the window.
     *
     * One party is enough. Requiring both to agree to arbitrate would let a
     * stonewaller block it forever — which is precisely the situation
     * arbitration exists for. What IS required is that the asker declared
     * cooperation first: you have to show up before you can demand a judge, and
     * that single tap is also what stops a dispute being opened and escalated
     * in the same breath.
     */
    public function requestArbitration(Dispute $dispute, int $userId): Dispute
    {
        $side = $this->sideOf($dispute, $userId);

        if ($side === null) {
            throw ValidationException::withMessages([
                'dispute' => __('لست طرفًا في هذا النزاع.'),
            ]);
        }

        if ($dispute->status === Dispute::STATUS_UNDER_REVIEW) {
            return $dispute; // already with a judge
        }

        if ($dispute->status !== Dispute::STATUS_MUTUAL_RESOLUTION) {
            throw ValidationException::withMessages([
                'dispute' => __('لا يمكن طلب التحكيم في الحالة الحالية للنزاع.'),
            ]);
        }

        if ($dispute->{$side . '_cooperated_at'} === null) {
            throw ValidationException::withMessages([
                'cooperation' => __('سجّل استعدادك للحل بالتراضي أولًا قبل طلب التحكيم.'),
            ]);
        }

        $this->escalate($dispute, 'party_request', $userId);

        return $dispute->fresh();
    }

    /**
     * Hand a single dispute to review and tell both parties it happened.
     *
     * The non-cooperation flags are set ONLY when the window actually ran out.
     * On a party's own request the window is still open, so marking the other
     * side as uncooperative would punish them for a deadline that has not
     * passed — and either way the flag is a mark an arbitrator reads, never a
     * charge.
     */
    public function escalate(Dispute $dispute, string $reason = 'deadline', ?int $requestedBy = null): bool
    {
        if ($dispute->status !== Dispute::STATUS_MUTUAL_RESOLUTION) {
            return false;
        }

        $byDeadline = $reason === 'deadline';

        $meta = is_array($dispute->meta ?? null) ? $dispute->meta : [];
        $meta['escalated_at'] = now()->toIso8601String();
        $meta['escalated_by'] = $byDeadline
            ? 'schedule:mutual_resolution_expired'
            : 'party_request';

        if ($requestedBy !== null) {
            $meta['escalation_requested_by'] = $requestedBy;
        }

        $dispute->status = Dispute::STATUS_UNDER_REVIEW;
        $dispute->meta = $meta;

        if ($byDeadline) {
            // Now that a party CAN declare cooperation, its absence means
            // something and is worth recording. This is a mark for the
            // arbitrator to read — it charges nothing. Nothing computes the
            // non-cooperation fee from it, and it must stay that way: a fee
            // levied automatically for missing a deadline would punish someone
            // who was simply not reading their phone.
            $dispute->client_non_cooperation_flag = $dispute->client_cooperated_at === null;
            $dispute->business_non_cooperation_flag = $dispute->business_cooperated_at === null;
        }

        $dispute->save();

        // Said in the room as well as pushed: the parties read the case here,
        // and a notification they dismiss should not erase the record of why
        // the case moved.
        $this->threads->system(
            $this->room($dispute),
            $byDeadline
                ? 'انتهت مهلة الحل بالتراضي وتم تحويل النزاع إلى التحكيم.'
                : 'طلب أحد الطرفين إحالة النزاع إلى التحكيم.'
        );

        $this->notifyEscalation($dispute, $byDeadline);

        return true;
    }

    protected function notifyEscalation(Dispute $dispute, bool $byDeadline = true): void
    {
        $recipients = array_values(array_unique(array_filter([
            (int) ($dispute->opened_by_user_id ?? 0),
            (int) ($dispute->against_user_id ?? 0),
        ])));

        foreach ($recipients as $userId) {
            try {
                $this->notifications->create([
                    'user_id' => $userId,
                    'type' => AppNotification::TYPE_DISPUTE,
                    'priority' => AppNotification::PRIORITY_HIGH,
                    'title_ar' => $byDeadline ? 'انتهت مهلة الحل بالتراضي' : 'طُلب التحكيم في النزاع',
                    'title_en' => $byDeadline ? 'The settlement window has closed' : 'Arbitration was requested',
                    'body_ar' => $byDeadline
                        ? 'تم تحويل النزاع إلى المراجعة للفصل فيه.'
                        : 'طلب أحد الطرفين إحالة النزاع إلى التحكيم للفصل فيه.',
                    'body_en' => $byDeadline
                        ? 'The dispute has been moved to review for a ruling.'
                        : 'A party asked for the dispute to be decided by an arbitrator.',
                    'notifiable_type' => Dispute::class,
                    'notifiable_id' => (int) $dispute->id,
                ]);
            } catch (\Throwable $e) {
                // A failed notification must not roll back the escalation: the
                // status change is the part that matters.
                report($e);
            }
        }
    }

    public function resolve(
        Dispute $dispute,
        string $resolutionType,
        array $resolutionPayload = [],
        ?int $actorId = null
    ): Dispute {
        if (! in_array($dispute->status, [
            Dispute::STATUS_OPEN,
            Dispute::STATUS_MUTUAL_RESOLUTION,
            Dispute::STATUS_UNDER_REVIEW,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => __('الحالة الحالية للنزاع لا تسمح بتنفيذ القرار.'),
            ]);
        }

        $disputeable = $this->resolveDisputeable($dispute);

        if ($disputeable instanceof Booking) {
            $this->resolveBookingDispute(
                booking: $disputeable,
                resolutionType: $resolutionType,
                resolutionPayload: $resolutionPayload
            );

            // Dispute over: return the frozen guarantee coverage (client's own +
            // any friend co-guarantors) — it was held throughout the dispute and
            // is never charged.
            $this->operationGuarantors->releaseOperation(OperationGuarantor::OP_BOOKING, (int) $disputeable->id);
        }

        $payload = is_array($dispute->resolution_payload ?? null)
            ? $dispute->resolution_payload
            : [];

        $payload['resolved_by'] = $actorId;
        $payload['resolution_payload'] = $resolutionPayload;

        $dispute->resolution_type = $resolutionType;
        $dispute->resolution_payload = $payload;
        $dispute->status = Dispute::STATUS_RESOLVED;
        $dispute->resolved_by = $actorId;
        $dispute->resolved_at = now();
        $dispute->save();

        // Written by the ruling, not by the ruler: nobody decides whether their
        // own case gets into the record.
        $session = app(ArbitrationService::class)
            ->recordSession($dispute, $resolutionType, $resolutionPayload, $actorId);

        $this->closeRoom($dispute, $resolutionType, $resolutionPayload);
        $this->notifyRuling($dispute, $session);

        return $dispute;
    }

    /**
     * Tell both parties how it ended.
     *
     * The room announces the ruling too, but a system message notifies nobody
     * by design — so without this a party who never opens the room has money
     * taken or returned and is told nothing at all. Escalation notifies; the
     * decision that actually moves the money must not be quieter than it.
     */
    protected function notifyRuling(Dispute $dispute, ?ArbitrationSession $session): void
    {
        $disputeable = $this->resolveDisputeable($dispute);

        // Who is on which side, so each party is told what THEY received rather
        // than a neutral summary they have to decode.
        $clientId = $disputeable instanceof Booking ? (int) $disputeable->user_id : (int) $dispute->opened_by_user_id;
        $businessId = $disputeable instanceof Booking ? (int) $disputeable->business_id : (int) $dispute->against_user_id;

        $toClient = round((float) ($session?->amount_to_client ?? 0), 2);
        $toBusiness = round((float) ($session?->amount_to_business ?? 0), 2);

        $recipients = [
            $clientId => $toClient,
            $businessId => $toBusiness,
        ];

        foreach ($recipients as $userId => $amount) {
            if ($userId <= 0) {
                continue;
            }

            try {
                $this->notifications->create([
                    'user_id' => $userId,
                    'type' => AppNotification::TYPE_DISPUTE,
                    'priority' => AppNotification::PRIORITY_HIGH,
                    'title_ar' => 'صدر قرار في النزاع',
                    'title_en' => 'A ruling was issued on the dispute',
                    'body_ar' => $amount > 0
                        ? 'تمت تسوية النزاع، وحُوّل إلى محفظتك مبلغ ' . number_format($amount, 2) . '.'
                        : 'تمت تسوية النزاع دون تحويل مبلغ إلى محفظتك.',
                    'body_en' => $amount > 0
                        ? 'The dispute was settled and ' . number_format($amount, 2) . ' was moved to your wallet.'
                        : 'The dispute was settled with no amount moved to your wallet.',
                    'notifiable_type' => Dispute::class,
                    'notifiable_id' => (int) $dispute->id,
                ]);
            } catch (\Throwable $e) {
                // The ruling already moved real money; a failed notification
                // must not undo it.
                report($e);
            }
        }
    }

    /**
     * The ruling is announced in the room, then the room is sealed. Locking
     * after the announcement, not before, is what lets the last word be the
     * decision itself rather than silence.
     */
    protected function closeRoom(Dispute $dispute, string $resolutionType, array $resolutionPayload): void
    {
        try {
            $thread = $this->room($dispute);

            $this->threads->system($thread, match ($resolutionType) {
                self::RESOLUTION_MUTUAL => 'اتفق الطرفان على إنهاء النزاع، وأُعيد مبلغ الضمان إلى صاحبه.',
                'refund_client' => 'صدر القرار: إعادة مبلغ الضمان إلى العميل.',
                'release_business' => 'صدر القرار: تسليم مبلغ الضمان إلى النشاط.',
                'split' => 'صدر القرار: تقسيم مبلغ الضمان بنسبة '
                    . (float) ($resolutionPayload['client_percent'] ?? 0) . '% للعميل و'
                    . (float) ($resolutionPayload['business_percent'] ?? 0) . '% للنشاط.',
                default => 'صدر القرار وأُغلق النزاع.',
            });

            $this->threads->lock($thread);
        } catch (\Throwable $e) {
            // The ruling already moved real money. A failure to narrate it must
            // not undo that.
            report($e);
        }
    }

    protected function resolveBookingDispute(
        Booking $booking,
        string $resolutionType,
        array $resolutionPayload = []
    ): void {
        $deposit = Deposit::query()
            ->where('target_type', Booking::class)
            ->where('target_id', (int) $booking->id)
            ->orderByDesc('id')
            ->first();

        if (! $deposit) {
            throw ValidationException::withMessages([
                'deposit' => __('لا يوجد Deposit مرتبط بهذا الحجز.'),
            ]);
        }

        if ($deposit->isFinal()) {
            return;
        }

        switch ($resolutionType) {
            // A ruling AWARDS the escrow — the loser's hold ends up with the
            // winner. release()/refund() only unwind it, each hold back to
            // whoever posted it, which is right for a booking that completed
            // normally and wrong for a case somebody lost.
            case 'release_business':
                $this->depositsEscrowService->awardTo($deposit, 'business');
                break;

            case 'refund_client':
                $this->depositsEscrowService->awardTo($deposit, 'client');
                break;

            case self::RESOLUTION_MUTUAL:
                // Each side gets its OWN hold back. The deposit is a guarantee,
                // not a payment: when the parties settle it between themselves
                // the guarantee has done its job and simply unwinds. Any money
                // that changed hands as part of their agreement is theirs to
                // move, and is recorded separately.
                $this->depositsEscrowService->refund($deposit, true, true);
                break;

            case 'no_action':
                break;

            case 'split':
                $clientPercent = (float) ($resolutionPayload['client_percent'] ?? 0);
                $businessPercent = (float) ($resolutionPayload['business_percent'] ?? 0);

                if (round($clientPercent + $businessPercent, 2) !== 100.00) {
                    throw ValidationException::withMessages([
                        'split' => __('مجموع النسب يجب أن يساوي 100%.'),
                    ]);
                }

                $this->depositsEscrowService->split($deposit, $clientPercent, $businessPercent);
                break;

            default:
                throw ValidationException::withMessages([
                    'resolution_type' => __('نوع القرار غير مدعوم.'),
                ]);
        }
    }

    protected function resolveBookingPlatformServiceId(Booking $booking): int
    {
        if ((int) $booking->service_id > 0) {
            return (int) $booking->service_id;
        }

        $platformService = PlatformService::query()
            ->where('key', 'booking')
            ->first();

        if (! $platformService) {
            throw ValidationException::withMessages([
                'platform_service' => __('تعذر تحديد Platform Service الخاص بالحجوزات.'),
            ]);
        }

        return (int) $platformService->id;
    }

    protected function resolveDisputeable(Dispute $dispute): mixed
    {
        if (
            ! empty($dispute->disputeable_type) &&
            ! empty($dispute->disputeable_id) &&
            class_exists($dispute->disputeable_type)
        ) {
            return $dispute->disputeable_type::find($dispute->disputeable_id);
        }

        return null;
    }
}