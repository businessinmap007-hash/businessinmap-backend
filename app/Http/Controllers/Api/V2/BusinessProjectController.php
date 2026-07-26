<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\Projects\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A business's project management timeline. The `business` middleware guarantees
 * the caller is a business, so every project is scoped to the caller's account.
 * The show endpoint returns the computed Gantt schedule (see ProjectService).
 */
class BusinessProjectController extends Controller
{
    public function __construct(private readonly ProjectService $projects)
    {
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
        ]);

        $data['business_id'] = (int) $request->user()->id;
        $data['status'] = $data['status'] ?? Project::STATUS_PLANNING;

        $project = Project::create($data);

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
        ]);

        $row->update($data);

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
            'tasks_count' => $p->tasks_count !== null ? (int) $p->tasks_count : null,
            'created_at' => optional($p->created_at)->toIso8601String(),
        ];
    }
}
