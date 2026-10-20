<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskItem;
use App\Services\Projects\ProjectItemService;
use App\Services\Projects\ProjectService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;

/**
 * The quantities of a stage (columns, concrete, bricks…). The engineer edits them; after the plan is agreed a change to what
 * was PLANNED answers 202 with the scope request waiting for the customer, while what was DONE is always applied at once.
 */
class ProjectItemController extends Controller
{
    public function __construct(private readonly ProjectItemService $items, private readonly ProjectService $projects)
    {
    }

    public function store(Request $request, int $project, int $task)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'unit' => ['nullable', 'string', 'max:30'],
            'planned' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $stage = $this->task($request, $project, $task);
        $result = $this->items->add($stage, $data, $request->user(), $data['reason'] ?? null);

        return $this->reply($stage, $result['request'], 201);
    }

    public function update(Request $request, int $project, int $task, int $item)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'unit' => ['nullable', 'string', 'max:30'],
            'planned' => ['sometimes', 'numeric', 'min:0'],
            'done' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $stage = $this->task($request, $project, $task);
        $row = ProjectTaskItem::query()->where('task_id', $stage->id)->findOrFail($item);
        $result = $this->items->update($row, $data, $request->user(), $data['reason'] ?? null);

        return $this->reply($stage, $result['request'], 200);
    }

    public function destroy(Request $request, int $project, int $task, int $item)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $stage = $this->task($request, $project, $task);
        $row = ProjectTaskItem::query()->where('task_id', $stage->id)->findOrFail($item);
        $result = $this->items->remove($row, $request->user(), $data['reason'] ?? null);

        return $this->reply($stage, $result['request'], 200);
    }

    /** GET business/projects/{project}/quantity-log */
    public function log(Request $request, int $project)
    {
        $row = Project::query()->where('id', $project)->where('business_id', BusinessContext::id($request))->firstOrFail();

        return response()->json(['success' => true, 'data' => ['log' => $this->items->log($row, 200)]]);
    }

    private function reply(ProjectTask $stage, $changeRequest, int $status)
    {
        $stage = $stage->fresh(['items']);

        return response()->json([
            'success' => true,
            'message' => $changeRequest ? __('أُرسل طلب تعديل النطاق إلى العميل ولن يُطبَّق قبل موافقته.') : __('تم حفظ الكمية.'),
            'data' => [
                'items' => $stage->items->map(fn ($i) => $this->projects->itemPayload($i))->all(),
                'progress' => (int) $stage->progress,
                'pending_request' => $changeRequest ? ['id' => (int) $changeRequest->id, 'type' => (string) $changeRequest->type, 'payload' => $changeRequest->payload] : null,
            ],
        ], $changeRequest ? 202 : $status);
    }

    private function task(Request $request, int $projectId, int $taskId): ProjectTask
    {
        $project = Project::query()->where('id', $projectId)->where('business_id', BusinessContext::id($request))->firstOrFail();

        return ProjectTask::query()->where('project_id', $project->id)->findOrFail($taskId);
    }
}
