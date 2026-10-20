<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\ProjectTask;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Moving the schedule (owner, 2026-10-20: «ترحيل الخطة أو مرحلة مدة محددة — أسبوع أو شهر — أو اختيار موعد بدء جديد»).
 *
 * Only work that has NOT started moves (status pending); work already in hand stays where it is. A shift of the whole plan moves
 * every pending date, the project's start (if nothing began) and its delivery date + grace; a shift of one stage moves that
 * stage, its works and everything that starts on or after it, and moves the delivery date + grace by the same days.
 *
 * Before the plan is agreed this is applied at once; after it, it is a request the OTHER side approves
 * ({@see ProjectAgreementService::requestShift()}).
 */
final class ProjectScheduleService
{
    /**
     * How many days a request means: [$days] as given, or the distance from the stage's (or the plan's) current start to
     * [$newStart]. Only forward — postponing — is allowed once the plan is agreed.
     */
    public function daysFor(Project $project, ?ProjectTask $stage, ?int $days, ?Carbon $newStart): int
    {
        if ($newStart) {
            $from = $stage ? $this->startOf($project, $stage) : $this->planStart($project);

            if (! $from) {
                throw ValidationException::withMessages(['new_start' => __('لا يوجد موعد بدء حالي لحساب الترحيل منه.')]);
            }

            $days = (int) $from->copy()->startOfDay()->diffInDays($newStart->copy()->startOfDay(), false);
        }

        if ($days === null || $days < 1 || $days > 365) {
            throw ValidationException::withMessages(['days' => __('مدة الترحيل غير صالحة؛ اختر من يوم إلى سنة، أو موعد بدء لاحقًا.')]);
        }

        return $days;
    }

    /** Move pending work by [$days] (the payload is a change request's: scope, task_id?, days). */
    public function apply(Project $project, array $payload): void
    {
        $days = (int) $payload['days'];
        $tasks = $project->tasks()->get();
        $stage = ($payload['scope'] ?? 'plan') === 'stage' ? $tasks->firstWhere('id', (int) ($payload['task_id'] ?? 0)) : null;

        if (($payload['scope'] ?? 'plan') === 'stage' && ! $stage) {
            return;
        }

        $targets = $stage ? $this->stageTargets($tasks, $stage) : $tasks->where('status', ProjectTask::STATUS_PENDING);

        foreach ($targets as $task) {
            if ($task->starts_on || $task->ends_on) {
                $task->forceFill([
                    'starts_on' => $task->starts_on?->copy()->addDays($days)->toDateString(),
                    'ends_on' => $task->ends_on?->copy()->addDays($days)->toDateString(),
                ])->save();
            }
        }

        $this->moveProject($project, $days, ! $stage && ! $tasks->contains(fn (ProjectTask $t) => $t->progress > 0));
    }

    /** Before the agreement the plan can also be pulled earlier or aligned to a start date: every pending date moves by [$delta]. */
    public function alignTo(Project $project, Carbon $startsOn): void
    {
        $from = $this->planStart($project);

        if (! $from) {
            return;
        }

        $delta = (int) $from->copy()->startOfDay()->diffInDays($startsOn->copy()->startOfDay(), false);

        if ($delta === 0) {
            return;
        }

        foreach ($project->tasks()->get() as $task) {
            if ($task->status === ProjectTask::STATUS_PENDING && ($task->starts_on || $task->ends_on)) {
                $task->forceFill([
                    'starts_on' => $task->starts_on?->copy()->addDays($delta)->toDateString(),
                    'ends_on' => $task->ends_on?->copy()->addDays($delta)->toDateString(),
                ])->save();
            }
        }
    }

    /** The earliest date any task of the plan starts on. */
    public function planStart(Project $project): ?Carbon
    {
        $min = $project->tasks()->whereNotNull('starts_on')->min('starts_on');

        return $min ? Carbon::parse($min) : ($project->starts_on ? Carbon::parse($project->starts_on) : null);
    }

    private function startOf(Project $project, ProjectTask $stage): ?Carbon
    {
        if ($stage->starts_on) {
            return $stage->starts_on->copy();
        }

        $min = $project->tasks()->where('parent_id', $stage->id)->whereNotNull('starts_on')->min('starts_on');

        return $min ? Carbon::parse($min) : null;
    }

    /** The stage, everything inside it, and every pending task that starts on or after it. */
    private function stageTargets($tasks, ProjectTask $stage)
    {
        $inside = [$stage->id => true];
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($tasks as $t) {
                if ($t->parent_id && isset($inside[$t->parent_id]) && ! isset($inside[$t->id])) {
                    $inside[$t->id] = true;
                    $changed = true;
                }
            }
        }

        $from = $stage->starts_on ?? $tasks->where('parent_id', $stage->id)->whereNotNull('starts_on')->min('starts_on');
        $from = $from ? Carbon::parse($from) : null;

        return $tasks->filter(fn (ProjectTask $t) => $t->status === ProjectTask::STATUS_PENDING
            && (isset($inside[$t->id]) || ($from && $t->starts_on && $t->starts_on->gte($from))));
    }

    private function moveProject(Project $project, int $days, bool $moveStart): void
    {
        $changes = [];

        if ($moveStart && $project->starts_on) {
            $changes['starts_on'] = $project->starts_on->copy()->addDays($days)->toDateString();
        }
        if ($project->due_on) {
            $changes['due_on'] = $project->due_on->copy()->addDays($days)->toDateString();
        }
        if ($project->grace_until) {
            $changes['grace_until'] = $project->grace_until->copy()->addDays($days)->toDateString();
        }

        if ($changes !== []) {
            $project->forceFill($changes)->save();
        }
    }
}
