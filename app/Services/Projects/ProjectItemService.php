<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\ProjectChangeRequest;
use App\Models\ProjectTask;
use App\Models\ProjectTaskItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The measurable quantities of a stage (owner, 2026-10-20: «عدد الأعمدة، كميات الخرسانة، الطوب»). The engineer edits them at
 * will — with two rules: every change is written to a log the customer can read, and once the plan is AGREED a change to what
 * was PLANNED (adding an item, changing its amount, removing it) is a change of scope that waits for the customer. What has
 * been DONE is reported freely.
 *
 * A stage that has items measures its own progress by them: the mean of done ÷ planned.
 */
final class ProjectItemService
{
    public function __construct(private readonly ProjectService $projects)
    {
    }

    /**
     * Add an item. Before the agreement it is simply added; after it, it is a scope request.
     *
     * @param  array{name:string,unit?:?string,planned:float|int|string,notes?:?string}  $data
     * @return array{item:?ProjectTaskItem,request:?ProjectChangeRequest}
     */
    public function add(ProjectTask $task, array $data, User $by, ?string $reason = null): array
    {
        $this->assertWorkItem($task);
        $planned = $this->amount($data['planned'] ?? 0);

        if ($this->agreed($task)) {
            $request = app(ProjectAgreementService::class)->requestScope($this->project($task), $by, [
                'op' => 'add', 'task_id' => (int) $task->id, 'name' => (string) $data['name'], 'unit' => $data['unit'] ?? null, 'planned' => $planned, 'notes' => $data['notes'] ?? null,
            ], $reason);

            return ['item' => null, 'request' => $request];
        }

        $item = $this->create($task, $data, $planned, $by, null);
        $this->syncProgress($task);

        return ['item' => $item, 'request' => null];
    }

    /**
     * Change an item. `done`, `name`, `unit` and `notes` apply at once; a different `planned` after the agreement is a request.
     *
     * @param  array<string,mixed>  $changes
     * @return array{item:ProjectTaskItem,request:?ProjectChangeRequest}
     */
    public function update(ProjectTaskItem $item, array $changes, User $by, ?string $reason = null): array
    {
        $task = ProjectTask::query()->findOrFail($item->task_id);
        $request = null;

        if (array_key_exists('planned', $changes)) {
            $planned = $this->amount($changes['planned']);

            if (abs($planned - (float) $item->planned) > 0.0001) {
                if ($this->agreed($task)) {
                    $request = app(ProjectAgreementService::class)->requestScope($this->project($task), $by, [
                        'op' => 'planned', 'task_id' => (int) $task->id, 'item_id' => (int) $item->id, 'from' => (float) $item->planned, 'to' => $planned,
                    ], $reason);
                } else {
                    $this->set($item, 'planned', $planned, $by, $reason, null);
                }
            }
        }

        foreach (['name', 'unit', 'notes'] as $field) {
            if (array_key_exists($field, $changes) && (string) $changes[$field] !== (string) $item->{$field}) {
                $this->set($item, $field, $changes[$field], $by, $reason, null);
            }
        }

        if (array_key_exists('done', $changes)) {
            $done = $this->amount($changes['done']);

            if (abs($done - (float) $item->done) > 0.0001) {
                $this->projects->assertStartAllowed($task);
                $this->set($item, 'done', $done, $by, $reason, null);
            }
        }

        $this->syncProgress($task);

        return ['item' => $item->refresh(), 'request' => $request];
    }

    /** @return array{removed:bool,request:?ProjectChangeRequest} */
    public function remove(ProjectTaskItem $item, User $by, ?string $reason = null): array
    {
        $task = ProjectTask::query()->findOrFail($item->task_id);

        if ($this->agreed($task)) {
            $request = app(ProjectAgreementService::class)->requestScope($this->project($task), $by, [
                'op' => 'delete', 'task_id' => (int) $task->id, 'item_id' => (int) $item->id, 'name' => (string) $item->name,
            ], $reason);

            return ['removed' => false, 'request' => $request];
        }

        $this->destroy($item, $by, $reason, null);
        $this->syncProgress($task);

        return ['removed' => true, 'request' => null];
    }

    /** The customer approved a scope request: do what it says (logged against the request). */
    public function applyScopeRequest(ProjectChangeRequest $request, User $customer): void
    {
        $p = $request->payload;
        $by = User::query()->find($request->requested_by) ?? $customer;
        $task = ProjectTask::query()->find($p['task_id'] ?? 0);

        if (! $task) {
            return;
        }

        match ($p['op'] ?? '') {
            'add' => $this->create($task, ['name' => $p['name'], 'unit' => $p['unit'] ?? null, 'notes' => $p['notes'] ?? null], (float) $p['planned'], $by, $request->id),
            'planned' => ($item = ProjectTaskItem::query()->find($p['item_id'] ?? 0)) ? $this->set($item, 'planned', (float) $p['to'], $by, $request->note, $request->id) : null,
            'delete' => ($item = ProjectTaskItem::query()->find($p['item_id'] ?? 0)) ? $this->destroy($item, $by, $request->note, $request->id) : null,
            default => null,
        };

        $this->syncProgress($task);
    }

    /** Recompute a stage's progress from its items (none = leave it as it is). */
    public function syncProgress(ProjectTask $task): void
    {
        $items = $task->items()->get();

        if ($items->isEmpty() || $task->children()->exists()) {
            return;
        }

        $computed = (int) round($items->avg(fn (ProjectTaskItem $i) => $i->ratio()) * 100);

        // 100% closes the stage and needs its camera photo; without it the stage waits at 99
        if ($computed >= 100 && $task->requires_photo && ! $task->photos()->camera()->exists()) {
            $computed = 99;
        }

        if ($computed !== (int) $task->progress) {
            $this->projects->updateTaskProgress($task->refresh(), $computed, null);
        }
    }

    /** The recent changes of a project's quantities, newest first, for the customer and the business to read. */
    public function log(Project $project, int $limit = 50): array
    {
        return DB::table('project_item_logs as l')
            ->leftJoin('project_tasks as t', 't.id', '=', 'l.task_id')
            ->leftJoin('users as u', 'u.id', '=', 'l.user_id')
            ->where('l.project_id', $project->id)
            ->orderByDesc('l.id')->limit($limit)
            ->get(['l.id', 'l.task_id', 't.title as task', 'l.item_id', 'l.field', 'l.old_value', 'l.new_value', 'l.reason', 'l.change_request_id', 'l.created_at', 'u.name as by'])
            ->map(fn ($r) => [
                'id' => (int) $r->id, 'task_id' => (int) $r->task_id, 'task' => $r->task, 'item_id' => $r->item_id ? (int) $r->item_id : null,
                'field' => $r->field, 'old' => $r->old_value, 'new' => $r->new_value, 'reason' => $r->reason,
                'via_request' => $r->change_request_id ? (int) $r->change_request_id : null, 'by' => $r->by, 'at' => $r->created_at,
            ])->all();
    }

    /* ------------------------------------------------------------------ internals */

    private function create(ProjectTask $task, array $data, float $planned, User $by, ?int $requestId): ProjectTaskItem
    {
        $item = $task->items()->create([
            'name' => mb_substr(trim((string) $data['name']), 0, 120), 'unit' => isset($data['unit']) ? mb_substr(trim((string) $data['unit']), 0, 30) : null,
            'planned' => $planned, 'done' => 0, 'notes' => isset($data['notes']) ? mb_substr(trim((string) $data['notes']), 0, 255) : null,
            'sort_order' => (int) $task->items()->max('sort_order') + 1,
        ]);

        $this->record($task, $item, $by, 'created', null, $item->name . ' = ' . $this->fmt($planned), null, $requestId);

        return $item;
    }

    private function set(ProjectTaskItem $item, string $field, mixed $value, User $by, ?string $reason, ?int $requestId): void
    {
        $old = $item->{$field};
        $item->forceFill([$field => $value])->save();
        $this->record(ProjectTask::query()->findOrFail($item->task_id), $item, $by, $field, is_float($old) ? $this->fmt($old) : (string) $old, is_float($value) ? $this->fmt($value) : (string) $value, $reason, $requestId);
    }

    private function destroy(ProjectTaskItem $item, User $by, ?string $reason, ?int $requestId): void
    {
        $task = ProjectTask::query()->findOrFail($item->task_id);
        $this->record($task, $item, $by, 'deleted', $item->name, null, $reason, $requestId);
        $item->delete();
    }

    private function record(ProjectTask $task, ProjectTaskItem $item, User $by, string $field, ?string $old, ?string $new, ?string $reason, ?int $requestId): void
    {
        DB::table('project_item_logs')->insert([
            'project_id' => (int) $task->project_id, 'task_id' => (int) $task->id, 'item_id' => $item->id, 'user_id' => (int) $by->id,
            'field' => $field, 'old_value' => $old !== null ? mb_substr($old, 0, 120) : null, 'new_value' => $new !== null ? mb_substr($new, 0, 120) : null,
            'reason' => $reason !== null ? mb_substr(trim($reason), 0, 255) : null, 'change_request_id' => $requestId, 'created_at' => now(),
        ]);
    }

    private function agreed(ProjectTask $task): bool
    {
        return app(ProjectAgreementService::class)->isAgreed($this->project($task));
    }

    private function project(ProjectTask $task): Project
    {
        return Project::query()->findOrFail($task->project_id);
    }

    private function assertWorkItem(ProjectTask $task): void
    {
        if ($task->children()->exists()) {
            throw ValidationException::withMessages(['item' => __('الكميات تُسجَّل على الأعمال لا على المراحل الرئيسية.')]);
        }
    }

    private function amount(mixed $v): float
    {
        if (! is_numeric($v) || (float) $v < 0 || (float) $v > 1000000000) {
            throw ValidationException::withMessages(['quantity' => __('الكمية غير صالحة.')]);
        }

        return round((float) $v, 2);
    }

    private function fmt(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }
}
