<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Starts a project from a ready stage plan. The plan is COPIED into the project (tasks in a chain, each finishing before the
 * next starts), so editing the project never touches the template.
 */
final class ProjectTemplateService
{
    public function __construct(private readonly ProjectService $projects)
    {
    }

    /**
     * The templates offered to this business: those scoped to its trade, plus the general ones.
     *
     * @return \Illuminate\Support\Collection<int,ProjectTemplate>
     */
    public function forBusiness(User $business)
    {
        $childId = (int) $business->category_child_id;

        $scoped = $childId > 0
            ? DB::table('project_template_children')->where('child_id', $childId)->pluck('template_id')->all()
            : [];
        $hasScope = DB::table('project_template_children')->select('template_id')->distinct()->pluck('template_id')->all();

        return ProjectTemplate::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereIn('id', $scoped)->orWhereNotIn('id', $hasScope))
            ->with('stages')
            ->orderBy('sort_order')->orderBy('id')
            ->get();
    }

    /** Is this template offered to this business? */
    public function offeredTo(ProjectTemplate $template, User $business): bool
    {
        return $this->forBusiness($business)->contains('id', $template->id);
    }

    /**
     * Write the template's stages into the project, one after the other from [$start]: each stage starts the day after the
     * previous one ends and depends on it. Sets the project's start and due dates when it has none.
     */
    public function apply(Project $project, ProjectTemplate $template, ?Carbon $start = null): void
    {
        $day = ($start ?? ($project->starts_on ? Carbon::parse($project->starts_on) : Carbon::today()))->copy()->startOfDay();
        $previous = null;
        $order = 0;
        $end = $day->copy();

        DB::transaction(function () use ($project, $template, &$day, &$previous, &$order, &$end) {
            foreach ($template->stages as $stage) {
                $from = $day->copy();
                $to = $day->copy()->addDays(max(1, (int) $stage->duration_days) - 1);

                $task = $project->tasks()->create([
                    'title' => $stage->title(),
                    'status' => ProjectTask::STATUS_PENDING,
                    'starts_on' => $from->toDateString(),
                    'ends_on' => $to->toDateString(),
                    'progress' => 0,
                    'requires_photo' => (bool) $stage->requires_photo,
                    'requires_approval' => (bool) $stage->requires_approval,
                    'sort_order' => ++$order,
                ]);

                if ($previous) {
                    $this->projects->setDependencies($task, [(int) $previous->id]);
                }

                $previous = $task;
                $end = $to->copy();
                $day = $to->copy()->addDay();
            }

            $dates = [];
            if (! $project->starts_on) {
                $dates['starts_on'] = $day->copy()->subDays($template->stages->sum('duration_days'))->toDateString();
            }
            if (! $project->due_on && $template->stages->isNotEmpty()) {
                $dates['due_on'] = $end->toDateString();
            }
            if ($dates !== []) {
                $project->update($dates);
            }
        });

        $this->projects->recomputeProgress($project->fresh());
    }
}
