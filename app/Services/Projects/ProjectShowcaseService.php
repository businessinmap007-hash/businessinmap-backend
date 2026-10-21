<?php

namespace App\Services\Projects;

use App\Models\FeedPost;
use App\Models\Image;
use App\Models\Project;
use App\Models\ProjectShowcase;
use App\Models\ProjectTask;
use App\Models\ShowcaseApproval;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «نتائج موثَّقة»: a business shows a project's progress as a post — only after the customer agrees to it, and only with what he
 * confirmed. The post itself holds a title and a note; the progress is read live at every view so it grows as the customer
 * approves more stages, and it carries no name, address, flat or floor.
 *
 * What counts as confirmed: a work that asks for the customer's approval counts only once he approved it; a work that doesn't ask
 * counts as the business recorded it. A completed work still waiting for (or refused by) the customer counts as nothing.
 */
final class ProjectShowcaseService
{
    public function __construct(
        private readonly ProjectService $projects,
        private readonly ProjectAgreementService $agreements,
        private readonly NotificationDispatcherService $notifications
    ) {}

    public function forProject(Project $project): ?ProjectShowcase
    {
        return ProjectShowcase::query()->where('project_id', $project->id)->first();
    }

    /** The business asks the customer to let the project be shown. */
    public function request(Project $project, User $business, string $publicTitle, ?string $note, bool $showPhotos, string $mode = 'once'): ProjectShowcase
    {
        $customerId = $this->projects->contractedCustomerId($project);

        if (! $customerId) {
            throw ValidationException::withMessages(['project' => __('اربط المشروع بطلب أو حجز عميل أولًا.')]);
        }

        if (! $this->agreements->isAgreed($project)) {
            throw ValidationException::withMessages(['project' => __('لا يُعرض المشروع قبل اتفاق العميل على الخطة.')]);
        }

        $showcase = $this->forProject($project);

        if ($showcase && in_array($showcase->status, [ProjectShowcase::REQUESTED, ProjectShowcase::APPROVED], true)) {
            throw ValidationException::withMessages(['project' => $showcase->isLive() ? __('المشروع منشور بالفعل.') : __('طلب النشر ينتظر رد العميل.')]);
        }

        $showcase = ProjectShowcase::query()->updateOrCreate(['project_id' => $project->id], [
            'business_id' => (int) $project->business_id,
            'public_title' => mb_substr(trim($publicTitle), 0, 191),
            'note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null,
            'show_photos' => $showPhotos,
            'consent_mode' => $mode === 'each' ? 'each' : 'once',
            'status' => ProjectShowcase::REQUESTED,
            'requested_at' => now(),
            'decided_at' => null,
            'decision_note' => null,
            'decided_by' => null,
        ]);
        ShowcaseApproval::query()->where('kind', ShowcaseApproval::PROJECT_STAGE)->where('showcase_id', $showcase->id)->delete();

        $this->tell(
            $customerId, $project,
            'طلب نشر مشروعك', 'A request to show your project',
            'يطلب النشاط عرض تقدّم المشروع «' . $project->title . '» كمنشور دون اسمك ولا عنوانك. لا يُنشر شيء قبل موافقتك.',
            'The business asks to show the progress of "' . $project->title . '" as a post without your name or address. Nothing is published before you agree.'
        );

        return $showcase;
    }

    /** The customer's answer. Approving makes (or switches back on) the post. */
    public function decide(Project $project, User $customer, bool $approve, ?string $note = null, ?string $mode = null): ProjectShowcase
    {
        $this->assertCustomer($project, $customer);

        $showcase = $this->forProject($project);

        if (! $showcase || $showcase->status !== ProjectShowcase::REQUESTED) {
            throw ValidationException::withMessages(['project' => __('لا يوجد طلب نشر ينتظر ردك.')]);
        }

        DB::transaction(function () use ($showcase, $customer, $approve, $note, $project, $mode) {
            // he may only make it STRICTER than asked: approve once-for-all as «stage by stage»
            if ($approve && $mode === 'each') {
                $showcase->consent_mode = 'each';
            }
            $showcase->forceFill([
                'status' => $approve ? ProjectShowcase::APPROVED : ProjectShowcase::DECLINED,
                'decided_at' => now(),
                'decided_by' => (int) $customer->id,
                'decision_note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null,
            ])->save();

            if ($approve) {
                $this->publish($showcase, $project);
            }
        });

        $this->tell(
            (int) $project->business_id, $project,
            $approve ? 'وافق العميل على نشر المشروع' : 'رفض العميل نشر المشروع', $approve ? 'The customer agreed to show the project' : 'The customer declined to show the project',
            ($approve ? 'نُشر المشروع «' : 'لم يوافق العميل على عرض «') . $project->title . ($approve ? '» ويتحدّث تقدّمه كلما اعتمد العميل مرحلة.' : '».'),
            $approve ? '"' . $project->title . '" is now a post and its progress grows as the customer approves stages.' : 'The customer did not agree to show "' . $project->title . '".'
        );

        return $showcase->fresh();
    }

    /**
     * `each` mode: the customer answers for ONE main stage that is done — «انشر هذه المرحلة؟». Until he approves it the stage and
     * its works are not in the post, and its share counts as nothing in the percentage.
     */
    public function decideStage(Project $project, User $customer, int $taskId, bool $approve): array
    {
        $this->assertCustomer($project, $customer);
        $showcase = $this->forProject($project);

        if (! $showcase || ! $showcase->isLive() || $showcase->consent_mode !== 'each') {
            throw ValidationException::withMessages(['project' => __('هذا المنشور لا يطلب موافقة على كل مرحلة.')]);
        }

        $unit = collect($this->units($project, $showcase))->firstWhere('key', (string) $taskId);

        if (! $unit) {
            throw ValidationException::withMessages(['project' => __('هذه المرحلة لا تنتظر موافقتك على النشر.')]);
        }

        ShowcaseApproval::decide(ShowcaseApproval::PROJECT_STAGE, (int) $showcase->id, (string) $taskId, $approve);

        return $this->block($project);
    }

    /**
     * Stages the customer is asked about in `each` mode: the done main stages, each pending / approved / declined.
     *
     * @return array<int,array{key:string,title:string,status:string}>
     */
    public function units(Project $project, ProjectShowcase $showcase): array
    {
        if ($showcase->consent_mode !== 'each') {
            return [];
        }

        $tasks = $project->tasks()->get();
        $byParent = $tasks->groupBy(fn (ProjectTask $t) => (int) ($t->parent_id ?? 0));
        $answers = ShowcaseApproval::statuses(ShowcaseApproval::PROJECT_STAGE, (int) $showcase->id);
        $units = [];

        foreach ($byParent->get(0, collect()) as $root) {
            $leaves = $this->leavesUnder($root, $byParent);

            if ($leaves->every(fn (ProjectTask $l) => $l->status === ProjectTask::STATUS_DONE)) {
                $units[] = ['key' => (string) $root->id, 'title' => (string) $root->title, 'status' => $answers[(string) $root->id] ?? ShowcaseApproval::PENDING];
            }
        }

        return $units;
    }

    /** Either side takes it down: the post goes off and stays off until the business asks again and the customer agrees again. */
    public function withdraw(Project $project, User $actor): ProjectShowcase
    {
        $isBusiness = (int) $project->business_id === (int) $actor->id;

        if (! $isBusiness) {
            $this->assertCustomer($project, $actor);
        }

        $showcase = $this->forProject($project);

        if (! $showcase || ! in_array($showcase->status, [ProjectShowcase::REQUESTED, ProjectShowcase::APPROVED], true)) {
            throw ValidationException::withMessages(['project' => __('لا يوجد منشور لسحبه.')]);
        }

        DB::transaction(function () use ($showcase, $isBusiness) {
            $showcase->forceFill(['status' => ProjectShowcase::WITHDRAWN, 'decided_at' => now()])->save();
            $showcase->post_id && FeedPost::query()->whereKey($showcase->post_id)->update(['is_active' => false]);
        });

        $this->tell(
            $isBusiness ? ($this->projects->contractedCustomerId($project) ?? 0) : (int) $project->business_id, $project,
            'سُحب عرض المشروع', 'The project is no longer shown',
            'أُوقف عرض المشروع «' . $project->title . '» كمنشور.',
            'The post showing "' . $project->title . '" was taken down.'
        );

        return $showcase->fresh();
    }

    private function publish(ProjectShowcase $showcase, Project $project): void
    {
        $attrs = [
            'user_id' => (int) $project->business_id,
            'is_active' => true,
            'share_count' => 0,
            'title' => $showcase->public_title,
            'body' => (string) ($showcase->note ?? ''),
            'subject_type' => ProjectShowcase::POST_SUBJECT,
            'subject_id' => (int) $showcase->id,
        ];

        $post = $showcase->post_id ? FeedPost::query()->find($showcase->post_id) : null;

        if ($post) {
            $post->forceFill($attrs)->save();
        } else {
            $post = FeedPost::create($attrs);
            $showcase->forceFill(['post_id' => (int) $post->id])->save();
        }
    }

    /**
     * The anonymous, live face of an approved showcase: the confirmed percentage, the main stages with their confirmed share and the
     * date of the customer's latest approval inside each, and — when the business asked and the customer agreed — the camera photos
     * of the works he approved. Nothing here names a person or a place.
     *
     * @return array<string,mixed>|null null when the showcase is not live
     */
    public function payload(ProjectShowcase $showcase): ?array
    {
        if (! $showcase->isLive()) {
            return null;
        }

        $project = Project::query()->find($showcase->project_id);

        if (! $project) {
            return null;
        }

        $tasks = $project->tasks()->with($showcase->show_photos ? ['photos'] : [])->get();
        $byParent = $tasks->groupBy(fn (ProjectTask $t) => (int) ($t->parent_id ?? 0));

        $stages = [];
        $allLeaves = collect();
        $shownLeaves = collect();
        $answers = $showcase->consent_mode === 'each' ? ShowcaseApproval::statuses(ShowcaseApproval::PROJECT_STAGE, (int) $showcase->id) : null;

        foreach ($byParent->get(0, collect()) as $root) {
            $leaves = $this->leavesUnder($root, $byParent);
            $allLeaves = $allLeaves->merge($leaves);

            // stage by stage: a stage the customer did not approve is neither listed nor counted
            if ($answers !== null && ($answers[(string) $root->id] ?? null) !== ShowcaseApproval::APPROVED) {
                continue;
            }

            $shownLeaves = $shownLeaves->merge($leaves);
            $stages[] = [
                'title' => (string) $root->title,
                'progress' => $this->confirmed($leaves),
                'works' => $leaves->count(),
                'works_confirmed' => $leaves->filter(fn (ProjectTask $l) => $this->isConfirmedDone($l))->count(),
                'confirmed_on' => optional($leaves->filter(fn (ProjectTask $l) => $this->isConfirmedDone($l) && $l->approval_at)->max('approval_at'))->toDateString(),
            ];
        }

        $photos = [];

        if ($showcase->show_photos) {
            foreach ($shownLeaves->filter(fn (ProjectTask $l) => $this->isConfirmedDone($l) && $l->requires_approval) as $leaf) {
                foreach ($leaf->photos as $img) {
                    /** @var Image $img */
                    if ($img->isCamera()) {
                        $photos[] = ['url' => asset($img->image), 'stage' => (string) $leaf->title, 'on' => optional($leaf->approval_at)->toDateString()];
                    }
                }
            }
        }

        return [
            'id' => (int) $showcase->id,
            'progress' => $this->confirmed($allLeaves, $shownLeaves),
            'stages' => $stages,
            'photos' => array_slice($photos, 0, 8),
            'started_on' => optional($project->starts_on)->toDateString(),
            'updated_on' => optional($shownLeaves->filter(fn (ProjectTask $l) => $l->approval_at)->max('approval_at'))->toDateString(),
            'verified' => true,
        ];
    }

    /** The owner/customer's view of the request itself (status and what was asked), for the screens that answer it. */
    public function block(Project $project): ?array
    {
        $s = $this->forProject($project);

        return $s ? [
            'id' => (int) $s->id,
            'status' => $s->status,
            'public_title' => $s->public_title,
            'note' => $s->note,
            'show_photos' => (bool) $s->show_photos,
            'consent_mode' => $s->consent_mode ?? 'once',
            'units' => $s->isLive() ? $this->units($project, $s) : [],
            'post_id' => $s->post_id ? (int) $s->post_id : null,
            'requested_at' => optional($s->requested_at)->toIso8601String(),
            'decided_at' => optional($s->decided_at)->toIso8601String(),
            'decision_note' => $s->decision_note,
        ] : null;
    }

    /* ---------------------------------------------------------------- helpers */

    /** @return \Illuminate\Support\Collection<int,ProjectTask> */
    private function leavesUnder(ProjectTask $node, $byParent)
    {
        $children = $byParent->get((int) $node->id);

        if (! $children || $children->isEmpty()) {
            return collect([$node]);
        }

        return $children->flatMap(fn (ProjectTask $c) => $this->leavesUnder($c, $byParent))->values();
    }

    /** A completed work counts only if it needed no approval or the customer gave it. */
    private function isConfirmedDone(ProjectTask $t): bool
    {
        return $t->status === ProjectTask::STATUS_DONE
            && (! $t->requires_approval || $t->approval_status === ProjectTask::APPROVAL_APPROVED);
    }

    /** Weighted percentage of the works, counting a done-but-unconfirmed work as nothing. */
    private function confirmed($leaves, $counted = null): int
    {
        $total = (int) $leaves->sum(fn (ProjectTask $l) => max(1, (int) $l->weight));

        if ($total === 0) {
            return 0;
        }

        $got = ($counted ?? $leaves)->sum(function (ProjectTask $l) {
            $w = max(1, (int) $l->weight);

            if ($l->status === ProjectTask::STATUS_DONE) {
                return $this->isConfirmedDone($l) ? $w * 100 : 0;
            }

            return $w * min(99, max(0, (int) $l->progress));
        });

        return (int) floor($got / $total);
    }

    private function assertCustomer(Project $project, User $customer): void
    {
        if ($this->projects->contractedCustomerId($project) !== (int) $customer->id) {
            throw ValidationException::withMessages(['project' => __('هذا المشروع ليس مشروعك.')]);
        }
    }

    private function tell(int $userId, Project $project, string $titleAr, string $titleEn, string $bodyAr, string $bodyEn): void
    {
        if ($userId <= 0) {
            return;
        }

        try {
            $this->notifications->dispatch('project_stage_completed', $userId, [
                'title_ar' => $titleAr, 'title_en' => $titleEn, 'body_ar' => $bodyAr, 'body_en' => $bodyEn,
                'notifiable_type' => Project::class, 'notifiable_id' => (int) $project->id,
                'source_type' => Project::class, 'source_id' => (int) $project->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
