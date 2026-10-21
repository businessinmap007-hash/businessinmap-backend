<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\TrainingPlan;
use App\Services\Training\PlanShowcaseService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;

/**
 * «نتائج موثَّقة» for training / nutrition plans: the client's one approval of the request covers the whole programme.
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
        $data = $request->validate(['public_title' => ['required', 'string', 'max:191'], 'note' => ['nullable', 'string', 'max:500'], 'consent_mode' => ['nullable', 'in:once,each']]);
        $row = $this->owned($request, $plan);

        $this->showcases->request($row, $data['public_title'], $data['note'] ?? null, $data['consent_mode'] ?? 'once');

        return response()->json(['success' => true, 'message' => __('أُرسل طلب النشر إلى العميل.'), 'data' => ['showcase' => $this->showcases->block($row)]], 201);
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
        $data = $request->validate(['consent_mode' => ['nullable', 'in:each']]);
        $row = $this->mine($request, $plan);
        $this->showcases->decide($row, $request->user(), true, null, $data['consent_mode'] ?? null);

        return $this->clientReply($row, __('وافقت على عرض النتائج.'));
    }

    public function decline(Request $request, int $plan)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $row = $this->mine($request, $plan);
        $this->showcases->decide($row, $request->user(), false, $data['note'] ?? null);

        return $this->clientReply($row, __('رفضت عرض النتائج.'));
    }

    /** POST training-plans/{plan}/showcase/months/{month}/approve|decline — «انشر نتائج هذا الشهر؟» in the month-by-month mode. */
    public function approveMonth(Request $request, int $plan, string $month)
    {
        $row = $this->mine($request, $plan);
        $this->showcases->decideMonth($row, $request->user(), $month, true);

        return $this->clientReply($row, __('وافقت على نشر نتائج الشهر.'));
    }

    public function declineMonth(Request $request, int $plan, string $month)
    {
        $row = $this->mine($request, $plan);
        $this->showcases->decideMonth($row, $request->user(), $month, false);

        return $this->clientReply($row, __('لن تُنشر نتائج الشهر.'));
    }

    public function clientWithdraw(Request $request, int $plan)
    {
        $row = $this->mine($request, $plan);
        $this->showcases->withdraw($row, $request->user());

        return $this->clientReply($row, __('أُوقف عرض النتائج.'));
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
