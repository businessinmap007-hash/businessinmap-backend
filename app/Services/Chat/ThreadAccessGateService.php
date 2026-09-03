<?php

namespace App\Services\Chat;

use App\Models\ChatAccessSetting;
use App\Models\Thread;
use App\Models\ThreadAccessApproval;
use App\Models\ThreadAccessConsent;
use App\Models\ThreadParticipant;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Support\Carbon;

/**
 * The single source of truth for "may an admin read this thread's decrypted
 * messages." A thread unlocks one of two ways — every real participant has
 * consented, or enough admins have independently vouched for the need — and
 * every caller (the chat moderation screen, the dispute ruling screen) asks
 * this service rather than re-deriving the rule.
 *
 * "Real participant" excludes the arbitrator role on purpose: an arbitrator
 * already reads their own dispute room by design (that thread is never
 * gated at all — see the caller), and this service is really about the two
 * (or more, in a group) people who were actually talking.
 */
class ThreadAccessGateService
{
    public function __construct(private readonly NotificationDispatcherService $notifications)
    {
    }

    public function isAccessible(Thread $thread): bool
    {
        return $this->allPartiesConsented($thread) || $this->adminApprovalCount($thread) >= $this->quorum();
    }

    /** @return array<int, string|null> participant user_id => 'approved'|'declined'|null */
    public function partyDecisions(Thread $thread): array
    {
        $decisions = ThreadAccessConsent::query()
            ->where('thread_id', $thread->id)
            ->pluck('decision', 'user_id');

        return $this->realParticipants($thread)
            ->mapWithKeys(fn (ThreadParticipant $p) => [(int) $p->user_id => $decisions[$p->user_id] ?? null])
            ->all();
    }

    public function allPartiesConsented(Thread $thread): bool
    {
        $decisions = $this->partyDecisions($thread);

        return count($decisions) > 0
            && ! in_array(null, $decisions, true)
            && ! in_array(ThreadAccessConsent::DECLINED, $decisions, true);
    }

    public function anyPartyDeclined(Thread $thread): bool
    {
        return in_array(ThreadAccessConsent::DECLINED, $this->partyDecisions($thread), true);
    }

    public function adminApprovalCount(Thread $thread): int
    {
        return ThreadAccessApproval::query()->where('thread_id', $thread->id)->count();
    }

    public function hasAdminApproved(Thread $thread, int $adminId): bool
    {
        return ThreadAccessApproval::query()
            ->where('thread_id', $thread->id)
            ->where('admin_id', $adminId)
            ->exists();
    }

    public function quorum(): int
    {
        return (int) (ChatAccessSetting::query()->first()?->admin_quorum ?? 3);
    }

    public function recordPartyConsent(Thread $thread, User $user, string $decision): void
    {
        ThreadAccessConsent::query()->updateOrCreate(
            ['thread_id' => $thread->id, 'user_id' => $user->id],
            ['decision' => $decision, 'responded_at' => Carbon::now()],
        );
    }

    public function recordAdminApproval(Thread $thread, User $admin): void
    {
        ThreadAccessApproval::query()->firstOrCreate(
            ['thread_id' => $thread->id, 'admin_id' => $admin->id],
            ['approved_at' => Carbon::now()],
        );
    }

    /** Nudge every real participant who hasn't yet decided. */
    public function notifyPartiesRequestConsent(Thread $thread): void
    {
        $alreadyDecided = ThreadAccessConsent::query()
            ->where('thread_id', $thread->id)
            ->pluck('user_id')
            ->all();

        foreach ($this->realParticipants($thread) as $participant) {
            if (in_array((int) $participant->user_id, $alreadyDecided, true)) {
                continue;
            }

            try {
                $this->notifications->dispatch('chat_access_requested', (int) $participant->user_id, [
                    'type' => \App\Models\AppNotification::TYPE_DISPUTE,
                    'title_ar' => 'طلبت الإدارة الاطلاع على محادثتك',
                    'title_en' => 'Admin requested access to your chat',
                    'body_ar' => 'راجع طلب الاطلاع من داخل المحادثة ووافق أو ارفض.',
                    'body_en' => 'Review the access request inside the chat and approve or decline.',
                    'source_type' => Thread::class,
                    'source_id' => (int) $thread->id,
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /** @return \Illuminate\Support\Collection<int, ThreadParticipant> */
    private function realParticipants(Thread $thread): \Illuminate\Support\Collection
    {
        return $thread->participants()
            ->where('role', '!=', ThreadParticipant::ROLE_ARBITRATOR)
            ->get();
    }
}
