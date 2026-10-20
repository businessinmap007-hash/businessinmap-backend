<?php

namespace App\Services\Moderation;

use App\Models\AccountCase;
use App\Models\UserGuarantee;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\ContentReport;
use App\Models\Thread;
use App\Models\User;
use App\Services\AccountDeletionService;
use App\Services\BanService;
use App\Services\Notifications\NotificationDispatcherService;
use App\Services\Wallet\PlatformTreasuryService;
use App\Services\OperationChatService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A reported ACCOUNT is never removed (owner, 2026-10-29). The moderator may open a chat with it — to learn what the report is
 * about — and may FREEZE it for three days (`bim.moderation.freeze_days`); a frozen account keeps exactly one screen, the chat
 * with the moderators (see BlockBannedUsers). The freeze ends by itself, or the moderator lifts it earlier.
 *
 * Everything hangs on an {@see AccountCase}: the moderator's file on the account, whose chat is the thread
 * `operation-chats/moderation/{case}`.
 */
final class AccountModerationService
{
    public function __construct(
        private readonly OperationChatService $chats,
        private readonly NotificationDispatcherService $notifications,
        private readonly BanService $bans,
        private readonly AccountDeletionService $deletion,
        private readonly PlatformTreasuryService $treasury
    ) {}

    /** The account's open case, or a new one in the name of this moderator. */
    public function openCase(User $account, User $moderator): AccountCase
    {
        $case = AccountCase::query()->where('user_id', $account->id)->where('status', AccountCase::STATUS_OPEN)->latest('id')->first();

        if ($case) {
            if ((int) $case->opened_by !== (int) $moderator->id) {
                $case->update(['opened_by' => (int) $moderator->id]);
            }

            return $case;
        }

        return AccountCase::create([
            'user_id' => (int) $account->id,
            'opened_by' => (int) $moderator->id,
            'status' => AccountCase::STATUS_OPEN,
            'reason' => $this->reasonOf($account),
        ]);
    }

    /** Open the chat with the account: the moderator asks, the account answers. The account is told. */
    public function openChat(User $account, User $moderator): AccountCase
    {
        $case = $this->openCase($account, $moderator);
        $existed = Thread::query()->where('subject_type', $case->getMorphClass())->where('subject_id', $case->id)->exists();

        $this->chats->open($case);

        if (! $existed) {
            $this->tell($account, 'moderation_chat_opened', $case,
                'فتح مشرف محادثة معك بخصوص بلاغ عن حسابك. اكتب له ما يوضح الأمر.',
                'A moderator opened a chat with you about a report on your account. Tell them what happened.');
        }

        return $case;
    }

    /**
     * Freeze the account for the configured days; the chat is opened too, so the account can answer. Every freeze is counted, and
     * the Nth (`bim.moderation.freezes_to_delete`, 5) deletes the account and confiscates its balance and guarantee instead.
     *
     * @return array{case:AccountCase,deleted:bool,count:int,confiscated:float}
     */
    public function freeze(User $account, User $moderator): array
    {
        $this->assertCanBeFrozen($account);

        $case = $this->openChat($account, $moderator);
        $limit = (int) config('bim.moderation.freezes_to_delete', 5);

        // already frozen: nothing new to count
        if ($account->isFrozen()) {
            return ['case' => $case, 'deleted' => false, 'count' => (int) $account->freeze_count, 'confiscated' => 0.0];
        }

        $count = (int) $account->freeze_count + 1;

        if ($count >= $limit) {
            $taken = $this->deleteAndConfiscate($account, $moderator, $case, $count);

            return ['case' => $case->refresh(), 'deleted' => true, 'count' => $count, 'confiscated' => $taken];
        }

        $until = now()->addDays((int) config('bim.moderation.freeze_days', 3));

        DB::transaction(function () use ($account, $case, $until, $count) {
            $account->forceFill(['frozen_until' => $until, 'freeze_count' => $count])->save();
            $case->update(['frozen_until' => $until, 'outcome' => 'frozen']);
        });

        $days = (int) config('bim.moderation.freeze_days', 3);
        $this->tell($account, 'moderation_account_frozen', $case,
            "جُمِّد حسابك {$days} أيام بسبب بلاغات عنه (هذا التجميد رقم {$count} من {$limit}). إذا تكرر التجميد {$limit} مرات يُحذف الحساب ويُصادَر رصيده وضمانه. يمكنك التواصل مع المشرفين من شاشة التجميد.",
            "Your account is frozen for {$days} days after reports about it (freeze {$count} of {$limit}). If it is frozen {$limit} times the account is deleted and its balance and guarantee are confiscated. Use the freeze screen to talk to the moderators.");

        return ['case' => $case->refresh(), 'deleted' => false, 'count' => $count, 'confiscated' => 0.0];
    }

    /**
     * The last straw: the free balance and the guarantee's locked money go to the platform treasury as a fine, the guarantee is
     * cancelled, the account is banned (its identities blocked, its sessions revoked) and its deletion is requested. If
     * something still blocks the deletion (a live booking…) the ban stands, so the account is unusable either way.
     */
    private function deleteAndConfiscate(User $account, User $moderator, AccountCase $case, int $count): float
    {
        $taken = $this->confiscateFunds($account);

        $account->forceFill(['freeze_count' => $count, 'frozen_until' => null])->save();
        // deletion first: a banned account is "permanently suspended" and refuses to be deleted. The ban follows, so the
        // identities are blocked and, when something (a live booking…) still blocks the deletion, the account is unusable anyway.
        try {
            $this->deletion->request($account->refresh(), 'frozen ' . $count . ' times by moderators');
        } catch (\RuntimeException $e) {
            report($e);
        }

        $this->bans->ban($account->refresh(), "حُذف الحساب بعد تجميده {$count} مرات", (int) $moderator->id);

        $this->close($case, $moderator, 'deleted');

        return $taken;
    }

    /** Balance + the guarantee's locked money → the treasury (PURPOSE_FINE); the guarantee is cancelled. Returns the sum. */
    private function confiscateFunds(User $account): float
    {
        return DB::transaction(function () use ($account) {
            $wallet = Wallet::query()->where('user_id', $account->id)->lockForUpdate()->first();
            $guarantees = UserGuarantee::query()->where('user_id', $account->id)->whereNotIn('status', [UserGuarantee::STATUS_CANCELLED])->lockForUpdate()->get();

            $fromBalance = $wallet ? round((float) $wallet->balance, 2) : 0.0;
            // only what the guarantee itself locked — never another operation's hold
            $guaranteeLocked = $wallet ? min(round((float) $wallet->locked_balance, 2), round((float) $guarantees->sum('locked_amount'), 2)) : 0.0;
            $total = round($fromBalance + $guaranteeLocked, 2);

            foreach ($guarantees as $g) {
                $g->forceFill(['locked_amount' => 0, 'status' => UserGuarantee::STATUS_CANCELLED, 'cancelled_at' => now()])->save();
            }

            if (! $wallet || $total <= 0) {
                return 0.0;
            }

            $key = 'account_forfeit:' . (int) $account->id;
            $before = round((float) $wallet->balance, 2);
            $lockedBefore = round((float) $wallet->locked_balance, 2);

            $wallet->balance = 0;
            $wallet->locked_balance = round($lockedBefore - $guaranteeLocked, 2);
            $wallet->total_out = round((float) $wallet->total_out + $total, 2);
            $wallet->last_activity_at = now();
            $wallet->save();

            WalletTransaction::create([
                'wallet_id' => (int) $wallet->id,
                'user_id' => (int) $account->id,
                'status' => WalletTransaction::STATUS_COMPLETED,
                'direction' => WalletTransaction::DIRECTION_OUT,
                'type' => WalletTransaction::TYPE_TRANSFER,
                'amount' => $total,
                'balance_before' => $before,
                'balance_after' => 0,
                'locked_before' => $lockedBefore,
                'locked_after' => round($lockedBefore - $guaranteeLocked, 2),
                'reference_type' => 'account_forfeit',
                'reference_id' => (string) $account->id,
                'idempotency_key' => $key,
                'note' => 'مصادرة رصيد وضمان حساب بعد تكرار تجميده',
                'meta' => ['from_balance' => $fromBalance, 'from_guarantee' => $guaranteeLocked],
            ]);

            $this->treasury->credit($total, PlatformTreasuryService::PURPOSE_FINE, (string) $account->id, $key . ':treasury', ['context' => 'account_forfeit']);

            return $total;
        });
    }

    /** The moderator lifts the freeze before its time and closes the case. */
    public function unfreeze(User $account, User $moderator): void
    {
        $account->forceFill(['frozen_until' => null])->save();

        AccountCase::query()->where('user_id', $account->id)->where('status', AccountCase::STATUS_OPEN)->get()
            ->each(fn (AccountCase $c) => $this->close($c, $moderator, 'cleared'));

        $this->tell($account, 'moderation_account_frozen', null,
            'رُفع التجميد عن حسابك. يمكنك استخدامه من جديد.',
            'The freeze on your account was lifted. You can use it again.');
    }

    /** The moderator closes the file: the chat starts its short retention clock. */
    public function close(AccountCase $case, User $moderator, string $outcome): void
    {
        $case->update(['status' => AccountCase::STATUS_CLOSED, 'outcome' => $outcome, 'closed_by' => (int) $moderator->id, 'closed_at' => now()]);
    }

    /** What the reported account sees: its open case, or one whose freeze is still running. */
    public function caseFor(User $user): ?AccountCase
    {
        return AccountCase::query()->where('user_id', $user->id)
            ->where(fn ($q) => $q->where('status', AccountCase::STATUS_OPEN)->orWhere('frozen_until', '>', now()))
            ->latest('id')->first();
    }

    private function assertCanBeFrozen(User $account): void
    {
        if ((string) $account->type === 'admin' || $account->isBanned()) {
            throw ValidationException::withMessages(['target' => __('لا يمكن تجميد هذا الحساب.')]);
        }
    }

    /** «احتيال × 2، سبب آخر × 1» — what the open reports on the account said. */
    private function reasonOf(User $account): string
    {
        $rows = ContentReport::query()->where('reportable_type', 'account')->where('reportable_id', $account->id)->where('status', ContentReport::STATUS_OPEN)
            ->selectRaw('reason, COUNT(*) as n')->groupBy('reason')->orderByDesc('n')->get();

        return mb_substr($rows->map(fn ($r) => (ContentReportService::REASONS[$r->reason] ?? ContentReportService::REASONS['other'])[0] . ' × ' . $r->n)->implode('، '), 0, 255);
    }

    private function tell(User $account, string $event, ?AccountCase $case, string $ar, string $en): void
    {
        try {
            $this->notifications->dispatch($event, (int) $account->id, [
                'body_ar' => $ar,
                'body_en' => $en,
                'action_type' => 'open_moderation_case',
                'notifiable_type' => AccountCase::class,
                'notifiable_id' => $case ? (int) $case->id : null,
                'source_type' => $event,
                'source_id' => $case ? (int) $case->id : (int) $account->id,
                'meta' => ['case_id' => $case?->id],
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
