<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Fine;
use App\Models\FineAppeal;
use App\Models\Wallet;
use App\Services\Notifications\InAppNotificationService;
use App\Services\Wallet\PlatformTreasuryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Platform fines for fraud/abuse, levied outside a dispute.
 *
 * The money is never seized outright. At levy the amount is FROZEN in the
 * wallet (a protective hold, not a deduction), an appeal window opens, and the
 * money only leaves — captured to the treasury as PURPOSE_FINE — once the fine
 * is UPHELD or the window closes unappealed. An overturned or cancelled fine
 * releases the hold, so contesting one successfully costs nothing.
 *
 * A fine born of a settlement the user already agreed to is not appealable: the
 * consent was conditional on the settlement completing, so there is nothing
 * left to contest. That path is not levied here yet; the flag exists so it holds
 * when it is.
 */
class FineService
{
    /** Default days a user has to contest a fine before it can be collected. */
    public const DEFAULT_APPEAL_DAYS = 7;

    public function __construct(
        private readonly WalletService $wallets,
        private readonly PlatformTreasuryService $treasury,
        private readonly InAppNotificationService $notifications,
    ) {}

    /**
     * Levy a fine and freeze what the wallet can cover right now. A wallet that
     * cannot cover it still gets fined — the shortfall is topped up from later
     * balance by the sweep, and the fine is not collected until fully frozen.
     */
    public function levy(
        int $userId,
        float $amount,
        string $reason,
        int $adminId,
        int $appealDays = self::DEFAULT_APPEAL_DAYS,
        bool $appealable = true,
        string $source = Fine::SOURCE_ADMIN,
    ): Fine {
        $amount = round($amount, 2);
        $reason = trim($reason);

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => __('قيمة الغرامة يجب أن تكون أكبر من صفر.')]);
        }
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => __('سبب الغرامة مطلوب.')]);
        }

        return DB::transaction(function () use ($userId, $amount, $reason, $adminId, $appealDays, $appealable, $source) {
            $fine = Fine::create([
                'user_id' => $userId,
                'amount' => $amount,
                'frozen_amount' => 0,
                'collected_amount' => 0,
                'reason' => $reason,
                'source' => $source,
                'status' => Fine::STATUS_FROZEN,
                'is_appealable' => $appealable,
                'appeal_deadline_at' => $appealable ? now()->addDays(max(1, $appealDays)) : null,
                'levied_by' => $adminId,
                'frozen_at' => now(),
            ]);

            $this->freezeAvailable($fine);

            // Stored (notification columns) — raw Arabic, never __(): wrapping
            // would freeze the admin's locale into the user's notice.
            $this->notify(
                $userId,
                (int) $fine->id,
                'غرامة على حسابك',
                'فُرضت غرامة بقيمة ' . number_format($amount, 2) . '. المبلغ مجمّد في محفظتك.'
                . ($appealable ? ' يمكنك الاعتراض خلال ' . max(1, $appealDays) . ' أيام قبل خصمها.' : '')
            );

            return $fine->fresh();
        });
    }

    /**
     * Lock as much of the outstanding shortfall as the wallet's free balance
     * allows. Idempotent per call — it only ever adds up to the shortfall.
     */
    public function freezeAvailable(Fine $fine): Fine
    {
        $shortfall = $fine->shortfall();
        if ($shortfall <= 0) {
            return $fine;
        }

        $wallet = $this->wallets->getOrCreateWallet((int) $fine->user_id);
        $available = round((float) $wallet->balance, 2);
        $toFreeze = round(min($shortfall, $available), 2);

        if ($toFreeze <= 0) {
            return $fine;
        }

        $this->wallets->hold(
            userId: (int) $fine->user_id,
            amount: $toFreeze,
            referenceType: 'fine',
            referenceId: (string) $fine->id,
            note: 'تجميد غرامة #' . $fine->id,
            idempotencyKey: 'fine_freeze_' . $fine->id . '_' . round((float) $fine->frozen_amount + $toFreeze, 2),
            meta: ['fine_id' => (int) $fine->id]
        );

        $fine->frozen_amount = round((float) $fine->frozen_amount + $toFreeze, 2);
        $fine->save();

        return $fine;
    }

    /**
     * The user contests the fine. Only while the window is open, only on their
     * own fine, and only if it is appealable.
     */
    public function appeal(Fine $fine, int $userId, string $statement): FineAppeal
    {
        if ((int) $fine->user_id !== $userId) {
            throw ValidationException::withMessages(['fine' => __('لا يمكنك الاعتراض على غرامة ليست لك.')]);
        }
        if (! $fine->is_appealable) {
            throw ValidationException::withMessages(['fine' => __('هذه الغرامة غير قابلة للاعتراض.')]);
        }
        if (! $fine->appealWindowOpen()) {
            throw ValidationException::withMessages(['fine' => __('انتهت مهلة الاعتراض على هذه الغرامة.')]);
        }

        $statement = trim($statement);
        if ($statement === '') {
            throw ValidationException::withMessages(['statement' => __('يجب كتابة سبب الاعتراض.')]);
        }

        return DB::transaction(function () use ($fine, $userId, $statement) {
            $appeal = FineAppeal::create([
                'fine_id' => (int) $fine->id,
                'user_id' => $userId,
                'statement' => $statement,
                'status' => FineAppeal::STATUS_PENDING,
            ]);

            // Pausing collection is the whole point: an appealed fine is neither
            // frozen (window) nor upheld, so the sweep leaves it for a human.
            $fine->update(['status' => Fine::STATUS_APPEALED]);

            return $appeal->fresh();
        });
    }

    /** Admin rules on a pending appeal. Accept unfreezes; reject makes it due. */
    public function decideAppeal(Fine $fine, int $adminId, bool $accept, ?string $note = null): Fine
    {
        if ($fine->status !== Fine::STATUS_APPEALED) {
            throw ValidationException::withMessages(['fine' => __('لا يوجد اعتراض قيد النظر على هذه الغرامة.')]);
        }

        return DB::transaction(function () use ($fine, $adminId, $accept, $note) {
            $appeal = $fine->appeals()->where('status', FineAppeal::STATUS_PENDING)->latest('id')->first();
            if ($appeal) {
                $appeal->update([
                    'status' => $accept ? FineAppeal::STATUS_ACCEPTED : FineAppeal::STATUS_REJECTED,
                    'decided_by' => $adminId,
                    'decision_note' => $note,
                    'decided_at' => now(),
                ]);
            }

            if ($accept) {
                $this->releaseHold($fine);
                $fine->update(['status' => Fine::STATUS_OVERTURNED, 'resolved_at' => now()]);

                $this->notify((int) $fine->user_id, (int) $fine->id, 'قُبل اعتراضك',
                    'قُبل اعتراضك على الغرامة وأُلغيت، وفُكّ تجميد المبلغ.');
            } else {
                $fine->update(['status' => Fine::STATUS_UPHELD]);

                $this->notify((int) $fine->user_id, (int) $fine->id, 'رُفض اعتراضك',
                    'رُفض اعتراضك على الغرامة وستُخصم قيمتها المجمّدة.');

                // Reject and collect in one step when the money is already there.
                $this->collect($fine->fresh());
            }

            return $fine->fresh();
        });
    }

    /**
     * Capture a due, fully-frozen fine to the treasury. A shortfall keeps it
     * open for the sweep to top up first — we never take more than is locked.
     */
    public function collect(Fine $fine): Fine
    {
        if (! $fine->isCollectable()) {
            return $fine;
        }

        // Try to lock any balance that appeared since levy before giving up.
        if (! $fine->isFullyFrozen()) {
            $fine = $this->freezeAvailable($fine);
            if (! $fine->isFullyFrozen()) {
                return $fine; // still short — leave it for the next sweep
            }
        }

        return DB::transaction(function () use ($fine) {
            $amount = round((float) $fine->frozen_amount, 2);
            if ($amount <= 0) {
                return $fine;
            }

            $this->wallets->captureLocked(
                userId: (int) $fine->user_id,
                amount: $amount,
                referenceType: 'fine',
                referenceId: (string) $fine->id,
                note: 'خصم غرامة #' . $fine->id,
                idempotencyKey: 'fine_capture_' . $fine->id,
                meta: ['fine_id' => (int) $fine->id]
            );

            $this->treasury->credit(
                amount: $amount,
                purpose: PlatformTreasuryService::PURPOSE_FINE,
                referenceId: (string) $fine->id,
                idempotencyKey: 'fine_capture_' . $fine->id . '_treasury',
                meta: ['fine_id' => (int) $fine->id]
            );

            $fine->update([
                'status' => Fine::STATUS_COLLECTED,
                'collected_amount' => $amount,
                'collected_at' => now(),
            ]);

            $this->notify((int) $fine->user_id, (int) $fine->id, 'خُصمت الغرامة',
                'خُصم ' . number_format($amount, 2) . ' من محفظتك سدادًا للغرامة.');

            return $fine->fresh();
        });
    }

    /** Admin withdraws a fine before it is collected; the hold is released. */
    public function cancel(Fine $fine, int $adminId, ?string $note = null): Fine
    {
        if (! $fine->isOpen()) {
            throw ValidationException::withMessages(['fine' => __('لا يمكن إلغاء غرامة مغلقة.')]);
        }

        return DB::transaction(function () use ($fine, $adminId, $note) {
            $this->releaseHold($fine);
            $fine->update([
                'status' => Fine::STATUS_CANCELLED,
                'resolved_at' => now(),
                'meta' => array_merge((array) $fine->meta, ['cancelled_by' => $adminId, 'cancel_note' => $note]),
            ]);

            $this->notify((int) $fine->user_id, (int) $fine->id, 'أُلغيت الغرامة',
                'أُلغيت الغرامة المفروضة على حسابك وفُكّ تجميد المبلغ.');

            return $fine->fresh();
        });
    }

    /**
     * The sweep: top up under-frozen holds and collect anything now due.
     *
     * @return array{topped_up:int,collected:int,pending:int}
     */
    public function processDue(int $limit = 100): array
    {
        $toppedUp = 0;
        $collected = 0;
        $pending = 0;

        Fine::query()
            ->whereIn('status', [Fine::STATUS_FROZEN, Fine::STATUS_UPHELD])
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (Fine $fine) use (&$toppedUp, &$collected, &$pending) {
                try {
                    if (! $fine->isFullyFrozen()) {
                        $before = (float) $fine->frozen_amount;
                        $fine = $this->freezeAvailable($fine);
                        if ((float) $fine->frozen_amount > $before) {
                            $toppedUp++;
                        }
                    }

                    if ($fine->isCollectable()) {
                        $fine = $this->collect($fine);
                        if ($fine->status === Fine::STATUS_COLLECTED) {
                            $collected++;
                        } else {
                            $pending++;
                        }
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            });

        return ['topped_up' => $toppedUp, 'collected' => $collected, 'pending' => $pending];
    }

    /** Sum of frozen money still held against this user's open fines. */
    public function frozenFor(int $userId): float
    {
        return round((float) Fine::query()
            ->where('user_id', $userId)
            ->whereIn('status', [Fine::STATUS_FROZEN, Fine::STATUS_APPEALED, Fine::STATUS_UPHELD])
            ->sum('frozen_amount'), 2);
    }

    /** Release whatever hold is still locked for a fine (overturn/cancel). */
    private function releaseHold(Fine $fine): void
    {
        $frozen = round((float) $fine->frozen_amount, 2);
        if ($frozen <= 0) {
            return;
        }

        $this->wallets->release(
            userId: (int) $fine->user_id,
            amount: $frozen,
            referenceType: 'fine',
            referenceId: (string) $fine->id,
            note: 'فك تجميد غرامة #' . $fine->id,
            idempotencyKey: 'fine_release_' . $fine->id,
            meta: ['fine_id' => (int) $fine->id]
        );

        $fine->frozen_amount = 0;
        $fine->save();
    }

    private function notify(int $userId, int $fineId, string $titleAr, string $bodyAr): void
    {
        try {
            $this->notifications->create([
                'user_id' => $userId,
                'type' => AppNotification::TYPE_WALLET,
                'priority' => AppNotification::PRIORITY_URGENT,
                'title_ar' => $titleAr,
                'body_ar' => $bodyAr,
                'notifiable_type' => Fine::class,
                'notifiable_id' => $fineId,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
