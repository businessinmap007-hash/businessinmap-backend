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
        ]);

        $this->reports->report($request->user(), $data['type'], (int) $data['id'], $data['reason'], $data['details'] ?? null);

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
