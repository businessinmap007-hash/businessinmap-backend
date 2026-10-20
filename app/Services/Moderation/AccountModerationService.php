<?php

namespace App\Services\Moderation;

use App\Models\AccountCase;
use App\Models\ContentReport;
use App\Models\Thread;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
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
        private readonly NotificationDispatcherService $notifications
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

    /** Freeze the account for the configured days; the chat is opened too, so the account can answer. */
    public function freeze(User $account, User $moderator): AccountCase
    {
        $this->assertCanBeFrozen($account);

        $case = $this->openChat($account, $moderator);
        $until = now()->addDays((int) config('bim.moderation.freeze_days', 3));

        DB::transaction(function () use ($account, $case, $until) {
            $account->forceFill(['frozen_until' => $until])->save();
            $case->update(['frozen_until' => $until, 'outcome' => 'frozen']);
        });

        $days = (int) config('bim.moderation.freeze_days', 3);
        $this->tell($account, 'moderation_account_frozen', $case,
            "جُمِّد حسابك {$days} أيام بسبب بلاغات عنه. يمكنك التواصل مع المشرفين من شاشة التجميد ليتأكدوا من السبب.",
            "Your account is frozen for {$days} days after reports about it. Use the freeze screen to talk to the moderators.");

        return $case->refresh();
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
