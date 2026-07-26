<?php

namespace App\Services\Projects;

use App\Models\Image;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Services\Media\ImageUploadService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The project-management timeline: it turns a project's tasks and their
 * finish-to-start dependencies into a schedule (each task's earliest start /
 * finish, its slack, and the critical path), and it guards the two integrity
 * rules the domain needs — no dependency cycles, and no stage marked done
 * without a live camera photo to prove it.
 *
 * Built for manufacturing/construction: a furniture factory tracking a
 * shipment through build stages, a contractor finishing residential units.
 */
class ProjectService
{
    public function __construct(private readonly ImageUploadService $uploads)
    {
    }

    /* ---------------------------------------------------------------- tasks */

    /**
     * Set the finish-to-start predecessors of a task, refusing self-links,
     * cross-project links, and anything that would close a cycle.
     *
     * @param  array<int>  $dependsOnIds
     */
    public function setDependencies(ProjectTask $task, array $dependsOnIds): void
    {
        $dependsOnIds = array_values(array_unique(array_map('intval', $dependsOnIds)));

        if (in_array((int) $task->id, $dependsOnIds, true)) {
            throw ValidationException::withMessages([
                'depends_on' => __('لا يمكن ربط المهمة بنفسها.'),
            ]);
        }

        if ($dependsOnIds !== []) {
            $valid = ProjectTask::query()
                ->where('project_id', (int) $task->project_id)
                ->whereIn('id', $dependsOnIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (count($valid) !== count($dependsOnIds)) {
                throw ValidationException::withMessages([
                    'depends_on' => __('المهمة التابعة يجب أن تكون في نفس المشروع.'),
                ]);
            }

            // A new edge predecessor -> task closes a cycle only if the task can
            // already reach that predecessor through existing edges.
            $reachable = $this->tasksReachableFrom($task);
            foreach ($dependsOnIds as $predId) {
                if (isset($reachable[$predId])) {
                    throw ValidationException::withMessages([
                        'depends_on' => __('هذا الربط يُنشئ حلقة مغلقة في التبعيات.'),
                    ]);
                }
            }
        }

        $task->dependencies()->sync($dependsOnIds);
        $this->recomputeProgress($task->project()->first());
    }

    /**
     * Advance a task's progress/status. A stage that requires a photo cannot be
     * marked done (or pushed to 100%) until it carries a camera-captured photo.
     */
    public function updateTaskProgress(ProjectTask $task, ?int $progress, ?string $status): ProjectTask
    {
        $targetProgress = $progress === null ? (int) $task->progress : max(0, min(100, $progress));
        $targetStatus = $status ?: $task->status;

        $isCompleting = $targetStatus === ProjectTask::STATUS_DONE || $targetProgress >= 100;

        if ($isCompleting && $task->requires_photo && ! $task->photos()->camera()->exists()) {
            throw ValidationException::withMessages([
                'photo' => __('يجب إرفاق صورة ملتقطة بالكاميرا قبل إنهاء هذه المرحلة.'),
            ]);
        }

        if ($targetStatus === ProjectTask::STATUS_DONE) {
            $targetProgress = 100;
        }
        if ($targetProgress >= 100 && $targetStatus !== ProjectTask::STATUS_BLOCKED) {
            $targetStatus = ProjectTask::STATUS_DONE;
        }

        $task->progress = $targetProgress;
        $task->status = $targetStatus;
        $task->save();

        $this->recomputeProgress($task->project()->first());

        return $task->fresh();
    }

    /**
     * Attach a progress photo to a task. Evidence must be camera-captured — an
     * uploaded/gallery image is refused. The app only offers the live camera
     * for this surface; the server enforces the declared origin.
     */
    public function attachPhoto(ProjectTask $task, UploadedFile $file, string $source): Image
    {
        if ($source !== Image::SOURCE_CAMERA) {
            throw ValidationException::withMessages([
                'source' => __('صور الإثبات يجب أن تكون ملتقطة بالكاميرا مباشرة.'),
            ]);
        }

        $path = $this->uploads->store($file);

        return $task->photos()->create([
            'image' => $path,
            'source' => Image::SOURCE_CAMERA,
        ]);
    }

    /**
     * Link (or unlink) a project to the operation it fulfils, so the contracted
     * customer can follow its progress. Passing null clears the link.
     */
    public function linkOperation(Project $project, ?Model $operation): void
    {
        $project->operation_type = $operation?->getMorphClass();
        $project->operation_id = $operation?->getKey();
        $project->save();
    }

    /**
     * The read-only progress view for the customer following an operation: the
     * project header, the computed timeline, and each stage's camera evidence.
     * Internal notes are deliberately omitted — the customer sees progress, not
     * the business's private planning.
     */
    public function customerView(Project $project): array
    {
        $tasks = $project->tasks()->with('photos')->get()->map(fn (ProjectTask $t) => [
            'id' => (int) $t->id,
            'title' => (string) $t->title,
            'status' => (string) $t->status,
            'progress' => (int) $t->progress,
            'starts_on' => optional($t->starts_on)->toDateString(),
            'ends_on' => optional($t->ends_on)->toDateString(),
            'photos' => $t->photos->map(fn (Image $i) => [
                'url' => asset($i->image),
                'source' => (string) $i->source,
                'is_camera' => $i->isCamera(),
                'captured_at' => optional($i->created_at)->toIso8601String(),
            ])->all(),
        ])->all();

        return [
            'project' => [
                'id' => (int) $project->id,
                'title' => (string) $project->title,
                'reference' => $project->reference,
                'status' => (string) $project->status,
                'progress' => (int) $project->progress,
                'starts_on' => optional($project->starts_on)->toDateString(),
                'due_on' => optional($project->due_on)->toDateString(),
                'is_overdue' => $project->isOverdue(),
            ],
            'timeline' => $this->timeline($project),
            'tasks' => $tasks,
        ];
    }

    /* ------------------------------------------------------------ timeline */

    /**
     * Compute the schedule for a project: per-task earliest start/finish (as
     * day offsets from the project start and, when a start date is set, as real
     * dates), slack, and whether the task is on the critical path. Cycles are
     * prevented at write time, but the walk stays defensive.
     *
     * @return array{
     *     project_duration_days:int,
     *     has_cycle:bool,
     *     critical_path:array<int>,
     *     tasks:array<int,array<string,mixed>>
     * }
     */
    public function timeline(Project $project): array
    {
        /** @var \Illuminate\Support\Collection<int,ProjectTask> $tasks */
        $tasks = $project->tasks()->with('dependencies:id')->get()->keyBy('id');

        if ($tasks->isEmpty()) {
            return [
                'project_duration_days' => 0,
                'has_cycle' => false,
                'critical_path' => [],
                'tasks' => [],
            ];
        }

        $duration = [];
        $preds = [];       // id => [predecessor ids] (within project)
        $succs = [];       // id => [successor ids]
        foreach ($tasks as $id => $task) {
            $duration[$id] = $task->durationDays();
            $preds[$id] = [];
            $succs[$id] = $succs[$id] ?? [];
        }
        foreach ($tasks as $id => $task) {
            foreach ($task->dependencies as $dep) {
                $pid = (int) $dep->id;
                if (! isset($tasks[$pid])) {
                    continue;
                }
                $preds[$id][] = $pid;
                $succs[$pid][] = $id;
            }
        }

        // Kahn topological order (deterministic by sort_order then id).
        $indeg = [];
        foreach ($preds as $id => $list) {
            $indeg[$id] = count($list);
        }
        $ready = collect($tasks)
            ->filter(fn ($t, $id) => $indeg[$id] === 0)
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->keys()
            ->all();

        $order = [];
        while ($ready !== []) {
            $u = array_shift($ready);
            $order[] = $u;
            foreach ($succs[$u] as $v) {
                if (--$indeg[$v] === 0) {
                    $ready[] = $v;
                }
            }
        }

        $hasCycle = count($order) < $tasks->count();
        if ($hasCycle) {
            // Fall back to a stable order so we still return something usable.
            $order = $tasks->sortBy([['sort_order', 'asc'], ['id', 'asc']])->keys()->all();
        }

        // Forward pass — longest path gives earliest start/finish (day offsets).
        $es = [];
        $ef = [];
        foreach ($order as $id) {
            $start = 0;
            foreach ($preds[$id] as $pid) {
                $start = max($start, $ef[$pid] ?? 0);
            }
            $es[$id] = $start;
            $ef[$id] = $start + $duration[$id];
        }

        $projectDuration = empty($ef) ? 0 : max($ef);

        // Backward pass — latest start/finish and slack.
        $lf = [];
        $ls = [];
        foreach (array_reverse($order) as $id) {
            $finish = $projectDuration;
            foreach ($succs[$id] as $sid) {
                $finish = min($finish, $ls[$sid] ?? $projectDuration);
            }
            $lf[$id] = $finish;
            $ls[$id] = $finish - $duration[$id];
        }

        $today = Carbon::today();
        $projectStart = $project->starts_on ? $project->starts_on->copy()->startOfDay() : null;

        $rows = [];
        $criticalPath = [];
        foreach ($tasks as $id => $task) {
            $slack = ($ls[$id] ?? 0) - ($es[$id] ?? 0);
            $isCritical = ! $hasCycle && $slack === 0;
            if ($isCritical) {
                $criticalPath[] = (int) $id;
            }

            $plannedStart = $projectStart ? $projectStart->copy()->addDays($es[$id]) : null;
            $plannedEnd = $projectStart ? $projectStart->copy()->addDays(max(0, $ef[$id] - 1)) : null;

            // Overdue: not done and its (explicit or planned) end is in the past.
            $effectiveEnd = $task->ends_on ?: $plannedEnd;
            $isOverdue = ! $task->isDone() && $effectiveEnd && $effectiveEnd->copy()->endOfDay()->isPast();

            // Its own scheduled start is earlier than the dependencies allow.
            $startsBeforeDeps = $task->starts_on && $plannedStart
                && $task->starts_on->lt($plannedStart);

            $rows[(int) $id] = [
                'id' => (int) $id,
                'title' => (string) $task->title,
                'status' => (string) $task->status,
                'progress' => (int) $task->progress,
                'duration_days' => $duration[$id],
                'earliest_start_offset' => $es[$id],
                'earliest_finish_offset' => $ef[$id],
                'slack_days' => $slack,
                'is_critical' => $isCritical,
                'planned_start' => optional($plannedStart)->toDateString(),
                'planned_end' => optional($plannedEnd)->toDateString(),
                'is_overdue' => (bool) $isOverdue,
                'starts_before_dependencies' => (bool) $startsBeforeDeps,
                'depends_on' => $preds[$id],
            ];
        }

        return [
            'project_duration_days' => $projectDuration,
            'has_cycle' => $hasCycle,
            'critical_path' => $criticalPath,
            'tasks' => $rows,
        ];
    }

    /* ------------------------------------------------------------ rollups */

    /** Refresh the project's cached progress from its tasks (simple average). */
    public function recomputeProgress(?Project $project): void
    {
        if (! $project) {
            return;
        }

        $avg = (int) round((float) $project->tasks()->avg('progress'));
        $project->progress = max(0, min(100, $avg));
        $project->save();
    }

    /* ------------------------------------------------------------ helpers */

    /**
     * Every task reachable from $task by following successor edges (i.e. the
     * tasks that transitively depend on it) — used to catch dependency cycles.
     *
     * @return array<int,bool>
     */
    private function tasksReachableFrom(ProjectTask $task): array
    {
        // successor edges of the project: depends_on_id -> task_id.
        $edges = DB::table('project_task_dependencies')
            ->join('project_tasks as t', 't.id', '=', 'project_task_dependencies.task_id')
            ->where('t.project_id', (int) $task->project_id)
            ->get(['project_task_dependencies.task_id', 'project_task_dependencies.depends_on_id']);

        $succ = [];
        foreach ($edges as $e) {
            $succ[(int) $e->depends_on_id][] = (int) $e->task_id;
        }

        $reachable = [];
        $stack = [(int) $task->id];
        while ($stack !== []) {
            $node = array_pop($stack);
            foreach ($succ[$node] ?? [] as $next) {
                if (! isset($reachable[$next])) {
                    $reachable[$next] = true;
                    $stack[] = $next;
                }
            }
        }

        return $reachable;
    }
}
