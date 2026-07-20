<?php

namespace App\Services;

use App\Models\BlockedIdentity;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\OperationGuarantor;
use App\Models\TripReservation;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Wallet\PlatformTreasuryService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * BIM-15.1 — deleting an account without losing anyone's money.
 *
 * Deletion is two steps separated by a grace window, because "recoverable for
 * 30 days" and "the balance moves to the platform at deletion" cannot both be
 * true at once:
 *
 *   day 0   request()  — the account is soft-deleted, its tokens are revoked and
 *                        its wallet is frozen. THE BALANCE DOES NOT MOVE. The
 *                        account is gone to the world and intact in the ledger.
 *   ≤ grace restore()  — everything comes back, balance included, because
 *                        nothing was ever taken.
 *   > grace finalize() — the balance escheats to the treasury and the identity
 *                        is scrubbed. Irreversible.
 *
 * Nothing is deleted while it could still owe or be owed: see blockers(). The
 * account row itself is never hard-deleted — the ledger, ratings and invoices of
 * everyone the user ever traded with point at its id, and those are other
 * people's records. Anonymization empties the person out of the row and leaves
 * the row.
 */
class AccountDeletionService
{
    /** reference_type on the escheat debit — the counterpart to PURPOSE_ESCHEAT. */
    public const REFERENCE_ESCHEAT = 'account_escheat';

    /** Operations that mean the user is still mid-deal with somebody. */
    private const LIVE_BOOKING_STATUSES = [
        Booking::STATUS_PENDING,
        Booking::STATUS_ACCEPTED,
        Booking::STATUS_IN_PROGRESS,
    ];

    private const LIVE_ORDER_STATUSES = ['pending', 'confirmed'];

    private const LIVE_DELIVERY_STATUSES = ['pending', 'accepted', 'delivering'];

    private const LIVE_RESERVATION_STATUSES = [
        TripReservation::STATUS_PENDING,
        TripReservation::STATUS_CONFIRMED,
    ];

    /** Public: the held-deletions admin screen counts the same live disputes. */
    public const LIVE_DISPUTE_STATUSES = [
        Dispute::STATUS_OPEN,
        Dispute::STATUS_UNDER_REVIEW,
        Dispute::STATUS_MUTUAL_RESOLUTION,
    ];

    public function __construct(
        private readonly PlatformTreasuryService $treasury,
    ) {}

    // ---------------------------------------------------------------- blockers

    /**
     * Everything standing between this account and deletion, in the user's
     * language. Empty array = deletable.
     *
     * @return array<int, array{code: string, message: string, count?: int}>
     */
    public function blockers(User $user): array
    {
        $id = (int) $user->id;
        $blockers = [];

        // A ban is enforced on the email and phone — which finalize() scrubs.
        // Allowing this would make deletion the "undo" button for a permanent
        // ban: delete, re-register, clean slate.
        if ($user->isBanned()) {
            $blockers[] = [
                'code' => 'banned',
                'message' => __('الحساب موقوف نهائيًا ولا يمكن حذفه.'),
            ];
        }

        if ($this->treasury->isTreasury($id)) {
            $blockers[] = [
                'code' => 'platform_account',
                'message' => __('حساب المنصة لا يُحذف.'),
            ];
        }

        $disputes = Dispute::query()
            ->whereIn('status', self::LIVE_DISPUTE_STATUSES)
            ->where(fn ($q) => $q->where('opened_by_user_id', $id)->orWhere('against_user_id', $id))
            ->count();

        if ($disputes > 0) {
            $blockers[] = [
                'code' => 'open_dispute',
                'message' => __('يوجد نزاع مفتوح. لا يمكن حذف الحساب حتى ينتهي النزاع.'),
                'count' => $disputes,
            ];
        }

        // Pending operations on either side: the user may be the client or the
        // business. Deleting either party mid-operation strands the other one.
        $counts = [
            'pending_bookings' => Booking::query()
                ->whereIn('status', self::LIVE_BOOKING_STATUSES)
                ->where(fn ($q) => $q->where('user_id', $id)->orWhere('business_id', $id))
                ->count(),

            'pending_orders' => DB::table('orders')
                ->whereIn('status', self::LIVE_ORDER_STATUSES)
                ->where(fn ($q) => $q->where('user_id', $id)->orWhere('business_id', $id))
                ->count(),

            'pending_deliveries' => DB::table('delivery_orders')
                ->whereIn('status', self::LIVE_DELIVERY_STATUSES)
                ->where(fn ($q) => $q->where('user_id', $id)->orWhere('business_id', $id))
                ->count(),

            'pending_reservations' => TripReservation::query()
                ->whereIn('status', self::LIVE_RESERVATION_STATUSES)
                ->where(fn ($q) => $q->where('client_id', $id)->orWhere('business_id', $id))
                ->count(),
        ];

        foreach ($counts as $code => $count) {
            if ($count > 0) {
                $blockers[] = [
                    'code' => $code,
                    'message' => __('يوجد عمليات معلقة يجب إنهاؤها أولًا.'),
                    'count' => $count,
                ];
            }
        }

        // Their guarantee is currently covering somebody else's live operation —
        // that promise is not theirs to walk away from.
        $guaranteeing = OperationGuarantor::query()
            ->where('guarantor_user_id', $id)
            ->where('status', OperationGuarantor::STATUS_ACCEPTED)
            ->count();

        if ($guaranteeing > 0) {
            $blockers[] = [
                'code' => 'guarantor_obligation',
                'message' => __('أنت ضامن لعملية جارية لصديق. لا يمكن حذف الحساب حتى تنتهي.'),
                'count' => $guaranteeing,
            ];
        }

        // Locked balance is escrow: a deposit held against a live operation. It
        // is not the account holder's money to take away, and freezing the
        // wallet would strand it (release() refuses a blocked wallet).
        $locked = round((float) (Wallet::query()->where('user_id', $id)->value('locked_balance') ?? 0), 2);

        if ($locked > 0) {
            $blockers[] = [
                'code' => 'locked_balance',
                'message' => __('يوجد مبلغ محجوز كعربون (:amount). يجب إنهاء العملية المرتبطة به أولًا.', ['amount' => number_format($locked, 2)]),
            ];
        }

        return $blockers;
    }

    public function canDelete(User $user): bool
    {
        return $this->blockers($user) === [];
    }

    // -------------------------------------------------------- balance transfer

    /**
     * May this user move their balance out right now?
     *
     * The cooldown exists so nobody can transact, drain the wallet and vanish
     * before the counterparty notices: the money must stay reachable for a few
     * days after the last thing the user did.
     *
     * @return array{allowed: bool, reason: ?string, available_at: ?string}
     */
    public function balanceTransferGate(User $user): array
    {
        $pending = array_values(array_filter(
            $this->blockers($user),
            fn ($b) => $b['code'] !== 'locked_balance'
        ));

        if ($pending !== []) {
            return [
                'allowed' => false,
                'reason' => $pending[0]['message'],
                'available_at' => null,
            ];
        }

        $days = (int) config('bim.account_deletion.balance_transfer_cooldown_days', 3);
        $last = $this->lastActivityAt($user);

        if ($last === null || $days <= 0) {
            return ['allowed' => true, 'reason' => null, 'available_at' => null];
        }

        $availableAt = $last->copy()->addDays($days);

        if ($availableAt->isFuture()) {
            return [
                'allowed' => false,
                'reason' => __('يجب مرور :days أيام على آخر عملية أو نزاع قبل تحويل الرصيد.', ['days' => $days]),
                'available_at' => $availableAt->toIso8601String(),
            ];
        }

        return ['allowed' => true, 'reason' => null, 'available_at' => null];
    }

    /** The most recent operation or dispute touching this user, either side. */
    public function lastActivityAt(User $user): ?\Illuminate\Support\Carbon
    {
        $id = (int) $user->id;

        $stamps = [
            Booking::query()->where(fn ($q) => $q->where('user_id', $id)->orWhere('business_id', $id))->max('created_at'),
            DB::table('orders')->where(fn ($q) => $q->where('user_id', $id)->orWhere('business_id', $id))->max('created_at'),
            DB::table('delivery_orders')->where(fn ($q) => $q->where('user_id', $id)->orWhere('business_id', $id))->max('created_at'),
            TripReservation::query()->where(fn ($q) => $q->where('client_id', $id)->orWhere('business_id', $id))->max('created_at'),
            Dispute::query()->where(fn ($q) => $q->where('opened_by_user_id', $id)->orWhere('against_user_id', $id))->max('created_at'),
        ];

        $latest = null;

        foreach (array_filter($stamps) as $stamp) {
            $when = \Illuminate\Support\Carbon::parse($stamp);
            if ($latest === null || $when->greaterThan($latest)) {
                $latest = $when;
            }
        }

        return $latest;
    }

    // ----------------------------------------------------------------- request

    /**
     * Day 0. Soft-delete, freeze, revoke — and touch no money.
     *
     * @throws \RuntimeException when something still blocks deletion.
     */
    public function request(User $user, ?string $reason = null): User
    {
        $blockers = $this->blockers($user);

        if ($blockers !== []) {
            throw new \RuntimeException($blockers[0]['message']);
        }

        $graceDays = (int) config('bim.account_deletion.grace_days', 30);

        return DB::transaction(function () use ($user, $reason, $graceDays) {
            $now = now();

            $user->deletion_requested_at = $now;
            // Stored, not derived: changing the config later must not move the
            // date out from under an account that is already waiting.
            $user->deletion_scheduled_at = $now->copy()->addDays($graceDays);
            $user->deletion_hold_reason = null;
            $user->save();

            // Frozen, not emptied. WalletService::ensureActive() makes this real:
            // no deposit, withdraw, hold, release, refund or transfer can touch a
            // blocked wallet, in either direction.
            Wallet::query()->where('user_id', $user->id)->update([
                'status' => Wallet::STATUS_BLOCKED,
            ]);

            $user->tokens()->delete(); // every device is logged out now
            $user->delete();           // soft delete — deleted_at

            Log::info('Account deletion requested.', [
                'user_id' => (int) $user->id,
                'scheduled_at' => $user->deletion_scheduled_at?->toIso8601String(),
                'reason' => $reason,
            ]);

            return $user;
        });
    }

    // ----------------------------------------------------------------- restore

    /**
     * Undo, while there is still something to undo. Returns the account and its
     * balance untouched — possible only because request() moved no money.
     *
     * @throws \RuntimeException once the account has been anonymized.
     */
    public function restore(User $user): User
    {
        if ($user->anonymized_at !== null) {
            throw new \RuntimeException(__('انتهت مهلة الاسترجاع ولا يمكن استعادة الحساب.'));
        }

        return DB::transaction(function () use ($user) {
            $user->deletion_requested_at = null;
            $user->deletion_scheduled_at = null;
            $user->deletion_hold_reason = null;
            $user->restore(); // clears deleted_at and saves

            Wallet::query()->where('user_id', $user->id)->update([
                'status' => Wallet::STATUS_ACTIVE,
            ]);

            Log::info('Account deletion cancelled; account restored.', ['user_id' => (int) $user->id]);

            return $user;
        });
    }

    // ---------------------------------------------------------------- finalize

    /** Accounts whose grace window has run out and that were never finalized. */
    public function dueForFinalization(?int $limit = null): Collection
    {
        return User::onlyTrashed()
            ->whereNotNull('deletion_scheduled_at')
            ->whereNull('anonymized_at')
            ->whereNull('deletion_hold_reason') // already flagged for a human
            ->where('deletion_scheduled_at', '<=', now())
            ->orderBy('deletion_scheduled_at')
            ->when($limit !== null, fn ($q) => $q->limit($limit))
            ->get();
    }

    /**
     * Past the grace window: take the balance and scrub the identity.
     *
     * Refuses rather than guesses. If money is locked, or a dispute appeared
     * after the request, the row is flagged for a human and left completely
     * alone — an automated sweep must never be the thing that seizes contested
     * money.
     *
     * @return array{status: string, escheated: float, reason?: string}
     */
    public function finalize(User $user): array
    {
        if ($user->anonymized_at !== null) {
            return ['status' => 'already_finalized', 'escheated' => 0.0];
        }

        if ($hold = $this->finalizationHold($user)) {
            $user->deletion_hold_reason = $hold;
            $user->save();

            Log::warning('Account finalization held for review.', [
                'user_id' => (int) $user->id,
                'reason' => $hold,
            ]);

            return ['status' => 'held', 'escheated' => 0.0, 'reason' => $hold];
        }

        return DB::transaction(function () use ($user) {
            $escheated = $this->escheatBalance($user);

            // Hash the identities BEFORE they are scrubbed — this is the last
            // moment they exist. Only for an account that is actually banned;
            // an ordinary user who leaves is free to come back.
            if ($user->isBanned()) {
                BlockedIdentity::blockUser(
                    $user,
                    $user->ban_reason ?? 'account banned',
                    BlockedIdentity::SOURCE_FRAUD
                );
            }

            $this->anonymize($user);

            Log::info('Account finalized.', [
                'user_id' => (int) $user->id,
                'escheated' => $escheated,
            ]);

            return ['status' => 'finalized', 'escheated' => $escheated];
        });
    }

    /**
     * Accounts the sweep refused and flagged for a human. dueForFinalization()
     * skips anything with a hold reason, so nothing brings these back on its
     * own — without a screen reading this, they wait forever.
     */
    public function held(?int $limit = null): Collection
    {
        return User::onlyTrashed()
            ->whereNotNull('deletion_hold_reason')
            ->whereNull('anonymized_at')
            ->orderBy('deletion_scheduled_at')
            ->when($limit !== null, fn ($q) => $q->limit($limit))
            ->get();
    }

    /**
     * Whether the stored hold still applies right now. The reason on the row is
     * a snapshot from the sweep; the admin needs to know whether the underlying
     * cause (locked money, a live dispute) has since been resolved, because
     * that is what decides if retrying will do anything.
     */
    public function currentHoldReason(User $user): ?string
    {
        return $this->finalizationHold($user);
    }

    /** A reason not to sweep this account, or null to proceed. */
    private function finalizationHold(User $user): ?string
    {
        $id = (int) $user->id;

        $locked = round((float) (Wallet::query()->where('user_id', $id)->value('locked_balance') ?? 0), 2);

        if ($locked > 0) {
            return 'رصيد محجوز (' . number_format($locked, 2) . ') وقت التنفيذ — يحتاج مراجعة يدوية.';
        }

        $disputes = Dispute::query()
            ->whereIn('status', self::LIVE_DISPUTE_STATUSES)
            ->where(fn ($q) => $q->where('opened_by_user_id', $id)->orWhere('against_user_id', $id))
            ->count();

        if ($disputes > 0) {
            return 'ظهر نزاع بعد طلب الحذف — يحتاج مراجعة يدوية.';
        }

        return null;
    }

    /**
     * Move the available balance to the treasury as escheat (not revenue).
     *
     * The debit is written directly rather than through WalletService::withdraw
     * because the wallet is frozen by design — the freeze is the whole point of
     * the grace window, and unfreezing it to empty it would open exactly the
     * window this is meant to close. Same shape as the fee services: locked row,
     * full before/after ledger, idempotent per account.
     */
    private function escheatBalance(User $user): float
    {
        $wallet = Wallet::query()->where('user_id', $user->id)->lockForUpdate()->first();

        if (! $wallet) {
            return 0.0;
        }

        $amount = round((float) $wallet->balance, 2);

        if ($amount <= 0) {
            return 0.0;
        }

        $idempotencyKey = 'account_escheat:' . (int) $user->id;

        if (WalletTransaction::query()->where('idempotency_key', $idempotencyKey)->exists()) {
            return 0.0; // already swept
        }

        $balanceBefore = $amount;
        $lockedBefore = round((float) $wallet->locked_balance, 2);

        $wallet->balance = 0;
        $wallet->total_out = round((float) $wallet->total_out + $amount, 2);
        $wallet->last_activity_at = now();
        $wallet->save();

        WalletTransaction::create([
            'wallet_id' => (int) $wallet->id,
            'user_id' => (int) $user->id,
            'status' => WalletTransaction::STATUS_COMPLETED,
            'direction' => WalletTransaction::DIRECTION_OUT,
            'type' => WalletTransaction::TYPE_TRANSFER,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => 0,
            'locked_before' => $lockedBefore,
            'locked_after' => $lockedBefore,
            'reference_type' => self::REFERENCE_ESCHEAT,
            'reference_id' => (string) $user->id,
            'idempotency_key' => $idempotencyKey,
            'note' => 'أيلولة رصيد حساب محذوف إلى المنصة',
            'meta' => [
                'user_id' => (int) $user->id,
                'deletion_requested_at' => $user->deletion_requested_at?->toIso8601String(),
                'source' => 'account_deletion',
            ],
        ]);

        $this->treasury->credit(
            amount: $amount,
            purpose: PlatformTreasuryService::PURPOSE_ESCHEAT,
            referenceId: (string) $user->id,
            idempotencyKey: $idempotencyKey . ':treasury',
            meta: ['user_id' => (int) $user->id, 'source' => 'account_deletion']
        );

        return $amount;
    }

    /**
     * Empty the person out of the row, keep the row.
     *
     * Kept on purpose: id (the ledger, ratings and invoices of other people
     * point at it) and created_at — the registration date is the one thing the
     * product decided to keep.
     */
    private function anonymize(User $user): void
    {
        $id = (int) $user->id;

        // Both columns are NOT NULL UNIQUE, so they cannot be nulled — they are
        // replaced with a value derived from the id, which is unique by
        // construction. .invalid is the reserved TLD (RFC 2606): it can never
        // resolve, so this address can never reach a real mailbox.
        $user->email = 'deleted-' . $id . '@deleted.invalid';
        $user->phone = Str::limit('del-' . $id, 15, ''); // phone is varchar(15)
        $user->name = 'حساب محذوف';
        $user->password = bcrypt(Str::random(64)); // no login path back in
        $user->api_token = Str::random(80);        // legacy NOT NULL UNIQUE
        $user->about = null;
        $user->image = null;
        $user->logo = null;
        $user->cover = null;
        $user->latitude = null;
        $user->longitude = null;
        $user->action_code = null;
        $user->code = null;
        $user->remember_token = null;
        $user->anonymized_at = now();
        $user->save();

        $user->tokens()->delete();
    }
}
