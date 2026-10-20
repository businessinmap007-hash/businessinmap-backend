<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Services\Fees\PlatformEventFeeService;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Starting a stage of an AGREED project needs the customer's explicit, recorded approval (owner, 2026-10-20) — silence is never
 * consent, so he cannot say he did not know the previous stage had ended or that he had no money for the next. The platform's
 * fee for the stage is taken from both sides at that moment. The unit of start is the TOP-LEVEL stage (a phase); the works
 * inside it start with it. The time a request waited for the customer is added to the project's `customer_wait_seconds`, so
 * the business is not rated late for it.
 */
final class ProjectStageStartService
{
    public function __construct(
        private readonly ProjectService $projects,
        private readonly ProjectAgreementService $agreements,
        private readonly PlatformEventFeeService $fees,
        private readonly NotificationDispatcherService $notifications
    ) {}

    /** The business asks to start a top-level stage. */
    public function request(ProjectTask $task, User $business): ProjectTask
    {
        $project = Project::query()->findOrFail($task->project_id);

        if ($task->parent_id) {
            throw ValidationException::withMessages(['stage' => __('طلب البدء يكون للمرحلة الرئيسية لا لأحد أعمالها.')]);
        }

        if (! $this->agreements->isAgreed($project)) {
            throw ValidationException::withMessages(['stage' => __('اتفقا على الخطة أولًا.')]);
        }

        if ($task->start_status === 'approved') {
            throw ValidationException::withMessages(['stage' => __('بدأت هذه المرحلة بالفعل.')]);
        }

        if ($task->start_status === 'requested') {
            throw ValidationException::withMessages(['stage' => __('طلب البدء بانتظار رد العميل.')]);
        }

        // the stage before it (what it depends on) must have ended
        if ($task->dependencies()->where('project_tasks.status', '!=', ProjectTask::STATUS_DONE)->exists()) {
            throw ValidationException::withMessages(['stage' => __('لا تبدأ هذه المرحلة قبل اكتمال المرحلة السابقة لها.')]);
        }

        $task->forceFill(['start_status' => 'requested', 'start_requested_at' => now(), 'start_decided_at' => null, 'start_note' => null])->save();

        $customerId = $this->projects->contractedCustomerId($project);
        $customerId && $this->tell($customerId, 'طلب بدء مرحلة جديدة', 'A request to start a new stage',
            'يطلب النشاط بدء المرحلة «' . $task->title . '» في «' . $project->title . '». عند موافقتك تُخصم رسوم المنصة من الطرفين.',
            'The business asks to start the stage "' . $task->title . '" of "' . $project->title . '". On your approval the platform fee is taken from both sides.', $project);

        return $task->refresh();
    }

    /** The customer's answer. Approval charges both sides and opens the stage. */
    public function decide(ProjectTask $task, User $customer, bool $approve, ?string $note = null): ProjectTask
    {
        $project = Project::query()->findOrFail($task->project_id);

        if ($this->projects->contractedCustomerId($project) !== (int) $customer->id) {
            throw ValidationException::withMessages(['stage' => __('هذا المشروع ليس مشروعك.')]);
        }

        if ($task->start_status !== 'requested') {
            throw ValidationException::withMessages(['stage' => __('لا يوجد طلب بدء بانتظار ردك.')]);
        }

        if (! $approve && ($note === null || trim($note) === '')) {
            throw ValidationException::withMessages(['rejection_note' => __('اكتب سبب الرفض.')]);
        }

        return DB::transaction(function () use ($task, $project, $customer, $approve, $note) {
            if ($approve) {
                $parties = ['business' => (int) $project->business_id, 'client' => (int) $customer->id];
                $this->fees->assertCanPay($this->fees->quote(PlatformEventFeeService::PROJECT_STAGE_STARTED, $parties), (int) $customer->id);
                $this->fees->charge(PlatformEventFeeService::PROJECT_STAGE_STARTED, $parties, 0.0, 'project_stage', (int) $task->id);

                $waited = max(0, (int) now()->diffInSeconds($task->start_requested_at ?? now()));
                $task->forceFill(['start_status' => 'approved', 'start_decided_at' => now(), 'start_note' => $note ? mb_substr(trim($note), 0, 500) : null])->save();
                $project->forceFill(['customer_wait_seconds' => (int) $project->customer_wait_seconds + $waited])->save();

                if ($task->status === ProjectTask::STATUS_PENDING) {
                    $task->forceFill(['status' => ProjectTask::STATUS_IN_PROGRESS])->save();
                }
            } else {
                $task->forceFill(['start_status' => 'declined', 'start_decided_at' => now(), 'start_note' => mb_substr(trim((string) $note), 0, 500)])->save();
            }

            $this->tell((int) $project->business_id,
                $approve ? 'وافق العميل على بدء المرحلة' : 'رفض العميل بدء المرحلة',
                $approve ? 'The customer approved starting the stage' : 'The customer declined starting the stage',
                ($approve ? 'يمكنك البدء في المرحلة «' : 'رفض العميل بدء المرحلة «') . $task->title . '» في «' . $project->title . '».' . (! $approve && $note ? ' السبب: ' . mb_substr(trim($note), 0, 200) : ''),
                'The stage "' . $task->title . '" of "' . $project->title . '": the customer ' . ($approve ? 'approved' : 'declined') . '.', $project);

            return $task->refresh();
        });
    }

    private function tell(int $userId, string $titleAr, string $titleEn, string $bodyAr, string $bodyEn, Project $project): void
    {
        try {
            $this->notifications->dispatch('project_stage_completed', $userId, [
                'title_ar' => $titleAr, 'title_en' => $titleEn, 'body_ar' => $bodyAr, 'body_en' => $bodyEn,
                'notifiable_type' => Project::class, 'notifiable_id' => (int) $project->id,
                'source_type' => ProjectTask::class, 'source_id' => 0,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
