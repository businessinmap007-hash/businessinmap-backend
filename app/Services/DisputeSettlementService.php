<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\DisputeSettlement;
use App\Models\User;
use App\Services\Notifications\InAppNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Money the parties moved between themselves, off the platform.
 *
 * The platform cannot verify a bank transfer or cash in a hand, so it does not
 * pretend to. What it records is three statements by three different acts:
 * someone proposes a figure, the other side accepts that this is the deal, and
 * the RECEIVER confirms it arrived. Only the receiver may make that last one —
 * a payer confirming their own payment proves nothing, and letting them would
 * turn the whole record into an assertion by one person.
 *
 * The receipt is what ends the dispute. Three deliberate acts, one of them by
 * the party who stood to lose by lying, is stronger evidence of agreement than
 * the two-tap settlement, so it closes the case on its own.
 */
class DisputeSettlementService
{
    public function __construct(
        protected DisputeService $disputes,
        protected ThreadService $threads,
        protected InAppNotificationService $notifications,
    ) {
    }

    /** The proposal currently on the table, if any. */
    public function current(Dispute $dispute): ?DisputeSettlement
    {
        return DisputeSettlement::query()
            ->where('dispute_id', $dispute->id)
            ->whereIn('status', DisputeSettlement::LIVE_STATUSES)
            ->latest('id')
            ->first();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, DisputeSettlement> */
    public function history(Dispute $dispute)
    {
        return DisputeSettlement::query()
            ->where('dispute_id', $dispute->id)
            ->orderByDesc('id')
            ->get();
    }

    public function propose(
        Dispute $dispute,
        int $userId,
        string $payerSide,
        float $amount,
        ?string $method = null,
        ?string $note = null
    ): DisputeSettlement {
        $this->ensureLive($dispute);

        $role = $this->roleOf($dispute, $userId);

        if (! in_array($payerSide, ['client', 'business'], true)) {
            throw ValidationException::withMessages([
                'payer_side' => __('يجب تحديد الطرف الذي يدفع.'),
            ]);
        }

        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('قيمة المبلغ غير صالحة.'),
            ]);
        }

        if ($this->current($dispute)) {
            throw ValidationException::withMessages([
                'settlement' => __('يوجد مقترح تسوية قائم — لا بد من قبوله أو رفضه أولًا.'),
            ]);
        }

        return DB::transaction(function () use ($dispute, $userId, $role, $payerSide, $amount, $method, $note) {
            $settlement = DisputeSettlement::create([
                'dispute_id' => (int) $dispute->id,
                'proposed_by_user_id' => $userId,
                'proposed_by_role' => $role,
                'payer_side' => $payerSide,
                'amount' => $amount,
                'method' => $method,
                'note' => $note,
                'status' => DisputeSettlement::STATUS_PROPOSED,
            ]);

            $this->say($dispute, sprintf(
                'مقترح تسوية: يدفع %s مبلغ %s خارج التطبيق.',
                $payerSide === 'client' ? 'العميل' : 'النشاط',
                number_format($amount, 2)
            ));

            $this->notifyOthers($dispute, $userId, 'مقترح تسوية جديد', 'راجع المبلغ المقترح ووافق عليه أو ارفضه.');

            return $settlement;
        });
    }

    /**
     * Accepting is for the OTHER side. The proposer already said yes by
     * proposing, and letting them accept their own figure would make the second
     * statement meaningless.
     */
    public function accept(DisputeSettlement $settlement, int $userId): DisputeSettlement
    {
        $dispute = $settlement->dispute;
        $this->ensureLive($dispute);
        $this->roleOf($dispute, $userId);

        if ($settlement->status !== DisputeSettlement::STATUS_PROPOSED) {
            throw ValidationException::withMessages([
                'settlement' => __('لا يمكن قبول هذا المقترح في حالته الحالية.'),
            ]);
        }

        if ((int) $settlement->proposed_by_user_id === $userId) {
            throw ValidationException::withMessages([
                'settlement' => __('لا يمكنك قبول مقترحك أنت.'),
            ]);
        }

        $settlement->update([
            'status' => DisputeSettlement::STATUS_ACCEPTED,
            'accepted_by_user_id' => $userId,
            'accepted_at' => now(),
        ]);

        $payee = $this->userForSide($dispute, $settlement->payeeSide());

        $this->say($dispute, 'تمت الموافقة على مقترح التسوية. في انتظار تأكيد استلام المبلغ.');

        if ($payee) {
            $this->notify(
                $payee,
                $dispute,
                'أكّد استلام مبلغ التسوية',
                'وافق الطرفان على التسوية. أكّد استلامك للمبلغ لإغلاق النزاع.'
            );
        }

        return $settlement->fresh();
    }

    public function reject(DisputeSettlement $settlement, int $userId): DisputeSettlement
    {
        $dispute = $settlement->dispute;
        $this->roleOf($dispute, $userId);

        if (! $settlement->isLive()) {
            throw ValidationException::withMessages([
                'settlement' => __('لا يمكن رفض هذا المقترح في حالته الحالية.'),
            ]);
        }

        if ((int) $settlement->proposed_by_user_id === $userId) {
            throw ValidationException::withMessages([
                'settlement' => __('استخدم سحب المقترح بدلًا من رفضه.'),
            ]);
        }

        $settlement->update([
            'status' => DisputeSettlement::STATUS_REJECTED,
            'closed_at' => now(),
        ]);

        $this->say($dispute, 'رُفض مقترح التسوية.');

        return $settlement->fresh();
    }

    /** The proposer taking their own figure back, while it is still on the table. */
    public function withdraw(DisputeSettlement $settlement, int $userId): DisputeSettlement
    {
        if ((int) $settlement->proposed_by_user_id !== $userId) {
            throw ValidationException::withMessages([
                'settlement' => __('لا يمكن سحب مقترح لم تقدّمه أنت.'),
            ]);
        }

        if ($settlement->status !== DisputeSettlement::STATUS_PROPOSED) {
            throw ValidationException::withMessages([
                'settlement' => __('لا يمكن سحب المقترح بعد الموافقة عليه.'),
            ]);
        }

        $settlement->update([
            'status' => DisputeSettlement::STATUS_WITHDRAWN,
            'closed_at' => now(),
        ]);

        $this->say($settlement->dispute, 'سُحب مقترح التسوية.');

        return $settlement->fresh();
    }

    /**
     * "I actually received the money" — and with it, the dispute ends.
     *
     * Only the payee may say this. It is the one statement in the chain made by
     * the party who had something to lose by making it falsely, which is
     * exactly why it is the one that closes the case.
     */
    public function confirmReceived(DisputeSettlement $settlement, int $userId): DisputeSettlement
    {
        $dispute = $settlement->dispute;
        $this->ensureLive($dispute);

        $role = $this->roleOf($dispute, $userId);

        if ($settlement->status !== DisputeSettlement::STATUS_ACCEPTED) {
            throw ValidationException::withMessages([
                'settlement' => __('لا بد من الموافقة على المقترح قبل تأكيد الاستلام.'),
            ]);
        }

        if ($role !== $settlement->payeeSide()) {
            throw ValidationException::withMessages([
                'settlement' => __('لا يؤكد استلام المبلغ إلا الطرف المستلم.'),
            ]);
        }

        return DB::transaction(function () use ($settlement, $dispute, $userId) {
            $settlement->update([
                'status' => DisputeSettlement::STATUS_RECEIVED,
                'received_by_user_id' => $userId,
                'received_at' => now(),
                'closed_at' => now(),
            ]);

            $this->say($dispute, sprintf(
                'أكّد الطرف المستلم استلام مبلغ %s. أُغلق النزاع بالتراضي.',
                number_format((float) $settlement->amount, 2)
            ));

            // Three deliberate acts, the last by the party who stood to lose by
            // lying — enough to end the case on its own, so the escrow unwinds
            // exactly as it would for a two-tap settlement.
            $this->disputes->resolve($dispute, DisputeService::RESOLUTION_MUTUAL, [
                'off_app_settlement_id' => (int) $settlement->id,
                'off_app_amount' => (float) $settlement->amount,
                'off_app_payer_side' => $settlement->payer_side,
            ]);

            return $settlement->fresh();
        });
    }

    /* ============================ helpers ============================ */

    private function ensureLive(Dispute $dispute): void
    {
        if (! in_array($dispute->status, [
            Dispute::STATUS_OPEN,
            Dispute::STATUS_MUTUAL_RESOLUTION,
            Dispute::STATUS_UNDER_REVIEW,
        ], true)) {
            throw ValidationException::withMessages([
                'dispute' => __('لا يمكن تسجيل تسوية في الحالة الحالية للنزاع.'),
            ]);
        }
    }

    /**
     * client / business / arbitrator — refusing anyone else. The arbitrator is
     * recognised by having a seat in the room, which is how they got involved.
     */
    private function roleOf(Dispute $dispute, int $userId): string
    {
        $side = $this->disputes->sideOf($dispute, $userId);

        if ($side !== null) {
            return $side;
        }

        $seat = $this->disputes->room($dispute)->participantFor($userId);

        if ($seat && $seat->role === \App\Models\ThreadParticipant::ROLE_ARBITRATOR) {
            return 'arbitrator';
        }

        throw ValidationException::withMessages([
            'dispute' => __('لست طرفًا في هذا النزاع.'),
        ]);
    }

    private function userForSide(Dispute $dispute, string $side): ?User
    {
        $booking = (string) $dispute->disputeable_type === Booking::class
            ? Booking::query()->find((int) $dispute->disputeable_id)
            : null;

        if (! $booking) {
            return null;
        }

        return User::query()->find(
            $side === 'client' ? (int) $booking->user_id : (int) $booking->business_id
        );
    }

    private function say(Dispute $dispute, string $body): void
    {
        try {
            $this->threads->system($this->disputes->room($dispute), $body);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function notifyOthers(Dispute $dispute, int $exceptUserId, string $titleAr, string $bodyAr): void
    {
        foreach ($this->disputes->room($dispute)->participants as $participant) {
            if ((int) $participant->user_id === $exceptUserId) {
                continue;
            }

            $user = User::query()->find((int) $participant->user_id);

            if ($user) {
                $this->notify($user, $dispute, $titleAr, $bodyAr);
            }
        }
    }

    private function notify(User $user, Dispute $dispute, string $titleAr, string $bodyAr): void
    {
        try {
            $this->notifications->create([
                'user_id' => (int) $user->id,
                'type' => AppNotification::TYPE_DISPUTE,
                'priority' => AppNotification::PRIORITY_HIGH,
                'title_ar' => $titleAr,
                'body_ar' => $bodyAr,
                'notifiable_type' => Dispute::class,
                'notifiable_id' => (int) $dispute->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
