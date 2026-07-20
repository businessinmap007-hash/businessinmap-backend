<?php

namespace App\Services;

use App\Models\ArbitrationSession;
use App\Models\Booking;
use App\Models\Deposit;
use App\Models\Dispute;
use App\Models\User;
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
     * Write the session for a ruling. Called once per dispute, by the ruling
     * itself, so no arbitrator can decide whether their case gets recorded.
     */
    public function recordSession(
        Dispute $dispute,
        string $outcome,
        array $payload = [],
        ?int $arbitratorId = null
    ): ArbitrationSession {
        $existing = ArbitrationSession::query()->where('dispute_id', $dispute->id)->first();

        if ($existing) {
            return $existing;
        }

        [$toClient, $toBusiness] = $this->amountsMoved($dispute, $outcome, $payload);

        return ArbitrationSession::create([
            'dispute_id' => (int) $dispute->id,
            'arbitrator_id' => $arbitratorId,
            'outcome' => $outcome,
            'client_percent' => $outcome === 'split' ? (float) ($payload['client_percent'] ?? 0) : null,
            'business_percent' => $outcome === 'split' ? (float) ($payload['business_percent'] ?? 0) : null,
            'amount_to_client' => $toClient,
            'amount_to_business' => $toBusiness,
            'platform_fine_amount' => 0,
            'notes' => $payload['notes'] ?? null,
        ]);
    }

    /** What the escrow actually did, in money rather than in percentages. */
    private function amountsMoved(Dispute $dispute, string $outcome, array $payload): array
    {
        $deposit = $this->depositFor($dispute);
        $total = $deposit ? (float) $deposit->client_amount + (float) $deposit->business_amount : 0.0;

        return match ($outcome) {
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

            return $session->fresh();
        });
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
            'by_outcome' => $rows->groupBy('outcome')->map->count()->all(),
            'fines_collected' => round((float) $rows->sum('platform_fine_amount'), 2),
            'moved_to_clients' => round((float) $rows->sum('amount_to_client'), 2),
            'moved_to_businesses' => round((float) $rows->sum('amount_to_business'), 2),
            'last_session_at' => optional($rows->max('created_at'))->toDateTimeString(),
        ];
    }
}
