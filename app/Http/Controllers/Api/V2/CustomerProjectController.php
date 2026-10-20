<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectFollower;
use App\Services\OperationChatService;
use App\Services\Projects\ProjectService;
use Illuminate\Http\Request;

/**
 * The customer following the operation they contracted: a read-only progress
 * view of the project the business linked to their order/booking — which build
 * stage it reached and the camera evidence for each. Either party (the buyer or
 * the business) may read it; a stranger gets a 404, never proof it exists.
 */
class CustomerProjectController extends Controller
{
    public function __construct(
        private readonly OperationChatService $operations,
        private readonly ProjectService $projects,
    ) {
    }

    /** GET /api/v2/operations/{type}/{id}/project — progress for my operation. */
    public function show(Request $request, string $type, int $id)
    {
        $operation = $this->operations->resolve($type, $id);
        $this->operations->assertParty($operation, (int) $request->user()->id);

        $project = Project::query()
            ->where('operation_type', $operation->getMorphClass())
            ->where('operation_id', $operation->getKey())
            ->first();

        // who is asking: the business of the operation, or its customer (the chat card and the screen differ)
        $viewer = (int) $operation->business_id === (int) $request->user()->id ? 'business' : 'customer';

        if (! $project) {
            return response()->json([
                'success' => true,
                'message' => __('لا توجد خطة تقدّم لهذه العملية بعد.'),
                'data' => ['project' => null, 'viewer' => $viewer],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $this->projects->customerView($project) + ['viewer' => $viewer],
        ]);
    }

    /** POST /api/v2/operations/{type}/{id}/project/tasks/{task}/approve — the customer approves a completed stage. */
    public function approve(Request $request, string $type, int $id, int $task)
    {
        return $this->decide($request, $type, $id, $task, true);
    }

    /** POST …/reject {note} — the customer sends a completed stage back; the business reopens it. */
    public function reject(Request $request, string $type, int $id, int $task)
    {
        return $this->decide($request, $type, $id, $task, false);
    }

    private function decide(Request $request, string $type, int $id, int $taskId, bool $approve)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        $operation = $this->operations->resolve($type, $id);
        $this->operations->assertParty($operation, (int) $request->user()->id);

        $project = Project::query()
            ->where('operation_type', $operation->getMorphClass())
            ->where('operation_id', $operation->getKey())
            ->firstOrFail();

        $task = \App\Models\ProjectTask::query()->where('project_id', $project->id)->findOrFail($taskId);

        $this->projects->decideApproval($task, $request->user(), $approve, $data['note'] ?? null);

        return response()->json([
            'success' => true,
            'message' => $approve ? __('تم اعتماد المرحلة.') : __('أُعيدت المرحلة إلى النشاط.'),
            'data' => $this->projects->customerView($project->fresh()),
        ]);
    }

    /**
     * GET /api/v2/projects/{project} — view a project at whatever depth the
     * viewer is entitled to: detailed for the owner/contracted customer/approved
     * detailed follower, the coarse map + percentages for a summary follower or
     * a public project. No access → 404 (a private project stays invisible).
     */
    public function showById(Request $request, int $project)
    {
        $row = Project::query()->findOrFail($project);

        $level = $this->projects->accessLevelFor($row, $request->user());
        abort_if($level === null, 404);

        return response()->json([
            'success' => true,
            'data' => $this->projects->viewFor($row, $level),
        ]);
    }

    /**
     * POST /api/v2/projects/{project}/follow — ask to follow a project's
     * progress; the business then approves and sets the access level. Allowed on
     * a public project, for the contracted customer, or to re-send an existing
     * request — otherwise the project stays invisible (404).
     */
    public function follow(Request $request, int $project)
    {
        $row = Project::query()->findOrFail($project);
        $user = $request->user();

        $alreadyKnown = ProjectFollower::query()
            ->where('project_id', (int) $row->id)
            ->where('user_id', (int) $user->id)
            ->exists();

        $mayRequest = $row->isPublic()
            || $this->projects->accessLevelFor($row, $user) !== null
            || $alreadyKnown;

        abort_if(! $mayRequest, 404);

        $follower = $this->projects->requestFollow($row, $user);

        return response()->json([
            'success' => true,
            'message' => __('تم إرسال طلب متابعة المشروع.'),
            'data' => ['status' => (string) $follower->status, 'access_level' => (string) $follower->access_level],
        ], 201);
    }

    /** DELETE /api/v2/projects/{project}/follow — stop following. */
    public function unfollow(Request $request, int $project)
    {
        ProjectFollower::query()
            ->where('project_id', $project)
            ->where('user_id', (int) $request->user()->id)
            ->delete();

        return response()->json(['success' => true, 'message' => __('تم إلغاء متابعة المشروع.')]);
    }
}
