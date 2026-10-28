<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Dispute;
use App\Models\DisputeObligation;
use App\Models\GuaranteeLevel;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Guarantees\GuaranteePenaltyService;
use App\Services\Notifications\InAppNotificationService;
use App\Services\Wallet\PlatformTreasuryService;
use Illuminate\Support\Facades\DB;

/**
 * Collecting what a ruling decided someone owes.
 *
 * The old behaviour was to throw when a wallet was short, which meant the
 * ruling said one thing and the ledger recorded nothing. A debt does not stop
 * existing because it cannot be met today, so every charge is written down
 * first and paid second.
 *
 * The escalation the platform promised, in order:
 *   1. Take it from the wallet if the money is there.
 *   2. Otherwise leave it PENDING, FREEZE their guarantee for it, and tell them
 *      they have 24 hours. The account itself is never stopped (owner,
 *      2026-10-28: «لا توقف الحساب في حالة النزاع ولكن تجميد الضمان أو العربون»)
 *      — it books and sells as usual; what it cannot do is spend the guarantee
 *      that now stands for the debt.
 *   3. After that window, take it from that guarantee without asking again.
 *
 * Step 3 is only defensible because of step 2. Raiding a guarantee the moment
 * a ruling lands is seizure; doing it after a deadline the person was told
 * about is enforcement. That is why `due_at` exists rather than a flag.
 */
class DisputeCollectionService
{
    /** The window a payer gets before their guarantee is opened. */
    public const GRACE_HOURS = 24;

    public function __construct(
        protected WalletService $wallets,
        protected PlatformTreasuryService $treasury,
        protected GuaranteePenaltyService $penalties,
        protected InAppNotificationService $notifications,
    ) {
    }

    /**
     * Record a debt and try to collect it.
     *
     * Idempotent per (dispute, type): a retry settles the existing obligation
     * rather than creating a second one.
     */
    public function charge(
        Dispute $dispute,
        int $userId,
        string $type,
        float $amount,
        ?int $payeeUserId = null
    ): DisputeObligation {
        $amount = round($amount, 2);

        $obligation = DisputeObligation::query()->firstOrCreate(
            ['dispute_id' => (int) $dispute->id, 'type' => $type],
            [
                'user_id' => $userId,
                'amount' => $amount,
                'payee_user_id' => $payeeUserId,
                'status' => DisputeObligation::STATUS_PENDING,
                'due_at' => now()->addHours(self::GRACE_HOURS),
            ]
        );

        return $this->settle($obligation);
    }

    /**
     * Try to meet one obligation.
     *
     * Before the window closes only the wallet is touched. After it, the
     * guarantee is fair game — GuaranteePenaltyService already takes from the
     * balance first and only then from the locked guarantee, marking it
     * underfunded and downgrading the level, which is exactly the consequence
     * that was promised.
     */
    public function settle(DisputeObligation $obligation): DisputeObligation
    {
        if (! $obligation->isPending()) {
            return $obligation;
        }

        $amount = round((float) $obligation->amount, 2);

        if ($amount <= 0) {
            return tap($obligation)->update([
                'status' => DisputeObligation::STATUS_PAID,
                'paid_at' => now(),
                'settled_from' => DisputeObligation::FROM_WALLET,
            ]);
        }

        $balance = (float) (Wallet::query()->where('user_id', $obligation->user_id)->value('balance') ?? 0);

        if ($balance >= $amount) {
            return $this->payFromWallet($obligation, $amount);
        }

        if ($obligation->isDue()) {
            return $this->payFromGuarantee($obligation, $amount);
        }

        // Still within the window: they were told, and their guarantee is held for it
        $this->holdGuarantee($obligation, $amount);

        return $obligation;
    }

    /** The operation a debt's guarantee freeze is recorded against. */
    public const HOLD_OPERATION = 'dispute_obligation';

    /**
     * Freeze as much of the debtor's guarantee as stands for the debt (all of it, or what it has). A debtor with no guarantee
     * freezes nothing: the debt is still written down and taken from the wallet the moment money is there.
     */
    private function holdGuarantee(DisputeObligation $obligation, float $amount): void
    {
        $user = User::query()->find((int) $obligation->user_id);

        if (! $user) {
            return;
        }

        $target = $user->type === User::TYPE_BUSINESS ? GuaranteeLevel::TARGET_BUSINESS : GuaranteeLevel::TARGET_CLIENT;

        try {
            $guarantors = app(\App\Services\Guarantees\OperationGuarantorService::class);
            $freeze = round(min($amount, $guarantors->usableCoverage($user, $target)), 2);

            if ($freeze > 0) {
                $guarantors->freezeSelf(self::HOLD_OPERATION, (int) $obligation->id, $user, $freeze, $target);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** The debt is met: its guarantee freeze goes back. */
    private function releaseHold(DisputeObligation $obligation): void
    {
        try {
            app(\App\Services\Guarantees\OperationGuarantorService::class)->releaseOperation(self::HOLD_OPERATION, (int) $obligation->id);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** @return array{settled: int, still_pending: int} */
    public function settleDue(?int $limit = 100): array
    {
        $due = DisputeObligation::query()
            ->where('status', DisputeObligation::STATUS_PENDING)
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now())
            ->orderBy('id')
            ->limit(max((int) ($limit ?? 100), 1))
            ->get();

        $settled = 0;
        $pending = 0;

        foreach ($due as $obligation) {
            try {
                $this->settle($obligation)->isPending() ? $pending++ : $settled++;
            } catch (\Throwable $e) {
                $pending++;
                report($e);
            }
        }

        return ['settled' => $settled, 'still_pending' => $pending];
    }

    /* ===================== the block on new operations ===================== */

    /**
     * Derived, never stored. A denormalised "blocked" flag drifts the first
     * time a debt is settled by a path that forgets to clear it, and a user
     * frozen out of the platform by a stale boolean has no way to argue.
     */
    public function isBlocked(int $userId): bool
    {
        // never: an unpaid ruling freezes the debtor's guarantee (holdGuarantee) and is collected from wallet or guarantee — the
        // account keeps working (owner, 2026-10-28)
        return false;
    }

    public function outstandingFor(int $userId): float
    {
        return round((float) DisputeObligation::query()
            ->where('user_id', $userId)
            ->where('status', DisputeObligation::STATUS_PENDING)
            ->sum('amount'), 2);
    }

    /** Pending debts this user owes (the ones that block them). */
    public function pendingOwedBy(int $userId)
    {
        return DisputeObligation::query()
            ->with('dispute:id,status')
            ->where('user_id', $userId)
            ->where('status', DisputeObligation::STATUS_PENDING)
            ->orderBy('due_at')
            ->get();
    }

    /** Pending money owed TO this user (compensation awaiting the payer). */
    public function pendingOwedTo(int $userId)
    {
        return DisputeObligation::query()
            ->with('dispute:id,status')
            ->where('payee_user_id', $userId)
            ->where('status', DisputeObligation::STATUS_PENDING)
            ->orderBy('due_at')
            ->get();
    }

    /**
     * Settle a user's pending debts from their wallet now — the "pay to unblock"
     * path. Without this a top-up made inside the grace window would not clear
     * the block until the scheduled sweep ran after `due_at`, even with the money
     * sitting in the wallet. Returns a fresh summary.
     *
     * @return array{settled: int, still_pending: int, outstanding: float, blocked: bool}
     */
    public function settleForUser(int $userId): array
    {
        $settled = 0;
        $pending = 0;

        foreach ($this->pendingOwedBy($userId) as $obligation) {
            try {
                $this->settle($obligation)->isPending() ? $pending++ : $settled++;
            } catch (\Throwable $e) {
                $pending++;
                report($e);
            }
        }

        return [
            'settled' => $settled,
            'still_pending' => $pending,
            'outstanding' => $this->outstandingFor($userId),
            'blocked' => $this->isBlocked($userId),
        ];
    }

    /* ============================== internals ============================== */

    private function payFromWallet(DisputeObligation $obligation, float $amount): DisputeObligation
    {
        return DB::transaction(function () use ($obligation, $amount) {
            $key = 'dispute_obligation_' . $obligation->id;

            $this->wallets->withdraw(
                userId: (int) $obligation->user_id,
                amount: $amount,
                note: $this->noteFor($obligation),
                referenceType: 'dispute_obligation',
                referenceId: (string) $obligation->dispute_id,
                idempotencyKey: $key,
                meta: ['obligation_id' => (int) $obligation->id, 'type' => $obligation->type]
            );

            $this->credit($obligation, $amount, $key);
            $this->releaseHold($obligation);

            $obligation->update([
                'status' => DisputeObligation::STATUS_PAID,
                'settled_from' => DisputeObligation::FROM_WALLET,
                'paid_at' => now(),
            ]);

            $this->notifyPaid($obligation, $amount, false);

            return $obligation->fresh();
        });
    }

    private function payFromGuarantee(DisputeObligation $obligation, float $amount): DisputeObligation
    {
        $user = User::query()->find((int) $obligation->user_id);

        if (! $user) {
            return $obligation;
        }

        // the freeze steps aside for the guarantee to pay what it was held for
        $this->releaseHold($obligation);

        try {
            $this->penalties->applyPenalty(
                user: $user,
                amount: $amount,
                targetType: $user->type === User::TYPE_BUSINESS
                    ? GuaranteeLevel::TARGET_BUSINESS
                    : GuaranteeLevel::TARGET_CLIENT,
                referenceType: Dispute::class,
                referenceId: (int) $obligation->dispute_id,
                reason: 'Unpaid dispute obligation settled from guarantee',
                meta: ['idempotency_key' => 'dispute_obligation_guarantee_' . $obligation->id]
            );
        } catch (\Throwable $e) {
            // No active guarantee, or not enough locked in it. The debt stands, held again, and is taken from the wallet when
            // money is there.
            report($e);
            $this->holdGuarantee($obligation, $amount);

            return $obligation;
        }

        $this->credit($obligation, $amount, 'dispute_obligation_guarantee_' . $obligation->id);

        $obligation->update([
            'status' => DisputeObligation::STATUS_PAID,
            'settled_from' => DisputeObligation::FROM_GUARANTEE,
            'paid_at' => now(),
        ]);

        $this->notifyPaid($obligation, $amount, true);

        return $obligation->fresh();
    }

    /** Money owed to the other party goes to them; everything else to the treasury. */
    private function credit(DisputeObligation $obligation, float $amount, string $key): void
    {
        if ($obligation->payee_user_id) {
            try {
                $this->wallets->deposit(
                    userId: (int) $obligation->payee_user_id,
                    amount: $amount,
                    note: 'تعويض بحكم تحكيم في نزاع #' . $obligation->dispute_id,
                    referenceType: 'dispute_obligation',
                    referenceId: (string) $obligation->dispute_id,
                    idempotencyKey: $key . '_payee',
                    meta: ['obligation_id' => (int) $obligation->id]
                );
            } catch (\Throwable $e) {
                report($e);
            }

            return;
        }

        $this->treasury->credit(
            amount: $amount,
            purpose: $obligation->type === DisputeObligation::TYPE_PLATFORM_FINE
                ? PlatformTreasuryService::PURPOSE_FINE
                : PlatformTreasuryService::PURPOSE_FEE,
            referenceId: (string) $obligation->dispute_id,
            idempotencyKey: $key . '_treasury',
            meta: ['obligation_id' => (int) $obligation->id, 'type' => $obligation->type]
        );
    }

    private function noteFor(DisputeObligation $obligation): string
    {
        return match ($obligation->type) {
            DisputeObligation::TYPE_SESSION_FEE => 'رسم جلسة تحكيم على نزاع #' . $obligation->dispute_id,
            DisputeObligation::TYPE_PLATFORM_FINE => 'غرامة منصة على نزاع #' . $obligation->dispute_id,
            default => 'تعويض بحكم تحكيم في نزاع #' . $obligation->dispute_id,
        };
    }

    public function notifyUnpaid(DisputeObligation $obligation): void
    {
        $this->notify(
            (int) $obligation->user_id,
            (int) $obligation->dispute_id,
            'مستحق غير مسدَّد على نزاع',
            'عليك سداد ' . number_format((float) $obligation->amount, 2)
                . '. حسابك موقوف عن العمليات الجديدة حتى السداد، وبعد '
                . self::GRACE_HOURS . ' ساعة يُخصم من ضمانك المجمّد دون الرجوع إليك.'
        );
    }

    private function notifyPaid(DisputeObligation $obligation, float $amount, bool $fromGuarantee): void
    {
        $this->notify(
            (int) $obligation->user_id,
            (int) $obligation->dispute_id,
            $fromGuarantee ? 'سُدِّد المستحق من ضمانك' : 'سُدِّد مستحق النزاع',
            $fromGuarantee
                ? 'انتهت المهلة، فخُصم ' . number_format($amount, 2) . ' من ضمانك المجمّد لسداد ما حُكم به.'
                : 'خُصم ' . number_format($amount, 2) . ' من محفظتك سدادًا لما حُكم به.'
        );
    }

    private function notify(int $userId, int $disputeId, string $titleAr, string $bodyAr): void
    {
        try {
            $this->notifications->create([
                'user_id' => $userId,
                'type' => AppNotification::TYPE_DISPUTE,
                'priority' => AppNotification::PRIORITY_URGENT,
                'title_ar' => $titleAr,
                'body_ar' => $bodyAr,
                'notifiable_type' => Dispute::class,
                'notifiable_id' => $disputeId,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
