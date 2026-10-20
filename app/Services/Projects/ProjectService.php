<?php

namespace App\Services\Projects;

use App\Models\Image;
use App\Models\Project;
use App\Models\ProjectFollower;
use App\Models\ProjectTask;
use App\Models\User;
use App\Services\Media\ImageUploadService;
use App\Services\Notifications\NotificationDispatcherService;
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
    public function __construct(
        private readonly ImageUploadService $uploads,
        private readonly NotificationDispatcherService $notifications,
    ) {
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
        // a main stage measures itself by the works inside it
        if ($task->children()->exists()) {
            throw ValidationException::withMessages(['task' => __('هذه مرحلة رئيسية: نسبتها تُحسب من الأعمال داخلها.')]);
        }

        $targetProgress = $progress === null ? (int) $task->progress : max(0, min(100, $progress));
        $targetStatus = $status ?: $task->status;

        // on an AGREED project nothing advances before the customer approved the start of its main stage
        if ($targetProgress > 0 || ! in_array($targetStatus, [ProjectTask::STATUS_PENDING], true)) {
            $this->assertStartAllowed($task);
        }

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

        $wasDone = $task->isDone();

        if ($targetProgress > 0 && $targetStatus === ProjectTask::STATUS_PENDING) {
            $targetStatus = ProjectTask::STATUS_IN_PROGRESS;
        }

        $task->progress = $targetProgress;
        $task->status = $targetStatus;
        $task->save();

        $project = $task->project()->first();
        $this->recomputeProgress($project);

        // A stage just crossed into "done" → tell the followers progress moved; one that asks for the customer's approval
        // now waits for it.
        if (! $wasDone && $task->isDone() && $project) {
            if ($task->requires_approval) {
                $task->forceFill(['approval_status' => ProjectTask::APPROVAL_PENDING, 'approval_note' => null, 'approval_at' => null])->save();
            }

            $this->notifyStageCompleted($project, $task);
        }

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

    /* ----------------------------------------------------------- followers */

    /**
     * A user asks to follow a project's progress. Idempotent — a repeat request
     * returns the existing row (an already-approved follow is left intact).
     */
    public function requestFollow(Project $project, User $user): ProjectFollower
    {
        return ProjectFollower::query()->firstOrCreate(
            ['project_id' => (int) $project->id, 'user_id' => (int) $user->id],
            ['status' => ProjectFollower::STATUS_PENDING, 'access_level' => ProjectFollower::ACCESS_SUMMARY],
        );
    }

    /**
     * The business decides a follow request: approve (granting summary or the
     * detailed level) or reject. Detailed is the precise, evidence-bearing view;
     * summary is the coarse map + percentages.
     */
    public function decideFollow(ProjectFollower $follower, bool $approve, ?string $accessLevel = null): ProjectFollower
    {
        $follower->status = $approve ? ProjectFollower::STATUS_APPROVED : ProjectFollower::STATUS_REJECTED;

        if ($approve) {
            $follower->access_level = in_array($accessLevel, ProjectFollower::ACCESS_LEVELS, true)
                ? $accessLevel
                : ProjectFollower::ACCESS_SUMMARY;
        }

        $follower->save();

        return $follower;
    }

    /**
     * What a viewer may see of a project: 'owner' / 'detailed' / 'summary' / null.
     *   - the owning business → owner (everything);
     *   - the contracted customer on the linked operation → detailed;
     *   - an approved follower → the level the business granted them;
     *   - a public project → summary for any signed-in viewer;
     *   - otherwise null (no access → the caller should 404).
     */
    public function accessLevelFor(Project $project, ?User $user): ?string
    {
        if ($user && (int) $user->id === (int) $project->business_id) {
            return 'owner';
        }

        if ($user && (int) $user->id === $this->contractedCustomerId($project)) {
            return ProjectFollower::ACCESS_DETAILED;
        }

        if ($user) {
            $follow = ProjectFollower::query()
                ->where('project_id', (int) $project->id)
                ->where('user_id', (int) $user->id)
                ->where('status', ProjectFollower::STATUS_APPROVED)
                ->first();

            if ($follow) {
                return $follow->access_level;
            }
        }

        if ($project->isPublic()) {
            return ProjectFollower::ACCESS_SUMMARY;
        }

        return null;
    }

    /** Render a project for a viewer at the given access level. */
    public function viewFor(Project $project, string $accessLevel): array
    {
        return $accessLevel === ProjectFollower::ACCESS_SUMMARY
            ? $this->summaryView($project)
            : $this->customerView($project);
    }

    /**
     * The coarse view: just the shape (the timeline map) and completion
     * percentages — no dates, notes, or evidence photos. This is what a
     * summary-level follower or a public viewer sees.
     */
    public function summaryView(Project $project): array
    {
        $timeline = $this->timeline($project);

        $tasks = array_values(array_map(fn (array $t) => [
            'id' => $t['id'],
            'title' => $t['title'],
            'progress' => $t['progress'],
            'is_critical' => $t['is_critical'],
            'earliest_start_offset' => $t['earliest_start_offset'],
            'earliest_finish_offset' => $t['earliest_finish_offset'],
            'depends_on' => $t['depends_on'],
        ], $timeline['tasks']));

        return [
            'access_level' => ProjectFollower::ACCESS_SUMMARY,
            'project' => [
                'id' => (int) $project->id,
                'title' => (string) $project->title,
                'status' => (string) $project->status,
                'progress' => (int) $project->progress,
                'visibility' => (string) $project->visibility,
            ],
            'timeline' => [
                'project_duration_days' => $timeline['project_duration_days'],
                'critical_path' => $timeline['critical_path'],
                'tasks' => $tasks,
            ],
        ];
    }

    /**
     * The read-only progress view for the customer following an operation: the
     * project header, the computed timeline, and each stage's camera evidence.
     * Internal notes are deliberately omitted — the customer sees progress, not
     * the business's private planning.
     */
    public function customerView(Project $project): array
    {
        $assignees = $this->assigneesOf((int) $project->business_id, $project->tasks()->pluck('id')->all());

        $tasks = $project->tasks()->with(['photos', 'items'])->orderBy('sort_order')->orderBy('id')->get()->map(fn (ProjectTask $t) => [
            'id' => (int) $t->id,
            'parent_id' => $t->parent_id ? (int) $t->parent_id : null,
            'zone' => $t->zone,
            'weight' => (int) $t->weight,
            'details' => $t->details,
            'start_status' => $t->start_status,
            'start_note' => $t->start_note,
            'items' => $t->items->map(fn ($i) => $this->itemPayload($i))->all(),
            'title' => (string) $t->title,
            'status' => (string) $t->status,
            'progress' => (int) $t->progress,
            'requires_approval' => (bool) $t->requires_approval,
            'approval_status' => $t->approval_status,
            'approval_note' => $t->approval_note,
            'assignee' => $assignees[(int) $t->id] ?? null,
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
            'access_level' => ProjectFollower::ACCESS_DETAILED,
            'project' => [
                'id' => (int) $project->id,
                'title' => (string) $project->title,
                'reference' => $project->reference,
                'status' => (string) $project->status,
                'progress' => (int) $project->progress,
                'starts_on' => optional($project->starts_on)->toDateString(),
                'due_on' => optional($project->due_on)->toDateString(),
                'grace_until' => optional($project->grace_until)->toDateString(),
                'completed_at' => optional($project->completed_at)->toIso8601String(),
                'is_overdue' => $project->isOverdue(),
            ],
            'timeline' => $this->timeline($project),
            'tasks' => $tasks,
        ] + app(ProjectAgreementService::class)->block($project) + [
            'quantity_log' => app(ProjectItemService::class)->log($project),
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
        $tasks = $project->tasks()->with(['dependencies:id', 'items'])->get()->keyBy('id');

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
                'requires_approval' => (bool) $task->requires_approval,
                'approval_status' => $task->approval_status,
                'approval_note' => $task->approval_note,
                'parent_id' => $task->parent_id ? (int) $task->parent_id : null,
                'zone' => $task->zone,
                'weight' => (int) $task->weight,
                'details' => $task->details,
                'start_status' => $task->start_status,
                'start_note' => $task->start_note,
                'items' => $task->items->map(fn ($i) => $this->itemPayload($i))->all(),
            ];
        }

        // who is responsible for each stage (the assignment core)
        foreach ($this->assigneesOf((int) $project->business_id, array_keys($rows)) as $taskId => $person) {
            $rows[$taskId]['assignee'] = $person;
        }

        return [
            'project_duration_days' => $projectDuration,
            'has_cycle' => $hasCycle,
            'critical_path' => $criticalPath,
            'tasks' => $rows,
        ];
    }

    /* ------------------------------------------------------------ rollups */

    /**
     * Refresh the project's cached progress. Every work counts by its weight (its own `weight`, else its length in days); a
     * main stage — one that has children — takes its progress and status from them; the project is the weighted mean of its
     * top-level stages. When every work is done the project is finished.
     */
    public function recomputeProgress(?Project $project): void
    {
        if (! $project) {
            return;
        }

        $tasks = $project->tasks()->get();

        if ($tasks->isEmpty()) {
            $project->progress = 0;
            $project->save();

            return;
        }

        $byParent = $tasks->groupBy(fn (ProjectTask $t) => (int) $t->parent_id);
        $weightOf = fn (ProjectTask $t): int => $t->weight > 0 ? (int) $t->weight : $t->durationDays();

        $calc = function (ProjectTask $t) use (&$calc, $byParent, $weightOf): array {
            $kids = $byParent->get((int) $t->id);

            if (! $kids || $kids->isEmpty()) {
                return [(float) $t->progress, $weightOf($t)];
            }

            $sum = 0.0;
            $weight = 0;
            $allDone = true;
            $any = false;

            foreach ($kids as $kid) {
                [$p, $w] = $calc($kid);
                $sum += $p * $w;
                $weight += $w;
                $allDone = $allDone && $kid->status === ProjectTask::STATUS_DONE;
                $any = $any || $p > 0;
            }

            $progress = $weight > 0 ? $sum / $weight : 0.0;
            $status = $allDone ? ProjectTask::STATUS_DONE : ($any ? ProjectTask::STATUS_IN_PROGRESS : $t->status);
            $rounded = (int) round($progress);

            if ((int) $t->progress !== $rounded || $t->status !== $status) {
                $t->forceFill(['progress' => $rounded, 'status' => $status])->saveQuietly();
            }

            return [$progress, max(1, $weight)];
        };

        $sum = 0.0;
        $weight = 0;
        foreach ($byParent->get(0, collect()) as $root) {
            [$p, $w] = $calc($root);
            $sum += $p * $w;
            $weight += $w;
        }

        $project->progress = $weight > 0 ? max(0, min(100, (int) round($sum / $weight))) : 0;
        $project->save();

        $leaves = $tasks->filter(fn (ProjectTask $t) => ! $byParent->has((int) $t->id));
        if ($leaves->isNotEmpty() && $leaves->every(fn (ProjectTask $t) => $t->status === ProjectTask::STATUS_DONE)
            && ! in_array($project->status, [Project::STATUS_COMPLETED, Project::STATUS_CANCELLED], true)) {
            $this->complete($project);
        }
    }

    /**
     * The job is finished. On a project agreed with a customer this is also the moment it is rated: on time (by the agreed
     * date + grace, with the customer's waiting time left out) it counts as a success for the business, otherwise as late.
     */
    public function complete(Project $project): void
    {
        if ($project->completed_at) {
            return;
        }

        $project->forceFill(['status' => Project::STATUS_COMPLETED, 'completed_at' => now(), 'progress' => 100])->save();

        $customerId = $this->contractedCustomerId($project);
        $deadline = $project->effectiveDeadline();

        if ($customerId && $deadline && app(ProjectAgreementService::class)->isAgreed($project)) {
            app(\App\Services\Ratings\RatingService::class)->recordOutcome(
                (int) $project->business_id,
                \App\Models\UserOperationRating::ROLE_BUSINESS,
                $project->completed_at->lte($deadline) ? \App\Models\RatingOutcomeEvent::OUTCOME_SUCCESS : \App\Models\RatingOutcomeEvent::OUTCOME_LATE,
                \App\Models\RatingOutcomeEvent::OP_PROJECT,
                (int) $project->id,
            );

            try {
                $this->notifications->dispatch('project_stage_completed', $customerId, [
                    'title_ar' => 'اكتمل مشروعك', 'title_en' => 'Your project is complete',
                    'body_ar' => 'اكتمل المشروع «' . $project->title . '». يمكنك تقييم التعامل.',
                    'body_en' => 'The project "' . $project->title . '" is complete.',
                    'notifiable_type' => Project::class, 'notifiable_id' => (int) $project->id,
                    'source_type' => Project::class, 'source_id' => (int) $project->id,
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * On an AGREED project a work may move only after the customer approved the start of its main (top-level) stage.
     *
     * @throws ValidationException
     */
    public function assertStartAllowed(ProjectTask $task): void
    {
        $project = Project::query()->find($task->project_id);

        if (! $project || ! app(ProjectAgreementService::class)->isAgreed($project)) {
            return;
        }

        $top = $task;
        $guard = 0;
        while ($top->parent_id && $guard++ < 10) {
            $top = ProjectTask::query()->find($top->parent_id) ?? $top;
            if (! $top->parent_id) {
                break;
            }
        }

        if ($top->start_status !== 'approved') {
            throw ValidationException::withMessages(['task' => __('تحتاج هذه المرحلة موافقة العميل على بدئها أولًا.')]);
        }
    }

    /* ------------------------------------------------------------ approval */

    /**
     * The contracted customer's answer on a completed stage that asks for it: approve it, or send it back with a note (the
     * stage reopens at 90% and must be completed — with its photo — again). Only the customer of the linked operation.
     *
     * @throws ValidationException
     */
    public function decideApproval(ProjectTask $task, User $customer, bool $approve, ?string $note = null): ProjectTask
    {
        $project = $task->project()->first();

        if (! $project || $this->contractedCustomerId($project) !== (int) $customer->id) {
            throw ValidationException::withMessages(['task' => __('هذه المرحلة ليست من مشروعك.')]);
        }

        if ($task->approval_status !== ProjectTask::APPROVAL_PENDING) {
            throw ValidationException::withMessages(['task' => __('هذه المرحلة لا تنتظر اعتمادك.')]);
        }

        $note = $note !== null ? mb_substr(trim($note), 0, 500) : null;

        if (! $approve && ($note === null || $note === '')) {
            throw ValidationException::withMessages(['rejection_note' => __('اكتب ملاحظتك على المرحلة.')]);
        }

        if ($approve) {
            $task->forceFill(['approval_status' => ProjectTask::APPROVAL_APPROVED, 'approval_note' => $note ?: null, 'approval_at' => now()])->save();
        } else {
            $task->forceFill([
                'approval_status' => ProjectTask::APPROVAL_REJECTED, 'approval_note' => $note, 'approval_at' => now(),
                'status' => ProjectTask::STATUS_IN_PROGRESS, 'progress' => min(90, (int) $task->progress),
            ])->save();
            $this->recomputeProgress($project);
        }

        $this->notifyApprovalDecision($project, $task, $approve);

        return $task->fresh();
    }

    /** The business hears the customer's answer on its stage. */
    private function notifyApprovalDecision(Project $project, ProjectTask $task, bool $approved): void
    {
        try {
            $this->notifications->dispatch('project_stage_completed', (int) $project->business_id, [
                'title_ar' => $approved ? 'اعتمد العميل مرحلة' : 'أعاد العميل مرحلة للتعديل',
                'title_en' => $approved ? 'The customer approved a stage' : 'The customer sent a stage back',
                'body_ar' => ($approved ? 'اعتُمدت المرحلة «' : 'أُعيدت المرحلة «') . $task->title . '» في المشروع «' . $project->title . '».'
                    . ($approved || ! $task->approval_note ? '' : ' الملاحظة: ' . $task->approval_note),
                'body_en' => 'The stage "' . $task->title . '" of "' . $project->title . '" was ' . ($approved ? 'approved.' : 'sent back.')
                    . ($approved || ! $task->approval_note ? '' : ' Note: ' . $task->approval_note),
                'notifiable_type' => Project::class,
                'notifiable_id' => (int) $project->id,
                'source_type' => ProjectTask::class,
                'source_id' => (int) $task->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * The people responsible for stages, for many at once: task id → {id, name, title}. Reads the assignment core; a former
     * provider still names who did the work.
     *
     * @param  iterable<int>  $taskIds
     * @return array<int,array{id:int,name:string,title:?string}>
     */
    public function assigneesOf(int $businessId, iterable $taskIds): array
    {
        $map = app(\App\Services\Assignments\AssignmentService::class)->assigneesOf((new ProjectTask())->getMorphClass(), $taskIds);

        if ($map === []) {
            return [];
        }

        $people = \App\Support\Providers::parties($businessId, array_values($map));
        $out = [];

        foreach ($map as $taskId => $userId) {
            if (isset($people[$userId])) {
                $out[(int) $taskId] = ['id' => (int) $userId, 'name' => (string) $people[$userId]['name'], 'title' => $people[$userId]['title'] ?? null];
            }
        }

        return $out;
    }

    /** @return array<string,mixed> */
    public function itemPayload(\App\Models\ProjectTaskItem $i): array
    {
        return [
            'id' => (int) $i->id, 'name' => (string) $i->name, 'unit' => $i->unit,
            'planned' => (float) $i->planned, 'done' => (float) $i->done, 'notes' => $i->notes,
        ];
    }

    /* ------------------------------------------------------------ helpers */

    /** The user_id of the customer on the project's linked operation, if any. */
    public function contractedCustomerId(Project $project): ?int
    {
        if (! $project->operation_type || ! $project->operation_id) {
            return null;
        }

        $operation = $project->operation()->first();

        return $operation ? (int) $operation->user_id : null;
    }

    /**
     * Tell everyone entitled to follow that a stage was completed: the
     * contracted customer and every approved follower. Best-effort — a delivery
     * failure never blocks the progress update.
     */
    private function notifyStageCompleted(Project $project, ProjectTask $task): void
    {
        $recipients = $project->followers()
            ->where('status', ProjectFollower::STATUS_APPROVED)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $customerId = $this->contractedCustomerId($project);
        if ($customerId) {
            $recipients[] = $customerId;
        }

        $recipients = array_values(array_unique(array_filter(
            $recipients,
            fn (int $id) => $id > 0 && $id !== (int) $project->business_id,
        )));

        foreach ($recipients as $userId) {
            // the contracted customer is asked to approve a stage that wants it
            $asks = $task->requires_approval && $userId === $customerId;

            try {
                $this->notifications->dispatch('project_stage_completed', $userId, [
                    // Stored notification content stays UNwrapped (bilingual columns).
                    'title_ar' => $asks ? 'مرحلة بانتظار اعتمادك' : 'اكتملت مرحلة في مشروعك',
                    'title_en' => $asks ? 'A stage is waiting for your approval' : 'A stage was completed',
                    'body_ar' => ($asks ? 'اكتملت المرحلة «' . $task->title . '» في المشروع «' . $project->title . '» وتنتظر اعتمادك.' : 'اكتملت المرحلة «' . $task->title . '» في المشروع «' . $project->title . '».'),
                    'body_en' => ($asks ? 'The stage "' . $task->title . '" of "' . $project->title . '" is complete and waits for your approval.' : 'The stage "' . $task->title . '" of "' . $project->title . '" is complete.'),
                    'notifiable_type' => Project::class,
                    'notifiable_id' => (int) $project->id,
                    'source_type' => ProjectTask::class,
                    'source_id' => (int) $task->id,
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

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
