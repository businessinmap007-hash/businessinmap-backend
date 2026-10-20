<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\Moderation\ContentReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * «الإبلاغ»: anyone reports a post or an owner listing; a moderator (an account holding the content ability) works the
 * queue. See {@see ContentReportService}.
 */
final class ContentReportController extends Controller
{
    public function __construct(private readonly ContentReportService $reports) {}

    /** GET reports/reasons — what the report sheet offers. */
    public function reasons()
    {
        return response()->json(['success' => true, 'data' => ['reasons' => $this->reports->reasons($this->english())]]);
    }

    /** POST reports {type, id, reason, details?} */
    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(ContentReportService::TYPES))],
            'id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:30'],
            'details' => ['nullable', 'string', 'max:500'],
            // a conversation: the messages the reporter chooses to show the moderators (at most 30)
            'message_ids' => ['nullable', 'array', 'max:30'],
            'message_ids.*' => ['integer'],
        ]);

        $this->reports->report($request->user(), $data['type'], (int) $data['id'], $data['reason'], $data['details'] ?? null, $data['message_ids'] ?? null);

        return response()->json(['success' => true, 'message' => __('وصلنا بلاغك، شكرًا.')]);
    }

    /** GET moderation/violations — the rules a listing is removed for (the remove dialog's choices). */
    public function violations(Request $request)
    {
        $this->authorizeModerator($request);

        return response()->json(['success' => true, 'data' => ['violations' => \App\Services\OwnerListings\OwnerListingSeriousness::violations($this->english())]]);
    }

    /** GET moderation/me — does this account get the moderation tab? */
    public function me(Request $request)
    {
        return response()->json(['success' => true, 'data' => ['is_moderator' => $this->reports->isModerator($request->user())]]);
    }

    /** GET moderation/reports?scope=open|handled&type=&per_page= */
    public function queue(Request $request)
    {
        $this->authorizeModerator($request);

        $data = $request->validate([
            'scope' => ['nullable', 'in:open,handled'],
            'type' => ['nullable', Rule::in(array_keys(ContentReportService::TYPES))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $page = $this->reports->queue($data['scope'] ?? 'open', $data['type'] ?? null, (int) ($data['per_page'] ?? 20), $this->english(), $request->user());

        return response()->json([
            'success' => true,
            'data' => ['items' => $page->items()],
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    /** POST moderation/reports/{type}/{id}/claim — «أفتح البلاغ وأتعامل معه»: recorded under my name; nobody else may act on it meanwhile. */
    public function claim(Request $request, string $type, int $id)
    {
        $this->authorizeModerator($request);
        $this->assertType($type);
        $this->reports->claim($type, $id, $request->user());

        return response()->json(['success' => true, 'message' => __('أنت من يتعامل مع هذا البلاغ الآن.')]);
    }

    /** POST moderation/reports/{type}/{id}/release — let it go without closing it. */
    public function release(Request $request, string $type, int $id)
    {
        $this->authorizeModerator($request);
        $this->assertType($type);
        $this->reports->release($type, $id, $request->user());

        return response()->json(['success' => true, 'message' => __('تم تحرير البلاغ.')]);
    }

    /** POST moderation/reports/account/{id}/chat — open the chat with the reported account (needs the claim). Returns the case: chat at operation-chats/moderation/{case}. */
    public function accountChat(Request $request, int $id)
    {
        $this->authorizeModerator($request);
        $case = $this->reports->chatWithAccount($id, $request->user());

        return response()->json(['success' => true, 'data' => ['case_id' => (int) $case->id]]);
    }

    /** POST moderation/reports/account/{id}/freeze — freeze it for `bim.moderation.freeze_days` (3); the account is never removed. */
    public function accountFreeze(Request $request, int $id)
    {
        $this->authorizeModerator($request);
        $reason = $request->validate(['reason' => 'nullable|string|max:255'])['reason'] ?? null;
        $r = $this->reports->freezeAccount($id, $request->user(), $reason);

        return response()->json(['success' => true, 'message' => $r['pending_deletion'] ? __('جُمِّد الحساب وعُرض حذفه على لجنة المشرفين.') : __('جُمِّد الحساب.'), 'data' => [
            'case_id' => (int) $r['case']->id,
            'frozen_until' => optional($r['case']->frozen_until)->toIso8601String(),
            'freeze_count' => $r['count'],
            'deleted' => $r['deleted'],
            'pending_deletion' => $r['pending_deletion'],
            'confiscated' => $r['confiscated'],
        ]]);
    }

    /** POST moderation/reports/account/{id}/unfreeze — lift the freeze early and close the case. */
    public function accountUnfreeze(Request $request, int $id)
    {
        $this->authorizeModerator($request);
        $this->reports->unfreezeAccount($id, $request->user());

        return response()->json(['success' => true, 'message' => __('رُفع التجميد.')]);
    }

    /** GET moderation/my-case — for a REPORTED account: its open case (the chat with the moderators) and whether it is frozen. */
    public function myCase(Request $request, \App\Services\Moderation\AccountModerationService $accounts, \App\Services\Moderation\AccountDeletionPanelService $panel)
    {
        $case = $accounts->caseFor($request->user());

        return response()->json(['success' => true, 'data' => ['case' => $case ? [
            'id' => (int) $case->id,
            'status' => $case->status,
            'frozen_until' => optional($case->frozen_until)->toIso8601String(),
        ] : null, 'deletion' => $panel->stateFor($request->user())]]);
    }

    /** POST moderation/my-case/objection — the frozen account objects in writing to an approved deletion; the panel votes again. */
    public function objectToDeletion(Request $request, \App\Services\Moderation\AccountDeletionPanelService $panel)
    {
        $data = $request->validate(['objection' => 'required|string|max:2000']);
        $panel->object($request->user(), $data['objection']);

        return response()->json(['success' => true, 'message' => __('وصل اعتراضك وسيُعرض على لجنة المشرفين من جديد.'), 'data' => ['deletion' => $panel->stateFor($request->user())]]);
    }

    /** GET moderation/deletion-proposals — deletions waiting for the moderators' panel to vote (no names of other moderators). */
    public function deletionProposals(Request $request, \App\Services\Moderation\AccountDeletionPanelService $panel)
    {
        $this->authorizeModerator($request);

        return response()->json(['success' => true, 'data' => ['items' => $panel->queue($request->user())]]);
    }

    /** POST moderation/deletion-proposals/{id}/vote {vote: approve|reject, note?} */
    public function voteOnDeletion(Request $request, \App\Services\Moderation\AccountDeletionPanelService $panel, int $id)
    {
        $this->authorizeModerator($request);
        $data = $request->validate(['vote' => 'required|string|in:approve,reject', 'note' => 'nullable|string|max:255']);
        $p = $panel->vote($id, $request->user(), $data['vote'], $data['note'] ?? null);

        return response()->json(['success' => true, 'message' => __('سُجِّل صوتك.'), 'data' => ['status' => $p->status]]);
    }

    /** POST moderation/reports/conversation/{id}/request-consent — way 2: ask both parties to allow reading. */
    public function conversationRequestConsent(Request $request, int $id)
    {
        $this->authorizeModerator($request);
        $this->reports->conversationRequestConsent($id, $request->user());

        return response()->json(['success' => true, 'message' => __('أُرسل طلب الموافقة للطرفين.')]);
    }

    /** POST moderation/reports/conversation/{id}/vouch — way 3: my vote toward the moderators' quorum. */
    public function conversationVouch(Request $request, int $id)
    {
        $this->authorizeModerator($request);
        $this->reports->conversationVouch($id, $request->user());

        return response()->json(['success' => true, 'message' => __('سُجِّل تأييدك للاطلاع.')]);
    }

    /** GET moderation/reports/conversation/{id}/messages — the conversation, read-only, when it is open (403 conversation_locked otherwise). */
    public function conversationMessages(Request $request, int $id)
    {
        $this->authorizeModerator($request);

        return response()->json(['success' => true, 'data' => ['messages' => $this->reports->conversationMessages($id, $request->user())]]);
    }

    /** POST moderation/reports/conversation/{id}/party/{user}/chat — open a moderator chat with one of its people. */
    public function conversationPartyChat(Request $request, int $id, int $user)
    {
        $this->authorizeModerator($request);
        $case = $this->reports->conversationPartyChat($id, $user, $request->user());

        return response()->json(['success' => true, 'data' => ['case_id' => (int) $case->id]]);
    }

    /** POST moderation/reports/conversation/{id}/party/{user}/freeze — freeze one of its people (counted; the fifth deletes). */
    public function conversationPartyFreeze(Request $request, int $id, int $user)
    {
        $this->authorizeModerator($request);
        $r = $this->reports->conversationPartyFreeze($id, $user, $request->user());

        return response()->json(['success' => true, 'message' => $r['pending_deletion'] ? __('جُمِّد الحساب وعُرض حذفه على لجنة المشرفين.') : __('جُمِّد الحساب.'), 'data' => [
            'case_id' => (int) $r['case']->id, 'freeze_count' => $r['count'], 'deleted' => $r['deleted'], 'pending_deletion' => $r['pending_deletion'], 'confiscated' => $r['confiscated'],
        ]]);
    }

    /** POST moderation/reports/{type}/{id}/dismiss */
    public function dismiss(Request $request, string $type, int $id)
    {
        $this->authorizeModerator($request);
        $this->assertType($type);
        $this->reports->dismiss($type, $id, $request->user());

        return response()->json(['success' => true, 'message' => __('تم إغلاق البلاغ.')]);
    }

    /** POST moderation/reports/{type}/{id}/remove */
    public function remove(Request $request, string $type, int $id)
    {
        $this->authorizeModerator($request);
        $this->assertType($type);
        $data = $request->validate([
            // for a listing: the rule it broke; the stake behind it is confiscated unless confiscate=false
            'violation' => ['nullable', 'string', 'max:30'],
            'confiscate' => ['nullable', 'boolean'],
        ]);
        $taken = $this->reports->remove($type, $id, $request->user(), $data['violation'] ?? null, (bool) ($data['confiscate'] ?? true));

        return response()->json(['success' => true, 'message' => __('تمت إزالة المحتوى.'), 'data' => ['confiscated' => $taken]]);
    }

    /** POST moderation/reports/{type}/{id}/restore */
    public function restore(Request $request, string $type, int $id)
    {
        $this->authorizeModerator($request);
        $this->assertType($type);
        $this->reports->restore($type, $id, $request->user());

        return response()->json(['success' => true, 'message' => __('تمت استعادة المحتوى.')]);
    }

    private function authorizeModerator(Request $request): void
    {
        // 404, not 403: an ordinary account must not learn that the screen exists
        abort_unless($this->reports->isModerator($request->user()), 404);
    }

    private function assertType(string $type): void
    {
        abort_unless(isset(ContentReportService::TYPES[$type]), 404);
    }

    private function english(): bool
    {
        return app()->getLocale() === 'en';
    }
}
