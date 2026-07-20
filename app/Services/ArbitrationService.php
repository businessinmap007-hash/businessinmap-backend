<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\ArbitrationSession;
use App\Models\Booking;
use App\Models\Deposit;
use App\Models\Dispute;
use App\Models\User;
use App\Services\Notifications\InAppNotificationService;
use App\Services\Wallet\PlatformTreasuryService;
use App\Support\AdminAbility;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Silber\Bouncer\BouncerFacade as Bouncer;

/**
 * The arbitrator as a role, and the record that makes the role accountable.
 *
 * An arbitrator is an admin account holding a curated set of abilities, NOT a
 * fourth `users.type`. `type` answers "which app does this account belong to",
 * and an arbitrator uses the same door as any other staff member; the ability
 * vocabulary in AdminAbility was written for exactly this — scoped staff. A
 * fourth enum value would instead mean auditing every `type === 'admin'` check
 * (the panel middleware, the login guard, the roles screen) so that a new kind
 * of admin did not silently stop being one.
 *
 * MONEY is in the set on purpose and is the reason the set is short: ruling on
 * a dispute moves the escrow, so an arbitrator who cannot move money cannot
 * arbitrate. That is also why every ruling writes a session row — power over
 * other people's money should leave a record its holder cannot edit.
 */
class ArbitrationService
{
    public const ROLE = 'arbitrator';

    /** What the role carries. Deliberately short. */
    public const ABILITIES = [
        AdminAbility::ACCESS,
        AdminAbility::DISPUTES,
        AdminAbility::MONEY,
    ];

    public function __construct(
        protected WalletService $wallets,
        protected PlatformTreasuryService $treasury,
        protected InAppNotificationService $notifications,
    ) {
    }

    /* ============================ the role ============================ */

    public function isArbitrator(User $user): bool
    {
        return $user->isAn(self::ROLE);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, User> */
    public function arbitrators()
    {
        return User::query()->whereIs(self::ROLE)->orderBy('id')->get();
    }

    public function promote(User $user): User
    {
        if ($user->type !== User::TYPE_ADMIN) {
            throw ValidationException::withMessages([
                'user' => __('الحكم يجب أن يكون حسابًا إداريًا.'),
            ]);
        }

        Bouncer::assign(self::ROLE)->to($user);

        foreach (self::ABILITIES as $ability) {
            Bouncer::allow($user)->to($ability);
        }

        Bouncer::refresh();

        return $user->fresh();
    }

    /**
     * Take the role away, and the abilities with it.
     *
     * The session history is deliberately left alone: a ruling that happened
     * still happened, and a record that disappears when someone is dismissed is
     * not a record.
     */
    public function demote(User $user): User
    {
        Bouncer::retract(self::ROLE)->from($user);

        foreach (self::ABILITIES as $ability) {
            Bouncer::disallow($user)->to($ability);
        }

        Bouncer::refresh();

        return $user->fresh();
    }

    /* ========================== the record ========================== */

    /**
     * Accept a case, on terms stated up front.
     *
     * The fee is fixed BEFORE the arbitrator hears anything, and both parties
     * are told what it is. Setting it afterwards would let the price of a
     * ruling be adjusted to the ruling — the arbitrator pricing their own
     * conclusion — and a party who cannot see the cost before it is incurred
     * cannot meaningfully object to it.
     *
     * Idempotent on the terms: once a session is accepted the fee cannot be
     * rewritten, for the same reason.
     */
    public function acceptSession(
        Dispute $dispute,
        int $arbitratorId,
        string $feeType,
        float $feeValue
    ): ArbitrationSession {
        if (! in_array($feeType, [ArbitrationSession::FEE_FIXED, ArbitrationSession::FEE_PERCENT], true)) {
            throw ValidationException::withMessages([
                'fee_type' => __('نوع الرسم غير مدعوم.'),
            ]);
        }

        $feeValue = round($feeValue, 2);

        if ($feeValue < 0) {
            throw ValidationException::withMessages([
                'fee_value' => __('قيمة الرسم غير صالحة.'),
            ]);
        }

        if ($feeType === ArbitrationSession::FEE_PERCENT && $feeValue > 100) {
            throw ValidationException::withMessages([
                'fee_value' => __('نسبة الرسم لا يمكن أن تتجاوز 100%.'),
            ]);
        }

        $existing = ArbitrationSession::query()->where('dispute_id', $dispute->id)->first();

        if ($existing && $existing->fee_terms_set_at !== null) {
            throw ValidationException::withMessages([
                'fee_type' => __('تم تحديد رسم هذه الجلسة بالفعل ولا يمكن تعديله.'),
            ]);
        }

        $disputedTotal = $this->disputedTotal($dispute);

        $feeAmount = $feeType === ArbitrationSession::FEE_PERCENT
            ? round(($disputedTotal * $feeValue) / 100, 2)
            : $feeValue;

        $attributes = [
            'arbitrator_id' => $arbitratorId,
            'fee_type' => $feeType,
            'fee_value' => $feeValue,
            'fee_amount' => $feeAmount,
            'fee_terms_set_at' => now(),
            'accepted_at' => now(),
        ];

        $session = $existing
            ? tap($existing)->update($attributes)
            : ArbitrationSession::create($attributes + ['dispute_id' => (int) $dispute->id]);

        $this->announceTerms($dispute, $session, $disputedTotal);

        return $session->fresh();
    }

    /**
     * Record the ruling on the session.
     *
     * A session may already exist because the arbitrator accepted the case
     * first; that row is filled in rather than duplicated. A session that
     * ALREADY carries an outcome is left untouched — a dispute is ruled once,
     * and the first ruling is the one on record.
     */
    public function recordSession(
        Dispute $dispute,
        string $outcome,
        array $payload = [],
        ?int $arbitratorId = null
    ): ArbitrationSession {
        $existing = ArbitrationSession::query()->where('dispute_id', $dispute->id)->first();

        if ($existing && ! $existing->isOpen()) {
            return $existing;
        }

        [$toClient, $toBusiness] = $this->amountsMoved($dispute, $outcome, $payload);

        $attributes = [
            'outcome' => $outcome,
            'client_percent' => $outcome === 'split' ? (float) ($payload['client_percent'] ?? 0) : null,
            'business_percent' => $outcome === 'split' ? (float) ($payload['business_percent'] ?? 0) : null,
            'amount_to_client' => $toClient,
            'amount_to_business' => $toBusiness,
            'notes' => $payload['notes'] ?? null,
        ];

        if ($existing) {
            // Do NOT overwrite arbitrator_id: whoever accepted the case owns it,
            // even if the ruling call was made without an actor id.
            $existing->update($attributes + array_filter(['arbitrator_id' => $arbitratorId]));

            return $existing->fresh();
        }

        return ArbitrationSession::create($attributes + [
            'dispute_id' => (int) $dispute->id,
            'arbitrator_id' => $arbitratorId,
            'platform_fine_amount' => 0,
        ]);
    }

    /** What the case is worth — the basis a percentage fee is taken from. */
    public function disputedTotal(Dispute $dispute): float
    {
        $deposit = $this->depositFor($dispute);

        return $deposit
            ? round((float) $deposit->client_amount + (float) $deposit->business_amount, 2)
            : 0.0;
    }

    protected function announceTerms(Dispute $dispute, ArbitrationSession $session, float $disputedTotal): void
    {
        $terms = $session->fee_type === ArbitrationSession::FEE_PERCENT
            ? sprintf('%s%% من قيمة النزاع (%s)', (float) $session->fee_value, number_format($disputedTotal, 2))
            : sprintf('مبلغ ثابت %s', number_format((float) $session->fee_value, 2));

        $body = sprintf(
            'قبل المحكّم النظر في النزاع. رسم التحكيم: %s = %s.',
            $terms,
            number_format((float) $session->fee_amount, 2)
        );

        try {
            app(ThreadService::class)->system(app(DisputeService::class)->room($dispute), $body);
        } catch (\Throwable $e) {
            report($e);
        }

        foreach ([$this->partyId($dispute, 'client'), $this->partyId($dispute, 'business')] as $userId) {
            if (! $userId) {
                continue;
            }

            try {
                $this->notifications->create([
                    'user_id' => $userId,
                    'type' => AppNotification::TYPE_DISPUTE,
                    'priority' => AppNotification::PRIORITY_HIGH,
                    'title_ar' => 'قُبلت جلسة التحكيم',
                    'title_en' => 'The arbitration session was accepted',
                    'body_ar' => $body,
                    'notifiable_type' => Dispute::class,
                    'notifiable_id' => (int) $dispute->id,
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /** What the escrow actually did, in money rather than in percentages. */
    private function amountsMoved(Dispute $dispute, string $outcome, array $payload): array
    {
        $deposit = $this->depositFor($dispute);
        $total = $deposit ? (float) $deposit->client_amount + (float) $deposit->business_amount : 0.0;

        return match ($outcome) {
            // A mutual settlement returns each hold to whoever posted it,
            // so nothing moved BETWEEN the parties through the escrow.
            'mutual_settlement' => [0.0, 0.0],
            'refund_client' => [$total, 0.0],
            'release_business' => [0.0, $total],
            'split' => [
                $client = round(($total * (float) ($payload['client_percent'] ?? 0)) / 100, 2),
                round($total - $client, 2),
            ],
            default => [0.0, 0.0],
        };
    }

    private function depositFor(Dispute $dispute): ?Deposit
    {
        if ((string) $dispute->disputeable_type !== Booking::class) {
            return null;
        }

        return Deposit::query()
            ->where('target_type', Booking::class)
            ->where('target_id', (int) $dispute->disputeable_id)
            ->orderByDesc('id')
            ->first();
    }

    /* =========================== the fine =========================== */

    /**
     * Fine a party on the platform's behalf: money out of their wallet and into
     * the treasury as PURPOSE_FINE.
     *
     * Separate from the guarantee penalty, which reduces someone's COVERAGE and
     * is a trust consequence. This is cash, and the two are not
     * interchangeable — a party can have coverage to burn and no balance, or
     * the reverse.
     */
    public function applyPlatformFine(Dispute $dispute, string $side, float $amount): ?ArbitrationSession
    {
        $amount = round(max($amount, 0), 2);

        if ($amount <= 0) {
            return null;
        }

        if (! in_array($side, ['client', 'business'], true)) {
            throw ValidationException::withMessages([
                'platform_fine_on' => __('يجب تحديد الطرف الذي تُفرض عليه الغرامة.'),
            ]);
        }

        $session = ArbitrationSession::query()->where('dispute_id', $dispute->id)->first();

        if (! $session) {
            throw ValidationException::withMessages([
                'dispute' => __('لا يمكن فرض غرامة قبل صدور القرار.'),
            ]);
        }

        if ((float) $session->platform_fine_amount > 0) {
            return $session; // one fine per ruling
        }

        $payerId = $this->partyId($dispute, $side);

        if (! $payerId) {
            throw ValidationException::withMessages([
                'platform_fine_on' => __('تعذر تحديد الطرف الذي تُفرض عليه الغرامة.'),
            ]);
        }

        return DB::transaction(function () use ($session, $dispute, $side, $amount, $payerId) {
            $key = 'dispute_fine_' . $dispute->id;

            // The debit is the authoritative half: the treasury credit is
            // best-effort by design (see PlatformTreasuryService), so it must
            // never be the thing that decides whether the fine happened.
            $this->wallets->withdraw(
                userId: $payerId,
                amount: $amount,
                note: 'غرامة منصة على نزاع #' . $dispute->id,
                referenceType: 'dispute_fine',
                referenceId: (string) $dispute->id,
                idempotencyKey: $key,
                meta: ['dispute_id' => (int) $dispute->id, 'side' => $side]
            );

            $this->treasury->credit(
                amount: $amount,
                purpose: PlatformTreasuryService::PURPOSE_FINE,
                referenceId: (string) $dispute->id,
                idempotencyKey: $key . '_treasury',
                meta: ['dispute_id' => (int) $dispute->id, 'side' => $side]
            );

            $session->update([
                'platform_fine_amount' => $amount,
                'platform_fine_on' => $side,
            ]);

            // A separate movement from the ruling, applied after it, so it
            // needs its own notice — the ruling notification was already sent
            // and knew nothing about this.
            $this->notifyFine($payerId, $dispute, $amount);

            return $session->fresh();
        });
    }

    /**
     * Collect the fee that was agreed before the case was heard.
     *
     * Who bears it is decided at the ruling — that part genuinely depends on
     * how it went — but HOW MUCH was fixed in advance and cannot be revisited
     * here. `split` divides it by subtraction so an odd number cannot mint or
     * burn a piastre between the two halves.
     *
     * Treasury purpose is FEE, not FINE: this is the price of the service, not
     * a punishment, and counting it as penalty revenue would misstate both.
     */
    public function chargeArbitrationFee(Dispute $dispute, string $on): ?ArbitrationSession
    {
        if (! in_array($on, ['client', 'business', 'split'], true)) {
            throw ValidationException::withMessages([
                'arbitration_fee_on' => __('يجب تحديد من يتحمل رسم التحكيم.'),
            ]);
        }

        $session = ArbitrationSession::query()->where('dispute_id', $dispute->id)->first();

        if (! $session || (float) $session->fee_amount <= 0) {
            return $session;
        }

        if ($session->fee_on !== null) {
            return $session; // already collected
        }

        $total = round((float) $session->fee_amount, 2);

        $shares = match ($on) {
            'client' => ['client' => $total, 'business' => 0.0],
            'business' => ['client' => 0.0, 'business' => $total],
            'split' => ['client' => $half = round($total / 2, 2), 'business' => round($total - $half, 2)],
        };

        return DB::transaction(function () use ($session, $dispute, $on, $shares) {
            foreach ($shares as $side => $amount) {
                if ($amount <= 0) {
                    continue;
                }

                $payerId = $this->partyId($dispute, $side);

                if (! $payerId) {
                    continue;
                }

                $key = 'dispute_arbitration_fee_' . $dispute->id . '_' . $side;

                $this->wallets->withdraw(
                    userId: $payerId,
                    amount: $amount,
                    note: 'رسم تحكيم على نزاع #' . $dispute->id,
                    referenceType: 'dispute_arbitration_fee',
                    referenceId: (string) $dispute->id,
                    idempotencyKey: $key,
                    meta: ['dispute_id' => (int) $dispute->id, 'side' => $side]
                );

                $this->treasury->credit(
                    amount: $amount,
                    purpose: PlatformTreasuryService::PURPOSE_FEE,
                    referenceId: (string) $dispute->id,
                    idempotencyKey: $key . '_treasury',
                    meta: ['dispute_id' => (int) $dispute->id, 'side' => $side, 'kind' => 'arbitration_fee']
                );

                $this->notifyArbitrationFee($payerId, $dispute, $amount);
            }

            $session->update(['fee_on' => $on]);

            return $session->fresh();
        });
    }

    private function notifyArbitrationFee(int $userId, Dispute $dispute, float $amount): void
    {
        try {
            $this->notifications->create([
                'user_id' => $userId,
                'type' => AppNotification::TYPE_DISPUTE,
                'priority' => AppNotification::PRIORITY_HIGH,
                'title_ar' => 'رسم تحكيم',
                'title_en' => 'Arbitration fee',
                'body_ar' => 'خُصم من محفظتك مبلغ ' . number_format($amount, 2) . ' كرسم تحكيم على النزاع.',
                'body_en' => number_format($amount, 2) . ' was deducted from your wallet as an arbitration fee.',
                'notifiable_type' => Dispute::class,
                'notifiable_id' => (int) $dispute->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function notifyFine(int $userId, Dispute $dispute, float $amount): void
    {
        try {
            $this->notifications->create([
                'user_id' => $userId,
                'type' => AppNotification::TYPE_DISPUTE,
                'priority' => AppNotification::PRIORITY_HIGH,
                'title_ar' => 'غرامة منصة على نزاع',
                'title_en' => 'A platform fine was imposed',
                'body_ar' => 'خُصم من محفظتك مبلغ ' . number_format($amount, 2) . ' كغرامة منصة على النزاع.',
                'body_en' => number_format($amount, 2) . ' was deducted from your wallet as a platform fine.',
                'notifiable_type' => Dispute::class,
                'notifiable_id' => (int) $dispute->id,
            ]);
        } catch (\Throwable $e) {
            // The money is already taken; a failed notice must not undo it.
            report($e);
        }
    }

    private function partyId(Dispute $dispute, string $side): ?int
    {
        if ((string) $dispute->disputeable_type !== Booking::class) {
            return null;
        }

        $booking = Booking::query()->find((int) $dispute->disputeable_id);

        if (! $booking) {
            return null;
        }

        return $side === 'client' ? (int) $booking->user_id : (int) $booking->business_id;
    }

    /* =========================== the stats =========================== */

    /**
     * An arbitrator's record: how many they heard, how each one went, and how
     * much the platform collected through them.
     */
    public function statsFor(int $arbitratorId): array
    {
        $rows = ArbitrationSession::query()
            ->where('arbitrator_id', $arbitratorId)
            ->get();

        return [
            'sessions' => $rows->count(),
            // Accepted but not yet ruled on — an arbitrator's open workload,
            // which is invisible if you only count decided cases.
            'open_sessions' => $rows->filter->isOpen()->count(),
            'by_outcome' => $rows->whereNotNull('outcome')->groupBy('outcome')->map->count()->all(),
            'fees_earned' => round((float) $rows->whereNotNull('fee_on')->sum('fee_amount'), 2),
            'fines_collected' => round((float) $rows->sum('platform_fine_amount'), 2),
            'moved_to_clients' => round((float) $rows->sum('amount_to_client'), 2),
            'moved_to_businesses' => round((float) $rows->sum('amount_to_business'), 2),
            'last_session_at' => optional($rows->max('created_at'))->toDateTimeString(),
        ];
    }
}
