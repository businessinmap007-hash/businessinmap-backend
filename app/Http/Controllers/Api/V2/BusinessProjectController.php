<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\OperationChatService;
use App\Services\Projects\ProjectService;
use App\Support\BusinessContext;
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
        private readonly \App\Services\Projects\ProjectTemplateService $templates,
        private readonly \App\Services\Projects\BuildingProjectGenerator $buildings,
    ) {
    }

    /**
     * POST /api/v2/business/projects/{project}/generate — write the whole plan of a residential building into an EMPTY project:
     * {kind: building, floors, basement?, apartments_per_floor, starts_on?}. Returns how many works were made.
     */
    public function generate(Request $request, int $project)
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['building'])],
            'floors' => ['required', 'integer', 'between:1,30'],
            'basement' => ['nullable', 'boolean'],
            'apartments_per_floor' => ['required', 'integer', 'between:0,10'],
            'starts_on' => ['nullable', 'date'],
        ]);
        $row = $this->ownedOrFail($request, $project);

        if ($row->tasks()->exists()) {
            throw ValidationException::withMessages(['kind' => __('المشروع فيه مراحل بالفعل؛ أنشئ مشروعًا جديدًا لتوليد المبنى.')]);
        }

        $made = $this->buildings->generate($row, $data, ! empty($data['starts_on']) ? \Illuminate\Support\Carbon::parse($data['starts_on']) : null);

        return response()->json(['success' => true, 'message' => __('تم توليد خطة المبنى.'), 'data' => ['works' => $made, 'project' => $this->serialize($row->fresh()->loadCount('tasks'))]], 201);
    }

    /** GET /api/v2/business/projects/templates — the ready stage plans offered to this business (its trade's + the general ones). */
    public function templates(Request $request)
    {
        $business = BusinessContext::business($request);

        $items = $business ? $this->templates->forBusiness($business)->map(fn (\App\Models\ProjectTemplate $t) => [
            'id' => (int) $t->id,
            'code' => (string) $t->code,
            'title' => $t->title(),
            'stages' => $t->stages->map(fn (\App\Models\ProjectTemplateStage $s) => [
                'title' => $s->title(),
                'duration_days' => (int) $s->duration_days,
                'requires_approval' => (bool) $s->requires_approval,
            ])->values()->all(),
        ])->values() : collect();

        return response()->json(['success' => true, 'data' => ['templates' => $items]]);
    }

    /**
     * GET /api/v2/business/projects/my-stages — the stages assigned to ME at the acting business that still need work (not
     * done, or done and sent back by the customer): what a staff member does today.
     */
    public function myStages(Request $request)
    {
        $me = (int) $request->user()->id;
        $businessId = BusinessContext::id($request);

        $rows = \App\Models\ProjectTask::query()
            ->assignedTo($me)
            ->whereHas('project', fn ($q) => $q->where('business_id', $businessId)->whereNotIn('status', [Project::STATUS_COMPLETED, Project::STATUS_CANCELLED]))
            ->where(fn ($q) => $q->where('status', '!=', \App\Models\ProjectTask::STATUS_DONE)->orWhere('approval_status', \App\Models\ProjectTask::APPROVAL_REJECTED))
            ->with('project:id,title')
            ->orderBy('starts_on')->orderBy('id')
            ->get()
            ->map(fn (\App\Models\ProjectTask $t) => [
                'id' => (int) $t->id,
                'project_id' => (int) $t->project_id,
                'project_title' => (string) optional($t->project)->title,
                'title' => (string) $t->title,
                'status' => (string) $t->status,
                'progress' => (int) $t->progress,
                'starts_on' => optional($t->starts_on)->toDateString(),
                'ends_on' => optional($t->ends_on)->toDateString(),
                'requires_photo' => (bool) $t->requires_photo,
                'approval_status' => $t->approval_status,
                'approval_note' => $t->approval_note,
            ])->values();

        return response()->json(['success' => true, 'data' => ['stages' => $rows]]);
    }

    /** GET /api/v2/business/projects — my projects, newest first. */
    public function index(Request $request)
    {
        $rows = Project::query()
            ->forBusiness(BusinessContext::id($request))
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
            'visibility' => ['nullable', Rule::in(Project::VISIBILITIES)],
            'starts_on' => ['nullable', 'date'],
            'due_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'operation_type' => ['nullable', Rule::in(array_keys(OperationChatService::TYPES)), 'required_with:operation_id'],
            'operation_id' => ['nullable', 'integer', 'required_with:operation_type'],
            // start from a ready stage plan (GET business/projects/templates)
            'template_id' => ['nullable', 'integer'],
        ]);

        $template = null;
        if (! empty($data['template_id'])) {
            $template = \App\Models\ProjectTemplate::query()->with('stages')->find((int) $data['template_id']);
            $business = BusinessContext::business($request);

            if (! $template || ! $business || ! $this->templates->offeredTo($template, $business)) {
                throw ValidationException::withMessages(['template_id' => __('هذا القالب غير متاح لنشاطك.')]);
            }
        }

        $project = new Project([
            'business_id' => BusinessContext::id($request),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'reference' => $data['reference'] ?? null,
            'status' => $data['status'] ?? Project::STATUS_PLANNING,
            'visibility' => $data['visibility'] ?? Project::VISIBILITY_PRIVATE,
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

        if ($template) {
            $this->templates->apply($project, $template);
            $project = $project->fresh();
        }

        return response()->json([
            'success' => true,
            'message' => __('تم إنشاء المشروع.'),
            'data' => ['project' => $this->serialize($project->loadCount('tasks'))],
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
            ] + app(\App\Services\Projects\ProjectAgreementService::class)->block($row)
              + ['quantity_log' => app(\App\Services\Projects\ProjectItemService::class)->log($row)],
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
            'visibility' => ['nullable', Rule::in(Project::VISIBILITIES)],
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
            'title', 'description', 'reference', 'status', 'visibility', 'starts_on', 'due_on',
        ])));

        if (($data['status'] ?? null) === Project::STATUS_COMPLETED) {
            $this->projects->complete($row->fresh());
        }

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

    /** GET /business/projects/{project}/followers — requests + granted followers. */
    public function followers(Request $request, int $project)
    {
        $row = $this->ownedOrFail($request, $project);

        $followers = $row->followers()
            ->with('user:id,name,logo')
            ->latest('id')
            ->get()
            ->map(fn (\App\Models\ProjectFollower $f) => [
                'user' => $f->user ? [
                    'id' => (int) $f->user->id,
                    'name' => $f->user->name,
                    'logo' => $f->user->logo,
                ] : ['id' => (int) $f->user_id],
                'status' => (string) $f->status,
                'access_level' => (string) $f->access_level,
                'requested_at' => optional($f->created_at)->toIso8601String(),
            ]);

        return response()->json(['success' => true, 'data' => ['followers' => $followers]]);
    }

    /**
     * PATCH /business/projects/{project}/followers/{user} — decide a follow
     * request (approve with an access level, or reject). Creates the grant if
     * the business is inviting a user directly (no prior request needed).
     */
    public function decideFollower(Request $request, int $project, int $user)
    {
        $row = $this->ownedOrFail($request, $project);

        $data = $request->validate([
            'approve' => ['required', 'boolean'],
            'access_level' => ['nullable', Rule::in(\App\Models\ProjectFollower::ACCESS_LEVELS)],
        ]);

        $follower = \App\Models\ProjectFollower::query()->firstOrCreate(
            ['project_id' => (int) $row->id, 'user_id' => $user],
            ['status' => \App\Models\ProjectFollower::STATUS_PENDING, 'access_level' => \App\Models\ProjectFollower::ACCESS_SUMMARY],
        );

        $follower = $this->projects->decideFollow($follower, (bool) $data['approve'], $data['access_level'] ?? null);

        return response()->json([
            'success' => true,
            'message' => __('تم تحديث متابعة المشروع.'),
            'data' => ['follower' => [
                'user_id' => (int) $follower->user_id,
                'status' => (string) $follower->status,
                'access_level' => (string) $follower->access_level,
            ]],
        ]);
    }

    /** DELETE /business/projects/{project}/followers/{user} — revoke access. */
    public function removeFollower(Request $request, int $project, int $user)
    {
        $row = $this->ownedOrFail($request, $project);

        $row->followers()->where('user_id', $user)->delete();

        return response()->json(['success' => true, 'message' => __('تمت إزالة المتابع.')]);
    }

    private function ownedOrFail(Request $request, int $projectId): Project
    {
        return Project::query()
            ->where('id', $projectId)
            ->where('business_id', BusinessContext::id($request))
            ->firstOrFail();
    }

    /**
     * Resolve an operation the caller may link — it must be the business's own
     * order/booking, so a project can't be pinned to someone else's operation.
     */
    private function ownedOperationOrFail(Request $request, string $type, int $id)
    {
        $operation = $this->operations->resolve($type, $id); // 404 if the type/id is unknown

        if ((int) $operation->business_id !== BusinessContext::id($request)) {
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
            'visibility' => (string) $p->visibility,
            'progress' => (int) $p->progress,
            'starts_on' => optional($p->starts_on)->toDateString(),
            'due_on' => optional($p->due_on)->toDateString(),
            'grace_until' => optional($p->grace_until)->toDateString(),
            'completed_at' => optional($p->completed_at)->toIso8601String(),
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
