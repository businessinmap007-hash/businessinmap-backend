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

    /** POST business/projects/{project}/agreement {due_on, grace_days} — offer the plan to the customer. */
    public function propose(Request $request, int $project)
    {
        $data = $request->validate(['due_on' => ['required', 'date'], 'grace_days' => ['required', 'integer', 'between:0,365']]);
        $row = $this->owned($request, $project);

        $this->agreements->propose($row, $request->user(), Carbon::parse($data['due_on']), (int) $data['grace_days']);

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
