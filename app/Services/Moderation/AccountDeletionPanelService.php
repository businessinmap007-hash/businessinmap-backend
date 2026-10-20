<?php

namespace App\Services\Moderation;

use App\Models\AccountDeletionProposal;
use App\Models\AccountDeletionVote;
use App\Models\AccountFreeze;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The fifth recent freeze does not delete an account by one moderator's hand (owner, 2026-10-20). It files a PROPOSAL; a panel of
 * moderators votes on it — `bim.moderation.deletion_approvals` (3) approvals approve it, `deletion_rejections` (2) cancel it, and
 * the moderator who froze the account has already approved. The votes carry no names to the other moderators.
 *
 * An approved deletion does not run at once: the account is told and gets `deletion_grace_days` (3) to object in writing. An
 * objection sends the proposal back to the panel for a second round, and approving it then deletes without a further wait.
 * If the panel cancels, the account simply serves its ordinary three-day freeze.
 */
final class AccountDeletionPanelService
{
    public function __construct(
        private readonly AccountModerationService $accounts,
        private readonly NotificationDispatcherService $notifications
    ) {}

    /** The proposal still alive for this account (undecided, or approved and waiting). */
    public function activeFor(User $account): ?AccountDeletionProposal
    {
        return AccountDeletionProposal::query()->where('user_id', $account->id)
            ->whereIn('status', [AccountDeletionProposal::STATUS_PENDING, AccountDeletionProposal::STATUS_APPROVED])
            ->latest('id')->first();
    }

    /** File the proposal after the fifth recent freeze; the moderator who froze it is the first approval. */
    public function propose(User $account, ?\App\Models\AccountCase $case, User $proposer): AccountDeletionProposal
    {
        $existing = $this->activeFor($account);

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($account, $case, $proposer) {
            $p = AccountDeletionProposal::create([
                'user_id' => (int) $account->id,
                'case_id' => $case ? (int) $case->id : null,
                'proposed_by' => (int) $proposer->id,
                'status' => AccountDeletionProposal::STATUS_PENDING,
                'round' => 1,
            ]);
            AccountDeletionVote::create(['proposal_id' => $p->id, 'moderator_id' => (int) $proposer->id, 'round' => 1, 'vote' => AccountDeletionVote::APPROVE]);

            // a panel of one (config) decides on the spot
            $this->settle($p->refresh());

            return $p->refresh();
        });
    }

    /** One moderator's vote. Returns the proposal as it stands after it. */
    public function vote(int $id, User $moderator, string $vote, ?string $note = null): AccountDeletionProposal
    {
        if (! in_array($vote, [AccountDeletionVote::APPROVE, AccountDeletionVote::REJECT], true)) {
            throw ValidationException::withMessages(['vote' => __('صوت غير صالح.')]);
        }

        return DB::transaction(function () use ($id, $moderator, $vote, $note) {
            $p = AccountDeletionProposal::query()->lockForUpdate()->find($id);

            if (! $p || $p->status !== AccountDeletionProposal::STATUS_PENDING) {
                throw ValidationException::withMessages(['proposal' => __('لا يوجد قرار حذف مفتوح للتصويت.')]);
            }

            if ((int) $p->user_id === (int) $moderator->id) {
                throw ValidationException::withMessages(['proposal' => __('لا يمكنك التصويت على حذف حسابك.')]);
            }

            if ($p->roundVotes()->where('moderator_id', $moderator->id)->exists()) {
                throw ValidationException::withMessages(['proposal' => __('سبق أن صوّتّ على هذا القرار.')]);
            }

            AccountDeletionVote::create([
                'proposal_id' => $p->id, 'moderator_id' => (int) $moderator->id, 'round' => (int) $p->round,
                'vote' => $vote, 'note' => $note !== null ? mb_substr(trim($note), 0, 255) : null,
            ]);

            $this->settle($p->refresh());

            return $p->refresh();
        });
    }

    /** Count the round's votes and, when the panel has spoken, act. */
    private function settle(AccountDeletionProposal $p): void
    {
        $approve = $p->roundVotes()->where('vote', AccountDeletionVote::APPROVE)->count();
        $reject = $p->roundVotes()->where('vote', AccountDeletionVote::REJECT)->count();

        if ($reject >= (int) config('bim.moderation.deletion_rejections', 2)) {
            $p->update(['status' => AccountDeletionProposal::STATUS_CANCELLED, 'decided_at' => now()]);
            $account = User::query()->find($p->user_id);
            $account && $this->tell($account, 'قررت لجنة المشرفين عدم حذف حسابك. يبقى مجمَّدًا مدة التجميد المعتادة فقط.',
                'The moderators\' panel decided not to delete your account. It stays frozen only for the usual freeze.');

            return;
        }

        if ($approve < (int) config('bim.moderation.deletion_approvals', 3)) {
            return;
        }

        $account = User::query()->find($p->user_id);

        // approved after an objection: the grace was already given, so it runs now
        if ($p->objected_at) {
            $p->update(['status' => AccountDeletionProposal::STATUS_APPROVED, 'decided_at' => now(), 'execute_after' => now()]);
            $this->execute($p->refresh());

            return;
        }

        $days = (int) config('bim.moderation.deletion_grace_days', 3);
        $when = now()->addDays($days);
        $p->update(['status' => AccountDeletionProposal::STATUS_APPROVED, 'decided_at' => now(), 'execute_after' => $when]);

        if ($account) {
            // it stays frozen through the grace period
            if (! $account->frozen_until || $account->frozen_until->lt($when)) {
                $account->forceFill(['frozen_until' => $when])->save();
            }

            $this->tell($account,
                "قررت لجنة المشرفين حذف حسابك ومصادرة رصيده وضمانه بعد {$days} أيام من الآن. يمكنك الاعتراض كتابةً من شاشة التجميد قبل ذلك، وسيُعاد عرض الأمر على اللجنة.",
                "The moderators' panel decided to delete your account and confiscate its balance and guarantee in {$days} days. You can object in writing from the freeze screen before then; the panel will look at it again.");
        }
    }

    /** The account's written objection during the grace period: the proposal goes back to the panel for a second round. */
    public function object(User $account, string $text): AccountDeletionProposal
    {
        $text = trim($text);

        if (mb_strlen($text) < 10) {
            throw ValidationException::withMessages(['objection' => __('اكتب اعتراضك بوضوح (10 أحرف على الأقل).')]);
        }

        return DB::transaction(function () use ($account, $text) {
            $p = AccountDeletionProposal::query()->where('user_id', $account->id)
                ->where('status', AccountDeletionProposal::STATUS_APPROVED)->whereNull('objected_at')->lockForUpdate()->latest('id')->first();

            if (! $p) {
                throw ValidationException::withMessages(['objection' => __('لا يوجد قرار حذف يمكنك الاعتراض عليه.')]);
            }

            $p->update([
                'status' => AccountDeletionProposal::STATUS_PENDING,
                'round' => (int) $p->round + 1,
                'objection' => mb_substr($text, 0, 2000),
                'objected_at' => now(),
                'execute_after' => null,
            ]);

            // it stays frozen while the panel looks again
            $keep = now()->addDays((int) config('bim.moderation.deletion_review_days', 14));
            if (! $account->frozen_until || $account->frozen_until->lt($keep)) {
                $account->forceFill(['frozen_until' => $keep])->save();
            }

            $this->tell($account, 'وصلنا اعتراضك وسيُعرض على لجنة المشرفين من جديد. لن يُنفَّذ الحذف قبل قرارهم.',
                'We received your objection; it goes back to the moderators\' panel. Nothing is deleted before they decide.');

            return $p->refresh();
        });
    }

    /** Run the approved deletions whose grace period is over (scheduled). */
    public function executeDue(): int
    {
        $n = 0;

        AccountDeletionProposal::query()->where('status', AccountDeletionProposal::STATUS_APPROVED)
            ->whereNull('objected_at')->where('execute_after', '<=', now())->orderBy('id')->each(function (AccountDeletionProposal $p) use (&$n) {
                $this->execute($p);
                $n++;
            });

        return $n;
    }

    private function execute(AccountDeletionProposal $p): void
    {
        $account = User::query()->find($p->user_id);

        if ($account) {
            $this->accounts->executeDeletion($account, $p);
        }

        $p->update(['status' => AccountDeletionProposal::STATUS_EXECUTED]);
    }

    /** The proposals waiting for a vote, as a moderator sees them: the freezes behind it, the objection, the tally — never names. */
    public function queue(User $moderator): array
    {
        return AccountDeletionProposal::query()->where('status', AccountDeletionProposal::STATUS_PENDING)->orderBy('id')->get()
            ->map(function (AccountDeletionProposal $p) use ($moderator) {
                $account = User::query()->find($p->user_id);
                $mine = $p->roundVotes()->where('moderator_id', $moderator->id)->value('vote');

                return [
                    'id' => (int) $p->id,
                    'round' => (int) $p->round,
                    'account' => $account ? ['id' => (int) $account->id, 'name' => $account->displayName(), 'type' => (string) $account->type] : null,
                    'freezes' => AccountFreeze::query()->where('user_id', $p->user_id)->counting()->orderBy('id')->get(['reason', 'created_at'])
                        ->map(fn ($f) => ['reason' => $f->reason, 'at' => $f->created_at->toIso8601String()])->all(),
                    'objection' => $p->objection,
                    'approvals' => $p->roundVotes()->where('vote', AccountDeletionVote::APPROVE)->count(),
                    'rejections' => $p->roundVotes()->where('vote', AccountDeletionVote::REJECT)->count(),
                    'approvals_needed' => (int) config('bim.moderation.deletion_approvals', 3),
                    'rejections_needed' => (int) config('bim.moderation.deletion_rejections', 2),
                    'my_vote' => $mine,
                    'can_vote' => $mine === null && (int) $p->user_id !== (int) $moderator->id,
                ];
            })->all();
    }

    /** What the account itself may know: that a decision is pending, when it runs, whether it can still object. */
    public function stateFor(User $account): ?array
    {
        $p = $this->activeFor($account);

        if (! $p) {
            return null;
        }

        return [
            'state' => $p->status === AccountDeletionProposal::STATUS_APPROVED ? 'scheduled' : 'review',
            'execute_after' => optional($p->execute_after)->toIso8601String(),
            'can_object' => $p->status === AccountDeletionProposal::STATUS_APPROVED && ! $p->objected_at,
            'objected' => (bool) $p->objected_at,
        ];
    }

    private function tell(User $account, string $ar, string $en): void
    {
        try {
            $this->notifications->dispatch('moderation_account_frozen', (int) $account->id, [
                'body_ar' => $ar,
                'body_en' => $en,
                'action_type' => 'open_moderation_case',
                'source_type' => 'moderation_account_frozen',
                'source_id' => (int) $account->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
