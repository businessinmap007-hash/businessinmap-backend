<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\BodyCompositionReport;
use App\Models\ShowcaseConsent;
use App\Models\TrainingPlan;
use App\Services\Training\PlanShowcaseService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;

/**
 * «نتائج موثَّقة» for training / nutrition plans, and the general consent that spares the person being asked every time.
 * The trainer's door sits under `business/training-plans/{plan}`, the client's under `training-plans/{plan}`; a third party
 * gets 404 on both (a 403 would confirm the pairing).
 */
class PlanShowcaseController extends Controller
{
    public function __construct(private readonly PlanShowcaseService $showcases)
    {
    }

    /* ------------------------------------------------------------ the trainer */

    public function show(Request $request, int $plan)
    {
        return response()->json(['success' => true, 'data' => ['showcase' => $this->showcases->block($this->owned($request, $plan))]]);
    }

    /** POST business/training-plans/{plan}/showcase {public_title, note?} */
    public function request(Request $request, int $plan)
    {
        $data = $request->validate(['public_title' => ['required', 'string', 'max:191'], 'note' => ['nullable', 'string', 'max:500']]);
        $row = $this->owned($request, $plan);

        $showcase = $this->showcases->request($row, $data['public_title'], $data['note'] ?? null);

        return response()->json([
            'success' => true,
            'message' => $showcase->isLive() ? __('نُشرت النتائج بموافقة العميل العامة.') : __('أُرسل طلب النشر إلى العميل.'),
            'data' => ['showcase' => $this->showcases->block($row)],
        ], 201);
    }

    public function trainerWithdraw(Request $request, int $plan)
    {
        $row = $this->owned($request, $plan);
        $this->showcases->withdraw($row, $request->user());

        return response()->json(['success' => true, 'message' => __('أُوقف عرض النتائج.'), 'data' => ['showcase' => $this->showcases->block($row)]]);
    }

    /* ------------------------------------------------------------ the client */

    public function approve(Request $request, int $plan)
    {
        $row = $this->mine($request, $plan);
        $this->showcases->decide($row, $request->user(), true);

        return $this->clientReply($row, __('وافقت على عرض النتائج.'));
    }

    public function decline(Request $request, int $plan)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $row = $this->mine($request, $plan);
        $this->showcases->decide($row, $request->user(), false, $data['note'] ?? null);

        return $this->clientReply($row, __('رفضت عرض النتائج.'));
    }

    public function clientWithdraw(Request $request, int $plan)
    {
        $row = $this->mine($request, $plan);
        $this->showcases->withdraw($row, $request->user());

        return $this->clientReply($row, __('أُوقف عرض النتائج.'));
    }

    /** POST training-plans/{plan}/body-reports/{report}/confirm — «أؤكد نتيجة هذا الشهر». */
    public function confirmReport(Request $request, int $plan, int $report)
    {
        $row = $this->mine($request, $plan);
        $reportRow = BodyCompositionReport::query()->where('training_plan_id', $row->id)->findOrFail($report);

        $done = $this->showcases->confirmReport($row, $reportRow, $request->user());

        return response()->json([
            'success' => true,
            'message' => __('تم تأكيد نتيجة الشهر.'),
            'data' => ['report' => ['id' => (int) $done->id, 'confirmed' => true, 'confirmed_on' => optional($done->client_confirmed_at)->toDateString()]],
        ]);
    }

    /* ------------------------------------------------- the general consent */

    /** GET showcase-consent — what I allow once and for all. */
    public function consent(Request $request)
    {
        return response()->json(['success' => true, 'data' => ['consent' => ShowcaseConsent::forUser((int) $request->user()->id)]]);
    }

    /** PUT showcase-consent {projects?, project_photos?, training?, nutrition?} */
    public function updateConsent(Request $request)
    {
        $data = $request->validate([
            'projects' => ['sometimes', 'boolean'],
            'project_photos' => ['sometimes', 'boolean'],
            'training' => ['sometimes', 'boolean'],
            'nutrition' => ['sometimes', 'boolean'],
        ]);

        // photos of a project are only allowed on top of showing projects at all
        $current = ShowcaseConsent::forUser((int) $request->user()->id);
        $next = $data + $current;
        $next['project_photos'] = $next['projects'] && $next['project_photos'];

        ShowcaseConsent::query()->updateOrCreate(['user_id' => (int) $request->user()->id], $next);

        return response()->json(['success' => true, 'message' => __('حُفظت موافقتك.'), 'data' => ['consent' => ShowcaseConsent::forUser((int) $request->user()->id)]]);
    }

    private function owned(Request $request, int $planId): TrainingPlan
    {
        return TrainingPlan::query()->where('id', $planId)->where('trainer_id', BusinessContext::id($request))->firstOrFail();
    }

    private function mine(Request $request, int $planId): TrainingPlan
    {
        return TrainingPlan::query()->where('id', $planId)->where('client_id', (int) $request->user()->id)->visibleToClient()->firstOrFail();
    }

    private function clientReply(TrainingPlan $plan, string $message)
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => ['showcase' => $this->showcases->block($plan->fresh())]]);
    }
}
