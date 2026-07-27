<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Services\Media\ImageUploadService;
use App\Services\Projects\ProjectService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Tasks (timeline bars) inside a business's project: create/update/delete,
 * wire their finish-to-start dependencies, and record camera-captured progress
 * photos. All scoped to a project the calling business owns.
 */
class BusinessProjectTaskController extends Controller
{
    public function __construct(private readonly ProjectService $projects)
    {
    }

    public function store(Request $request, int $project)
    {
        $owned = $this->projectOrFail($request, $project);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(ProjectTask::STATUSES)],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'progress' => ['nullable', 'integer', 'between:0,100'],
            'requires_photo' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'depends_on' => ['nullable', 'array'],
            'depends_on.*' => ['integer'],
        ]);

        if (! empty($data['parent_id'])) {
            $this->taskInProject($owned, (int) $data['parent_id']); // 404 if foreign
        }

        $task = $owned->tasks()->create([
            'title' => $data['title'],
            'notes' => $data['notes'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'status' => $data['status'] ?? ProjectTask::STATUS_PENDING,
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'progress' => $data['progress'] ?? 0,
            'requires_photo' => $data['requires_photo'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        if (array_key_exists('depends_on', $data)) {
            $this->projects->setDependencies($task, $data['depends_on'] ?? []);
        }

        return response()->json([
            'success' => true,
            'message' => __('تمت إضافة المهمة.'),
            'data' => ['task' => $this->serialize($task->fresh(['dependencies:id', 'photos']))],
        ], 201);
    }

    public function update(Request $request, int $project, int $task)
    {
        $owned = $this->projectOrFail($request, $project);
        $row = $this->taskInProject($owned, $task);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'requires_photo' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'depends_on' => ['nullable', 'array'],
            'depends_on.*' => ['integer'],
        ]);

        $row->fill(array_intersect_key($data, array_flip([
            'title', 'notes', 'starts_on', 'ends_on', 'requires_photo', 'sort_order',
        ])))->save();

        if (array_key_exists('depends_on', $data)) {
            $this->projects->setDependencies($row, $data['depends_on'] ?? []);
        }

        return response()->json([
            'success' => true,
            'message' => __('تم تحديث المهمة.'),
            'data' => ['task' => $this->serialize($row->fresh(['dependencies:id', 'photos']))],
        ]);
    }

    /** PATCH progress/status — gated on camera evidence when the stage requires it. */
    public function progress(Request $request, int $project, int $task)
    {
        $owned = $this->projectOrFail($request, $project);
        $row = $this->taskInProject($owned, $task);

        $data = $request->validate([
            'progress' => ['nullable', 'integer', 'between:0,100'],
            'status' => ['nullable', Rule::in(ProjectTask::STATUSES)],
        ]);

        $updated = $this->projects->updateTaskProgress(
            $row,
            $data['progress'] ?? null,
            $data['status'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => __('تم تحديث تقدّم المهمة.'),
            'data' => ['task' => $this->serialize($updated->load(['dependencies:id', 'photos']))],
        ]);
    }

    /** POST a camera-captured progress photo (multipart: photo, source=camera). */
    public function photo(Request $request, int $project, int $task)
    {
        $owned = $this->projectOrFail($request, $project);
        $row = $this->taskInProject($owned, $task);

        $request->validate([
            'photo' => array_merge(['required'], ImageUploadService::validationRules()),
            'source' => ['required', 'string', Rule::in([Image::SOURCE_CAMERA, Image::SOURCE_UPLOAD])],
        ]);

        $image = $this->projects->attachPhoto($row, $request->file('photo'), (string) $request->input('source'));

        return response()->json([
            'success' => true,
            'message' => __('تم حفظ صورة الإثبات.'),
            'data' => ['photo' => $this->photoPayload($image)],
        ], 201);
    }

    public function destroy(Request $request, int $project, int $task)
    {
        $owned = $this->projectOrFail($request, $project);
        $row = $this->taskInProject($owned, $task);

        \DB::table('project_task_dependencies')
            ->where('task_id', (int) $row->id)
            ->orWhere('depends_on_id', (int) $row->id)
            ->delete();
        $row->children()->update(['parent_id' => null]);
        $row->delete();

        $this->projects->recomputeProgress($owned->fresh());

        return response()->json(['success' => true, 'message' => __('تم حذف المهمة.')]);
    }

    private function projectOrFail(Request $request, int $projectId): Project
    {
        return Project::query()
            ->where('id', $projectId)
            ->where('business_id', BusinessContext::id($request))
            ->firstOrFail();
    }

    private function taskInProject(Project $project, int $taskId): ProjectTask
    {
        return ProjectTask::query()
            ->where('id', $taskId)
            ->where('project_id', (int) $project->id)
            ->firstOrFail();
    }

    private function serialize(ProjectTask $t): array
    {
        return [
            'id' => (int) $t->id,
            'project_id' => (int) $t->project_id,
            'parent_id' => $t->parent_id ? (int) $t->parent_id : null,
            'title' => (string) $t->title,
            'notes' => $t->notes,
            'status' => (string) $t->status,
            'progress' => (int) $t->progress,
            'requires_photo' => (bool) $t->requires_photo,
            'starts_on' => optional($t->starts_on)->toDateString(),
            'ends_on' => optional($t->ends_on)->toDateString(),
            'sort_order' => (int) $t->sort_order,
            'depends_on' => $t->relationLoaded('dependencies')
                ? $t->dependencies->map(fn ($d) => (int) $d->id)->all()
                : [],
            'photos' => $t->relationLoaded('photos')
                ? $t->photos->map(fn (Image $i) => $this->photoPayload($i))->all()
                : [],
        ];
    }

    private function photoPayload(Image $image): array
    {
        return [
            'id' => (int) $image->id,
            'url' => asset($image->image),
            'source' => (string) $image->source,
            'is_camera' => $image->isCamera(),
            'created_at' => optional($image->created_at)->toIso8601String(),
        ];
    }
}
