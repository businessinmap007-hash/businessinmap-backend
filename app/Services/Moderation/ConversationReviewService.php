<?php

namespace App\Services\Moderation;

use App\Models\AccountCase;
use App\Models\ContentReport;
use App\Models\Dispute;
use App\Models\Thread;
use App\Models\ThreadMessage;
use App\Models\ThreadParticipant;
use App\Models\User;
use App\Services\Chat\ThreadAccessGateService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;

/**
 * Reports on a CONVERSATION (owner, 2026-10-30). What a moderator may read of someone's chat is the owner's standing privacy rule
 * ({@see ThreadAccessGateService}) — nothing without the parties' say-so — so a reported conversation offers the moderator three
 * ways to look, and the moderator picks (or combines) them:
 *
 *  1. the messages the REPORTER chose to show (his own choice; only those);
 *  2. the CONSENT of both parties (the moderator asks; the parties answer in the chat, as they do for an admin);
 *  3. a QUORUM of moderators who each vouch for the need (the same quorum an admin quorum is).
 *
 * Options 2 and 3 open the whole thread, read-only. A dispute room and a moderator's own chat with a reported account are never
 * reportable or readable here.
 */
final class ConversationReviewService
{
    public const MAX_SELECTED = 30;

    public function __construct(private readonly ThreadAccessGateService $gate) {}

    /** The reporter must be one of the two (or more) people talking; a dispute room or a moderation chat is not reportable. */
    public function assertReportable(Thread $thread, User $reporter): void
    {
        if (in_array($thread->subject_type, [(new Dispute())->getMorphClass(), (new AccountCase())->getMorphClass()], true)) {
            throw ValidationException::withMessages(['target' => __('هذا المحتوى غير متاح.')]);
        }

        $isParty = $thread->participants()->where('user_id', $reporter->id)->where('role', '!=', ThreadParticipant::ROLE_ARBITRATOR)->exists();

        if (! $isParty) {
            // 404-style: a stranger must not learn the conversation exists
            throw ValidationException::withMessages(['target' => __('هذا المحتوى غير متاح.')]);
        }
    }

    /**
     * Only real messages of THIS thread, at most {@see self::MAX_SELECTED}.
     *
     * @param  array<int,mixed>|null  $ids
     * @return list<int>
     */
    public function cleanMessageIds(Thread $thread, ?array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return ThreadMessage::query()->where('thread_id', $thread->id)->where('kind', ThreadMessage::KIND_MESSAGE)
            ->whereIn('id', array_slice(array_values(array_unique(array_map('intval', $ids))), 0, self::MAX_SELECTED))
            ->orderBy('id')->pluck('id')->map(fn ($v) => (int) $v)->all();
    }

    /** @return list<array{id:int,name:string}> */
    public function parties(Thread $thread): array
    {
        return $thread->participants()->where('role', '!=', ThreadParticipant::ROLE_ARBITRATOR)->with('user:id,name')->get()
            ->map(fn (ThreadParticipant $p) => ['id' => (int) $p->user_id, 'name' => (string) ($p->user?->displayName() ?? ('#' . $p->user_id))])->values()->all();
    }

    /**
     * What the moderator's card shows: who is talking, the messages the reporters chose, and where each way of looking stands.
     *
     * @return array<string,mixed>
     */
    public function summary(Thread $thread, ?User $moderator): array
    {
        $chosen = ContentReport::query()->where('reportable_type', 'conversation')->where('reportable_id', $thread->id)->whereNotNull('message_ids')
            ->pluck('message_ids')->flatten()->map(fn ($v) => (int) $v)->unique()->values()->all();

        return [
            'thread_id' => (int) $thread->id,
            'kind' => $this->kindOf($thread),
            'parties' => $this->parties($thread),
            // option 1: what the reporters chose to show — readable at once
            'selected' => $this->present(ThreadMessage::query()->whereIn('id', $chosen)->where('thread_id', $thread->id)->with('sender:id,name')->orderBy('id')->get()),
            'access' => [
                'accessible' => $this->gate->isAccessible($thread),
                // option 2
                'consent' => array_map(fn ($d) => $d, $this->gate->partyDecisions($thread)),
                'all_consented' => $this->gate->allPartiesConsented($thread),
                'any_declined' => $this->gate->anyPartyDeclined($thread),
                // option 3
                'approvals' => $this->gate->adminApprovalCount($thread),
                'quorum' => $this->gate->quorum(),
                'i_vouched' => $moderator ? $this->gate->hasAdminApproved($thread, (int) $moderator->id) : false,
            ],
        ];
    }

    /** Option 2: the parties are asked (they answer in the chat, with the banner every chat already shows). */
    public function requestConsent(Thread $thread): void
    {
        $this->gate->notifyPartiesRequestConsent($thread);
    }

    /** Option 3: this moderator's own vote toward the quorum. */
    public function vouch(Thread $thread, User $moderator): void
    {
        $this->gate->recordAdminApproval($thread, $moderator);
    }

    /**
     * The whole thread, read-only — only when it is open by consent or by quorum. Otherwise 403 with where things stand.
     *
     * @return list<array<string,mixed>>
     */
    public function messages(Thread $thread, ?User $moderator): array
    {
        if (! $this->gate->isAccessible($thread)) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'code' => 'conversation_locked',
                'message' => __('المحادثة مغلقة: تُفتح بموافقة الطرفين أو بنصاب من المشرفين.'),
                'data' => ['access' => $this->summary($thread, $moderator)['access']],
            ], 403));
        }

        return $this->present($thread->messages()->with('sender:id,name')->orderBy('id')->get());
    }

    /** @param  iterable<ThreadMessage>  $messages  @return list<array<string,mixed>> */
    private function present(iterable $messages): array
    {
        $out = [];

        foreach ($messages as $m) {
            $out[] = [
                'id' => (int) $m->id,
                'kind' => $m->kind,
                'body' => $m->body,
                'sender' => $m->sender_id === null ? null : ['id' => (int) $m->sender_id, 'name' => (string) ($m->sender?->name ?? '')],
                'created_at' => optional($m->created_at)->toIso8601String(),
            ];
        }

        return $out;
    }

    private function kindOf(Thread $thread): string
    {
        if ($thread->created_by !== null) {
            return 'group';
        }

        return $thread->subject_type !== null ? 'operation' : 'direct';
    }
}
