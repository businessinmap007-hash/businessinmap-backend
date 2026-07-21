<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\ArbitrationSession;
use App\Models\Booking;
use App\Models\ConductViolation;
use App\Models\Deposit;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\DisputeObligation;
use App\Models\DisputeFee;
use App\Models\Thread;
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
    public function acceptSession(Dispute $dispute, int $arbitratorId): ArbitrationSession
    {
        $existing = ArbitrationSession::query()->where('dispute_id', $dispute->id)->first();

        if ($existing && $existing->fee_terms_set_at !== null) {
            throw ValidationException::withMessages([
                'fee_type' => __('تم تحديد رسم هذه الجلسة بالفعل ولا يمكن تعديله.'),
            ]);
        }

        // Read, not chosen. The price is platform policy per service, so it is
        // the same for whoever ends up paying it and the same across every case
        // on that service — an arbitrator cannot price the session they are
        // about to hear, and a party can look the number up beforehand.
        $feeAmount = $this->sessionFeeFor($dispute);

        $attributes = [
            'arbitrator_id' => $arbitratorId,
            'fee_type' => ArbitrationSession::FEE_FIXED,
            'fee_value' => $feeAmount,
            'fee_amount' => $feeAmount,
            'fee_terms_set_at' => now(),
            'accepted_at' => now(),
        ];

        $session = $existing
            ? tap($existing)->update($attributes)
            : ArbitrationSession::create($attributes + ['dispute_id' => (int) $dispute->id]);

        $this->announceTerms($dispute, $session);

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

    /** The platform's price for a session on this dispute's service. */
    public function sessionFeeFor(Dispute $dispute): int
    {
        return DisputeFee::amountFor(
            $dispute->platform_service_id ? (int) $dispute->platform_service_id : null
        );
    }

    protected function announceTerms(Dispute $dispute, ArbitrationSession $session): void
    {
        $body = sprintf(
            'قبل المحكّم النظر في النزاع. رسم الجلسة %s، يتحمله الطرف الخاسر وحده.',
            number_format((float) $session->fee_amount, 0)
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
    public function applyPlatformFine(
        Dispute $dispute,
        string $side,
        float $amount,
        string $reason
    ): ?ArbitrationSession {
        $amount = round(max($amount, 0), 2);

        if ($amount <= 0) {
            return null;
        }

        if (! in_array($side, ['client', 'business'], true)) {
            throw ValidationException::withMessages([
                'platform_fine_on' => __('يجب تحديد الطرف الذي تُفرض عليه الغرامة.'),
            ]);
        }

        // Losing a dispute is not a punishable act. A fine rests on exactly two
        // grounds — behaving badly in the room, or refusing the ruling — and
        // without one of them it would just be a second penalty for being
        // wrong, on top of losing the escrow.
        if (! in_array($reason, ArbitrationSession::FINE_REASONS, true)) {
            throw ValidationException::withMessages([
                'platform_fine_reason' => __('الغرامة لا تُفرض إلا على التعدي أو عدم الخضوع للحكم.'),
            ]);
        }

        // A misconduct fine has to point at misconduct that was actually
        // recorded, in the room, where the party could see it.
        if ($reason === ArbitrationSession::FINE_CONDUCT && ! $this->hasRecordedMisconduct($dispute, $side)) {
            throw ValidationException::withMessages([
                'platform_fine_reason' => __('لا توجد مخالفة سلوك مسجّلة على هذا الطرف.'),
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

        $obligation = app(DisputeCollectionService::class)->charge(
            dispute: $dispute,
            userId: $payerId,
            type: DisputeObligation::TYPE_PLATFORM_FINE,
            amount: $amount
        );

        $session->update([
            'platform_fine_amount' => $amount,
            'platform_fine_on' => $side,
            'platform_fine_reason' => $reason,
        ]);

        if ($obligation->isPending()) {
            app(DisputeCollectionService::class)->notifyUnpaid($obligation);
        }

        return $session->fresh();
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
    public function chargeArbitrationFee(Dispute $dispute): ?ArbitrationSession
    {
        $session = ArbitrationSession::query()->where('dispute_id', $dispute->id)->first();

        if (! $session || (float) $session->fee_amount <= 0) {
            return $session;
        }

        if ($session->fee_on !== null) {
            return $session; // already collected
        }

        $side = $this->losingSide($session);

        if ($side === null) {
            throw ValidationException::withMessages([
                'arbitration_fee' => __('لا يمكن تحصيل رسم الجلسة: لا يوجد طرف خاسر في هذا القرار.'),
            ]);
        }

        $amount = round((float) $session->fee_amount, 2);
        $payerId = $this->partyId($dispute, $side);

        if (! $payerId) {
            throw ValidationException::withMessages([
                'arbitration_fee' => __('تعذر تحديد الطرف الخاسر.'),
            ]);
        }

        // Recorded as a debt and then paid, never "paid or thrown": a wallet
        // that cannot cover it today does not make the ruling go away.
        $obligation = app(DisputeCollectionService::class)->charge(
            dispute: $dispute,
            userId: $payerId,
            type: DisputeObligation::TYPE_SESSION_FEE,
            amount: $amount
        );

        $session->update(['fee_on' => $side]);

        if ($obligation->isPending()) {
            app(DisputeCollectionService::class)->notifyUnpaid($obligation);
        }

        return $session->fresh();
    }

    /**
     * Who lost, read off the ruling itself.
     *
     * Derived, never chosen: letting an arbitrator name the payer would make
     * the fee a second penalty they hand out at will, and the point of putting
     * it on the loser is that the ruling already decided who that is.
     *
     * Returns null when the ruling names no loser — an even split, no_action,
     * or a settlement the parties reached themselves. There is genuinely nobody
     * to charge in those cases, and inventing one would be arbitrary.
     */
    public function losingSide(ArbitrationSession $session): ?string
    {
        return match ($session->outcome) {
            'refund_client' => 'business',
            'release_business' => 'client',
            'split' => match (true) {
                (float) $session->client_percent > (float) $session->business_percent => 'business',
                (float) $session->business_percent > (float) $session->client_percent => 'client',
                default => null, // an even split declares no loser
            },
            default => null,
        };
    }

    /* ======================== compensation ======================== */

    /**
     * What may actually be claimed, read off the operation itself.
     *
     * The point is that nobody can be made to pay more than was ever agreed:
     * a compensation typed freehand can name any figure, while a line read from
     * the order can only ever name what the parties already signed up to. The
     * arbitrator picks which lines are owed; the amount is their sum, never a
     * number of the arbitrator's own.
     *
     * The platform's own service fee is deliberately NOT claimable. It went to
     * the platform, not to the counterparty, so making the counterparty refund
     * it would charge them for money they never received.
     *
     * @return array<int, array{key: string, label: string, amount: float}>
     */
    public function claimableLines(Dispute $dispute): array
    {
        $subject = $dispute->disputeable_type && class_exists($dispute->disputeable_type)
            ? $dispute->disputeable_type::find($dispute->disputeable_id)
            : null;

        if ($subject instanceof Booking) {
            return array_values(array_filter([
                $this->line('booking_price', 'قيمة الحجز', (float) $subject->price),
            ]));
        }

        if ($subject instanceof Order) {
            return array_values(array_filter([
                $this->line('goods', 'قيمة الطلب', (float) $subject->total),
                $this->line('delivery_fee', 'رسوم الشحن', (float) $subject->delivery_fee),
                $this->line('tax', 'الضريبة', (float) $subject->tax),
            ]));
        }

        return [];
    }

    private function line(string $key, string $label, float $amount): ?array
    {
        $amount = round($amount, 2);

        return $amount > 0 ? ['key' => $key, 'label' => __($label), 'amount' => $amount] : null;
    }

    /**
     * Turn a set of chosen line keys into the amount owed.
     *
     * An unknown key is refused rather than ignored: silently dropping one
     * would hand down a smaller award than the arbitrator believed they were
     * making.
     */
    public function amountForLines(Dispute $dispute, array $keys): float
    {
        $available = collect($this->claimableLines($dispute))->keyBy('key');

        $keys = array_values(array_unique(array_filter($keys)));

        if ($keys === []) {
            throw ValidationException::withMessages([
                'compensation_lines' => __('اختر بندًا واحدًا على الأقل من بنود العملية.'),
            ]);
        }

        $total = 0.0;

        foreach ($keys as $key) {
            if (! $available->has($key)) {
                throw ValidationException::withMessages([
                    'compensation_lines' => __('بند غير موجود في هذه العملية.'),
                ]);
            }

            $total += (float) $available[$key]['amount'];
        }

        return round($total, 2);
    }


    /**
     * Order one party to compensate the other — shipping already paid, a cost
     * incurred, a difference in value. Distinct from the escrow, which is only
     * ever the money the platform was already holding: a real loss is often
     * larger than the deposit, or has nothing to do with it.
     *
     * ORDERING and PAYING are separate. The escrow moves the instant a ruling
     * lands because the platform holds it; compensation comes out of a wallet
     * that may be empty. So the order is always recorded, payment is attempted,
     * and a shortfall leaves it unpaid rather than silently not happening —
     * which is also precisely the `non_compliance` a fine may then rest on.
     */
    public function awardCompensation(
        Dispute $dispute,
        string $toSide,
        array $lineKeys,
        ?string $note = null
    ): ArbitrationSession {
        if (! in_array($toSide, ['client', 'business'], true)) {
            throw ValidationException::withMessages([
                'compensation_to' => __('يجب تحديد الطرف المستحق للتعويض.'),
            ]);
        }

        // The amount is the sum of lines taken from the operation, never a
        // figure anyone typed. That is the whole guard: a line can only ever
        // name what the parties already agreed to.
        $amount = $this->amountForLines($dispute, $lineKeys);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'compensation_amount' => __('قيمة التعويض غير صالحة.'),
            ]);
        }

        $session = ArbitrationSession::query()->where('dispute_id', $dispute->id)->first();

        if (! $session || $session->isOpen()) {
            throw ValidationException::withMessages([
                'compensation_amount' => __('لا يمكن الحكم بتعويض قبل صدور القرار.'),
            ]);
        }

        if ((float) $session->compensation_amount > 0) {
            return $session; // one compensation order per ruling
        }

        $session->update([
            'compensation_amount' => $amount,
            'compensation_to' => $toSide,
            // The chosen lines ARE the justification, so they are kept even
            // when the arbitrator wrote nothing of their own.
            'compensation_note' => trim(implode(' + ', $this->labelsFor($dispute, $lineKeys))
                . ($note ? ' — ' . $note : '')),
        ]);

        $this->announceCompensation($dispute, $toSide, $amount, implode(' + ', $this->labelsFor($dispute, $lineKeys)));

        // Compensation is collected through the SAME ledger as the fee and the
        // fine — recorded as a debt, paid from the wallet if it can be, and
        // otherwise left pending so it blocks new operations and, after the
        // window, is taken from the guarantee. Its own direct-transfer path
        // used to bypass all of that: an unaffordable compensation stayed
        // unpaid on the session but created no obligation, so the block never
        // fired and — worse — a compliance close saw nothing outstanding and
        // could certify a ruling that was never carried out.
        $payerSide = $toSide === 'client' ? 'business' : 'client';
        $payerId = $this->partyId($dispute, $payerSide);
        $payeeId = $this->partyId($dispute, $toSide);

        if ($payerId && $payeeId) {
            $obligation = app(DisputeCollectionService::class)->charge(
                dispute: $dispute,
                userId: $payerId,
                type: DisputeObligation::TYPE_COMPENSATION,
                amount: $amount,
                payeeUserId: $payeeId
            );

            if ($obligation->status === DisputeObligation::STATUS_PAID) {
                $session->update(['compensation_paid_at' => $obligation->paid_at]);
            } else {
                app(DisputeCollectionService::class)->notifyUnpaid($obligation);
            }
        }

        return $session->fresh();
    }

    /**
     * Retry an ordered compensation the payer could not afford at the time.
     *
     * Delegates to the collection ledger, which is where the debt actually
     * lives now; the session's `compensation_paid_at` is kept in step for the
     * admin screen.
     */
    public function settleCompensation(Dispute $dispute): ArbitrationSession
    {
        $session = ArbitrationSession::query()->where('dispute_id', $dispute->id)->firstOrFail();

        $obligation = DisputeObligation::query()
            ->where('dispute_id', $dispute->id)
            ->where('type', DisputeObligation::TYPE_COMPENSATION)
            ->first();

        if ($obligation && $obligation->isPending()) {
            $obligation = app(DisputeCollectionService::class)->settle($obligation);
        }

        if ($obligation && $obligation->status === DisputeObligation::STATUS_PAID && $session->compensation_paid_at === null) {
            $session->update(['compensation_paid_at' => $obligation->paid_at]);
        }

        return $session->fresh();
    }

    /** @return array<int, string> */
    private function labelsFor(Dispute $dispute, array $keys): array
    {
        $available = collect($this->claimableLines($dispute))->keyBy('key');

        return collect($keys)
            ->map(fn ($key) => $available[$key]['label'] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    protected function announceCompensation(Dispute $dispute, string $toSide, float $amount, ?string $note): void
    {
        $body = sprintf(
            'حكم المحكّم بتعويض قدره %s لصالح %s%s',
            number_format($amount, 2),
            $toSide === 'client' ? 'العميل' : 'النشاط',
            $note ? ' — ' . $note : '.'
        );

        try {
            app(ThreadService::class)->system(app(DisputeService::class)->room($dispute), $body);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function notifyCompensation(int $payeeId, int $payerId, Dispute $dispute, float $amount): void
    {
        $notices = [
            $payeeId => 'أُضيف إلى محفظتك مبلغ ' . number_format($amount, 2) . ' كتعويض بحكم التحكيم.',
            $payerId => 'خُصم من محفظتك مبلغ ' . number_format($amount, 2) . ' تعويضًا للطرف الآخر بحكم التحكيم.',
        ];

        foreach ($notices as $userId => $body) {
            try {
                $this->notifications->create([
                    'user_id' => $userId,
                    'type' => AppNotification::TYPE_DISPUTE,
                    'priority' => AppNotification::PRIORITY_HIGH,
                    'title_ar' => 'تعويض بحكم التحكيم',
                    'title_en' => 'Compensation awarded by the ruling',
                    'body_ar' => $body,
                    'notifiable_type' => Dispute::class,
                    'notifiable_id' => (int) $dispute->id,
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /** Was a conduct violation actually recorded against this side, in the room? */
    private function hasRecordedMisconduct(Dispute $dispute, string $side): bool
    {
        $userId = $this->partyId($dispute, $side);

        if (! $userId) {
            return false;
        }

        $thread = Thread::query()
            ->where('subject_type', $dispute->getMorphClass())
            ->where('subject_id', $dispute->getKey())
            ->first();

        if (! $thread) {
            return false;
        }

        return ConductViolation::query()
            ->where('thread_id', $thread->id)
            ->where('against_user_id', $userId)
            ->exists();
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

    private function notifyFine(int $userId, Dispute $dispute, float $amount, string $reason): void
    {
        try {
            $this->notifications->create([
                'user_id' => $userId,
                'type' => AppNotification::TYPE_DISPUTE,
                'priority' => AppNotification::PRIORITY_HIGH,
                'title_ar' => 'غرامة منصة على نزاع',
                'title_en' => 'A platform fine was imposed',
                // The ground is named in the notice: a fine whose reason the
                // party has to guess is one they cannot contest.
                'body_ar' => 'خُصم من محفظتك مبلغ ' . number_format($amount, 2) . ' كغرامة منصة بسبب '
                    . ($reason === ArbitrationSession::FINE_CONDUCT ? 'مخالفة سلوك' : 'عدم الخضوع للحكم') . '.',
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
