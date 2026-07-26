<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\OperationChatService;
use App\Services\Projects\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A business's project management timeline. The `business` middleware guarantees
 * the caller is a business, so every project is scoped to the caller's account.
 * The show endpoint returns the computed Gantt schedule (see ProjectService).
 */
class BusinessProjectController extends Controller
{
    public function __construct(
        private readonly ProjectService $projects,
        private readonly OperationChatService $operations,
    ) {
    }

    /** GET /api/v2/business/projects — my projects, newest first. */
    public function index(Request $request)
    {
        $rows = Project::query()
            ->forBusiness((int) $request->user()->id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->withCount('tasks')
            ->latest('id')
            ->paginate((int) $request->get('per_page', 20));

        $rows->getCollection()->transform(fn (Project $p) => $this->serialize($p));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'reference' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(Project::STATUSES)],
            'starts_on' => ['nullable', 'date'],
            'due_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'operation_type' => ['nullable', Rule::in(array_keys(OperationChatService::TYPES)), 'required_with:operation_id'],
            'operation_id' => ['nullable', 'integer', 'required_with:operation_type'],
        ]);

        $project = new Project([
            'business_id' => (int) $request->user()->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'reference' => $data['reference'] ?? null,
            'status' => $data['status'] ?? Project::STATUS_PLANNING,
            'starts_on' => $data['starts_on'] ?? null,
            'due_on' => $data['due_on'] ?? null,
        ]);
        $project->save();

        if (! empty($data['operation_type'])) {
            $this->projects->linkOperation(
                $project,
                $this->ownedOperationOrFail($request, $data['operation_type'], (int) $data['operation_id']),
            );
        }

        return response()->json([
            'success' => true,
            'message' => __('تم إنشاء المشروع.'),
            'data' => ['project' => $this->serialize($project)],
        ], 201);
    }

    /** GET /api/v2/business/projects/{project} — detail + timeline. */
    public function show(Request $request, int $project)
    {
        $row = $this->ownedOrFail($request, $project);

        return response()->json([
            'success' => true,
            'data' => [
                'project' => $this->serialize($row->loadCount('tasks')),
                'timeline' => $this->projects->timeline($row),
            ],
        ]);
    }

    public function update(Request $request, int $project)
    {
        $row = $this->ownedOrFail($request, $project);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'reference' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(Project::STATUSES)],
            'starts_on' => ['nullable', 'date'],
            'due_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'operation_type' => ['nullable', Rule::in(array_keys(OperationChatService::TYPES)), 'required_with:operation_id'],
            'operation_id' => ['nullable', 'integer', 'required_with:operation_type'],
        ]);

        // Linking is handled separately so we never mass-assign the morph pair.
        if ($request->has('operation_type')) {
            $operation = empty($data['operation_type'])
                ? null
                : $this->ownedOperationOrFail($request, $data['operation_type'], (int) $data['operation_id']);
            $this->projects->linkOperation($row, $operation);
        }

        $row->update(array_intersect_key($data, array_flip([
            'title', 'description', 'reference', 'status', 'starts_on', 'due_on',
        ])));

        return response()->json([
            'success' => true,
            'message' => __('تم تحديث المشروع.'),
            'data' => ['project' => $this->serialize($row->fresh()->loadCount('tasks'))],
        ]);
    }

    public function destroy(Request $request, int $project)
    {
        $row = $this->ownedOrFail($request, $project);

        // Clean up the dependency edges and tasks this project owns.
        $taskIds = $row->tasks()->pluck('id')->all();
        if ($taskIds !== []) {
            \DB::table('project_task_dependencies')
                ->whereIn('task_id', $taskIds)
                ->orWhereIn('depends_on_id', $taskIds)
                ->delete();
        }
        $row->tasks()->delete();
        $row->delete();

        return response()->json(['success' => true, 'message' => __('تم حذف المشروع.')]);
    }

    private function ownedOrFail(Request $request, int $projectId): Project
    {
        return Project::query()
            ->where('id', $projectId)
            ->where('business_id', (int) $request->user()->id)
            ->firstOrFail();
    }

    /**
     * Resolve an operation the caller may link — it must be the business's own
     * order/booking, so a project can't be pinned to someone else's operation.
     */
    private function ownedOperationOrFail(Request $request, string $type, int $id)
    {
        $operation = $this->operations->resolve($type, $id); // 404 if the type/id is unknown

        if ((int) $operation->business_id !== (int) $request->user()->id) {
            throw ValidationException::withMessages([
                'operation_id' => __('يمكنك ربط المشروع بعملية تخصّ نشاطك فقط.'),
            ]);
        }

        return $operation;
    }

    /** Map a stored morph class back to the API token (order|booking), or null. */
    private function operationToken(?string $morphClass): ?string
    {
        return array_flip(OperationChatService::TYPES)[$morphClass] ?? null;
    }

    private function serialize(Project $p): array
    {
        return [
            'id' => (int) $p->id,
            'title' => (string) $p->title,
            'description' => $p->description,
            'reference' => $p->reference,
            'status' => (string) $p->status,
            'progress' => (int) $p->progress,
            'starts_on' => optional($p->starts_on)->toDateString(),
            'due_on' => optional($p->due_on)->toDateString(),
            'is_overdue' => $p->isOverdue(),
            'operation' => $p->operation_type && $p->operation_id ? [
                'type' => $this->operationToken($p->operation_type),
                'id' => (int) $p->operation_id,
            ] : null,
            'tasks_count' => $p->tasks_count !== null ? (int) $p->tasks_count : null,
            'created_at' => optional($p->created_at)->toIso8601String(),
        ];
    }
}
