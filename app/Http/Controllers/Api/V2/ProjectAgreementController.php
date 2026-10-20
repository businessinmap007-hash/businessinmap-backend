<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectChangeRequest;
use App\Models\ProjectTask;
use App\Services\OperationChatService;
use App\Services\Projects\ProjectAgreementService;
use App\Services\Projects\ProjectService;
use App\Services\Projects\ProjectStageStartService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The agreement on a project's plan and everything that needs the customer's recorded word after it: more time, a change of
 * scope, the start of each main stage. Two doors — the business's under /business/projects/{project}, and the customer's
 * under /operations/{type}/{id}/project (he is a party of the order / booking the project is linked to).
 */
class ProjectAgreementController extends Controller
{
    public function __construct(
        private readonly ProjectAgreementService $agreements,
        private readonly ProjectStageStartService $starts,
        private readonly ProjectService $projects,
        private readonly OperationChatService $operations
    ) {
    }

    /* ------------------------------------------------------------------ the business */

    /** GET business/projects/{project}/agreement — the version in force, every version, and the requests with their answers. */
    public function show(Request $request, int $project)
    {
        return response()->json(['success' => true, 'data' => $this->agreements->block($this->owned($request, $project))]);
    }

    /**
     * POST business/projects/{project}/agreement {starts_on, due_on, grace_days, required_guarantee?} — offer the plan to the
     * customer: execution starts on a future date (the time until then is the negotiation), is delivered by `due_on` with a
     * grace after it, and the customer may be required to hold a guarantee of at least a level before work begins.
     */
    public function propose(Request $request, int $project)
    {
        $data = $request->validate([
            'starts_on' => ['required', 'date'],
            'due_on' => ['required', 'date'],
            'grace_days' => ['required', 'integer', 'between:0,365'],
            'required_guarantee' => ['nullable', 'string', 'max:20'],
        ]);
        $row = $this->owned($request, $project);

        $this->agreements->propose($row, $request->user(), Carbon::parse($data['starts_on']), Carbon::parse($data['due_on']), (int) $data['grace_days'], $data['required_guarantee'] ?? null);

        return response()->json(['success' => true, 'message' => __('عُرضت الخطة على العميل.'), 'data' => $this->agreements->block($row)], 201);
    }

    /** POST business/projects/{project}/extension-requests {extra_days, reason} — more time, if the customer agrees. */
    public function requestExtension(Request $request, int $project)
    {
        $data = $request->validate(['extra_days' => ['required', 'integer', 'between:1,365'], 'reason' => ['required', 'string', 'max:500']]);
        $row = $this->owned($request, $project);

        $this->agreements->requestExtension($row, $request->user(), (int) $data['extra_days'], $data['reason']);

        return response()->json(['success' => true, 'message' => __('أُرسل طلب التمديد إلى العميل.'), 'data' => $this->agreements->block($row)], 201);
    }

    /**
     * POST business/projects/{project}/shift {scope: plan|stage, task_id?, days? | new_start?, reason?} — postpone the plan, or a
     * stage and what follows it, by a number of days (7 = a week, 30 = a month) or to a later start date. Before the agreement it
     * happens at once (200); after it, it is a request the customer approves (201).
     */
    public function shift(Request $request, int $project)
    {
        $data = $request->validate([
            'scope' => ['required', 'in:plan,stage'],
            'task_id' => ['nullable', 'integer', 'required_if:scope,stage'],
            'days' => ['nullable', 'integer', 'between:1,365'],
            'new_start' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $row = $this->owned($request, $project);
        $stage = ! empty($data['task_id']) ? ProjectTask::query()->where('project_id', $row->id)->findOrFail((int) $data['task_id']) : null;

        $changeRequest = $this->agreements->requestShift(
            $row, $request->user(), $data['scope'], $stage, $data['days'] ?? null, ! empty($data['new_start']) ? Carbon::parse($data['new_start']) : null, (string) ($data['reason'] ?? ''),
        );

        return response()->json(
            ['success' => true, 'message' => $changeRequest ? __('أُرسل طلب الترحيل إلى العميل.') : __('تم ترحيل المواعيد.'), 'data' => $this->agreements->block($row->fresh())],
            $changeRequest ? 201 : 200,
        );
    }

    /** POST business/projects/{project}/change-requests/{id}/approve|reject — the business answers a postponement the customer asked for. */
    public function businessApprove(Request $request, int $project, int $changeRequest)
    {
        return $this->businessDecide($request, $project, $changeRequest, true);
    }

    public function businessReject(Request $request, int $project, int $changeRequest)
    {
        return $this->businessDecide($request, $project, $changeRequest, false);
    }

    private function businessDecide(Request $request, int $project, int $requestId, bool $approve)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $row = $this->owned($request, $project);
        $change = ProjectChangeRequest::query()->where('project_id', $row->id)->findOrFail($requestId);

        $this->agreements->decide($change, $request->user(), $approve, $data['note'] ?? null);

        return response()->json(['success' => true, 'message' => $approve ? __('وافقت على الطلب.') : __('رفضت الطلب.'), 'data' => $this->agreements->block($row->fresh())]);
    }

    /** POST business/projects/{project}/tasks/{task}/request-start — ask to start a main stage. */
    public function requestStart(Request $request, int $project, int $task)
    {
        $row = $this->owned($request, $project);
        $stage = ProjectTask::query()->where('project_id', $row->id)->findOrFail($task);

        $this->starts->request($stage, $request->user());

        return response()->json(['success' => true, 'message' => __('أُرسل طلب بدء المرحلة إلى العميل.'), 'data' => ['start_status' => 'requested']], 201);
    }

    /* ------------------------------------------------------------------ the customer */

    public function agree(Request $request, string $type, int $id)
    {
        $project = $this->forCustomer($request, $type, $id);
        $this->agreements->agree($project, $request->user());

        return $this->customerReply($project, __('تم الاتفاق على الخطة.'));
    }

    public function decline(Request $request, string $type, int $id)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);
        $project = $this->forCustomer($request, $type, $id);
        $this->agreements->reject($project, $request->user(), $data['note']);

        return $this->customerReply($project, __('أُرسل رفضك للنشاط.'));
    }

    /** POST operations/{type}/{id}/project/shift-requests {scope, task_id?, days? | new_start?, reason} — the customer asks to postpone; the business answers. */
    public function customerShift(Request $request, string $type, int $id)
    {
        $data = $request->validate([
            'scope' => ['required', 'in:plan,stage'],
            'task_id' => ['nullable', 'integer', 'required_if:scope,stage'],
            'days' => ['nullable', 'integer', 'between:1,365'],
            'new_start' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $project = $this->forCustomer($request, $type, $id);
        $stage = ! empty($data['task_id']) ? ProjectTask::query()->where('project_id', $project->id)->findOrFail((int) $data['task_id']) : null;

        $this->agreements->requestShift(
            $project, $request->user(), $data['scope'], $stage, $data['days'] ?? null, ! empty($data['new_start']) ? Carbon::parse($data['new_start']) : null, $data['reason'],
        );

        return $this->customerReply($project, __('أُرسل طلب الترحيل إلى النشاط.'));
    }

    public function approveRequest(Request $request, string $type, int $id, int $changeRequest)
    {
        return $this->decideRequest($request, $type, $id, $changeRequest, true);
    }

    public function rejectRequest(Request $request, string $type, int $id, int $changeRequest)
    {
        return $this->decideRequest($request, $type, $id, $changeRequest, false);
    }

    public function approveStart(Request $request, string $type, int $id, int $task)
    {
        return $this->decideStart($request, $type, $id, $task, true);
    }

    public function declineStart(Request $request, string $type, int $id, int $task)
    {
        return $this->decideStart($request, $type, $id, $task, false);
    }

    private function decideRequest(Request $request, string $type, int $id, int $requestId, bool $approve)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $project = $this->forCustomer($request, $type, $id);
        $change = ProjectChangeRequest::query()->where('project_id', $project->id)->findOrFail($requestId);

        $this->agreements->decide($change, $request->user(), $approve, $data['note'] ?? null);

        return $this->customerReply($project, $approve ? __('وافقت على الطلب.') : __('رفضت الطلب.'));
    }

    private function decideStart(Request $request, string $type, int $id, int $taskId, bool $approve)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $project = $this->forCustomer($request, $type, $id);
        $task = ProjectTask::query()->where('project_id', $project->id)->findOrFail($taskId);

        $this->starts->decide($task, $request->user(), $approve, $data['note'] ?? null);

        return $this->customerReply($project, $approve ? __('وافقت على بدء المرحلة.') : __('رفضت بدء المرحلة.'));
    }

    /* ------------------------------------------------------------------ helpers */

    private function owned(Request $request, int $projectId): Project
    {
        return Project::query()->where('id', $projectId)->where('business_id', BusinessContext::id($request))->firstOrFail();
    }

    /** The project linked to an order / booking the caller is a party of — a stranger gets 404. */
    private function forCustomer(Request $request, string $type, int $id): Project
    {
        $operation = $this->operations->resolve($type, $id);
        $this->operations->assertParty($operation, (int) $request->user()->id);

        return Project::query()
            ->where('operation_type', $operation->getMorphClass())->where('operation_id', $operation->getKey())
            ->firstOrFail();
    }

    private function customerReply(Project $project, string $message)
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $this->projects->customerView($project->fresh())]);
    }
}
