<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\ProjectAgreement;
use App\Models\ProjectChangeRequest;
use App\Models\ProjectTask;
use App\Models\User;
use App\Services\Fees\PlatformEventFeeService;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The plan two people agreed to (owner, 2026-10-20). The business proposes the plan with a delivery date and a grace; the
 * customer agrees (or not) — both are recorded, with a hash of what was agreed, so a dispute can rely on it. The platform's
 * fee for the agreement is taken from BOTH sides at that moment, and only if both can pay it.
 *
 * Afterwards nothing is edited in place: more time and a change of scope are CHANGE REQUESTS the customer approves, and each
 * approval writes a new, equally immutable, version.
 */
final class ProjectAgreementService
{
    public function __construct(
        private readonly NotificationDispatcherService $notifications,
        private readonly PlatformEventFeeService $fees,
        private readonly ProjectService $projects,
        private readonly ProjectItemService $items
    ) {}

    /** The version in force: the latest agreed one. */
    public function current(Project $project): ?ProjectAgreement
    {
        return ProjectAgreement::query()->where('project_id', $project->id)->where('status', ProjectAgreement::AGREED)->orderByDesc('version')->first();
    }

    public function isAgreed(Project $project): bool
    {
        return $this->current($project) !== null;
    }

    /** The version waiting for the customer, if any. */
    public function pending(Project $project): ?ProjectAgreement
    {
        return ProjectAgreement::query()->where('project_id', $project->id)->where('status', ProjectAgreement::PROPOSED)->orderByDesc('version')->first();
    }

    /* ------------------------------------------------------------------ the first agreement */

    /** The business offers the plan, with its delivery date and grace days. */
    public function propose(Project $project, User $business, Carbon $dueOn, int $graceDays): ProjectAgreement
    {
        $customerId = $this->projects->contractedCustomerId($project);

        if (! $customerId) {
            throw ValidationException::withMessages(['agreement' => __('اربط المشروع بطلب أو حجز لعميل أولًا.')]);
        }

        if ($this->isAgreed($project)) {
            throw ValidationException::withMessages(['agreement' => __('الخطة متفق عليها بالفعل؛ أي تعديل بطلب يوافق عليه العميل.')]);
        }

        if (! $project->tasks()->exists()) {
            throw ValidationException::withMessages(['agreement' => __('أضف مراحل المشروع قبل عرض الخطة على العميل.')]);
        }

        if ($dueOn->copy()->startOfDay()->lt(Carbon::today()) || $graceDays < 0 || $graceDays > 365) {
            throw ValidationException::withMessages(['agreement' => __('موعد التسليم أو مدة السماح غير صالحة.')]);
        }

        return DB::transaction(function () use ($project, $business, $dueOn, $graceDays, $customerId) {
            ProjectAgreement::query()->where('project_id', $project->id)->where('status', ProjectAgreement::PROPOSED)
                ->update(['status' => ProjectAgreement::SUPERSEDED]);

            $grace = $dueOn->copy()->addDays($graceDays);
            $snapshot = $this->snapshotOf($project);

            $agreement = ProjectAgreement::create([
                'project_id' => $project->id,
                'version' => (int) ProjectAgreement::query()->where('project_id', $project->id)->max('version') + 1,
                'status' => ProjectAgreement::PROPOSED,
                'due_on' => $dueOn->toDateString(),
                'grace_until' => $grace->toDateString(),
                'snapshot' => $snapshot,
                'content_hash' => $this->hash($snapshot, $dueOn, $grace),
                'proposed_by' => (int) $business->id,
                'business_agreed_at' => now(),
            ]);

            $this->tell($customerId, 'اتفاق على خطة مشروعك بانتظار موافقتك', 'A project plan is waiting for your agreement',
                'عرض عليك «' . $project->title . '» خطة بموعد تسليم ' . $dueOn->toDateString() . ' ومدة سماح حتى ' . $grace->toDateString() . '. راجعها ووافق عليها أو ارفضها.',
                'The plan of "' . $project->title . '" has a delivery date of ' . $dueOn->toDateString() . ' and a grace until ' . $grace->toDateString() . '. Review it and agree, or decline.',
                $project);

            return $agreement;
        });
    }

    /** The customer agrees: his consent is recorded, the plan becomes the project's, and both sides pay the agreement fee. */
    public function agree(Project $project, User $customer): ProjectAgreement
    {
        $this->assertCustomer($project, $customer);
        $agreement = $this->pending($project) ?? throw ValidationException::withMessages(['agreement' => __('لا توجد خطة بانتظار موافقتك.')]);

        return DB::transaction(function () use ($project, $customer, $agreement) {
            $parties = ['business' => (int) $project->business_id, 'client' => (int) $customer->id];
            $quote = $this->fees->quote(PlatformEventFeeService::PROJECT_PLAN_AGREED, $parties);
            $this->fees->assertCanPay($quote, (int) $customer->id);
            $this->fees->charge(PlatformEventFeeService::PROJECT_PLAN_AGREED, $parties, 0.0, 'project', (int) $project->id, (string) $agreement->version);

            ProjectAgreement::query()->where('project_id', $project->id)->where('status', ProjectAgreement::AGREED)->update(['status' => ProjectAgreement::SUPERSEDED]);
            $agreement->update(['status' => ProjectAgreement::AGREED, 'customer_id' => (int) $customer->id, 'customer_agreed_at' => now()]);

            $project->forceFill([
                'due_on' => $agreement->due_on->toDateString(),
                'grace_until' => $agreement->grace_until->toDateString(),
                'status' => in_array($project->status, [Project::STATUS_PLANNING], true) ? Project::STATUS_ACTIVE : $project->status,
            ])->save();

            $this->tell((int) $project->business_id, 'وافق العميل على خطة المشروع', 'The customer agreed to the plan',
                'وافق العميل على خطة «' . $project->title . '». يمكنك طلب بدء المرحلة الأولى.',
                'The customer agreed to the plan of "' . $project->title . '". You can ask to start the first stage.', $project);

            return $agreement->refresh();
        });
    }

    public function reject(Project $project, User $customer, string $note): void
    {
        $this->assertCustomer($project, $customer);
        $agreement = $this->pending($project) ?? throw ValidationException::withMessages(['agreement' => __('لا توجد خطة بانتظار موافقتك.')]);

        $agreement->update(['status' => ProjectAgreement::REJECTED, 'customer_id' => (int) $customer->id, 'customer_note' => mb_substr(trim($note), 0, 500)]);

        $this->tell((int) $project->business_id, 'رفض العميل خطة المشروع', 'The customer declined the plan',
            'رفض العميل خطة «' . $project->title . '». الملاحظة: ' . mb_substr(trim($note), 0, 200),
            'The customer declined the plan of "' . $project->title . '". Note: ' . mb_substr(trim($note), 0, 200), $project);
    }

    /* ------------------------------------------------------------------ change requests */

    /** More time: the business asks to move the end of the grace by [$extraDays]; the customer decides. */
    public function requestExtension(Project $project, User $business, int $extraDays, string $reason): ProjectChangeRequest
    {
        $current = $this->current($project) ?? throw ValidationException::withMessages(['extension' => __('لا توجد خطة متفق عليها لتمديدها.')]);

        if ($extraDays < 1 || $extraDays > 365) {
            throw ValidationException::withMessages(['extra_days' => __('مدة التمديد غير صالحة.')]);
        }

        if (ProjectChangeRequest::query()->where('project_id', $project->id)->where('type', ProjectChangeRequest::EXTENSION)->where('status', ProjectChangeRequest::PENDING)->exists()) {
            throw ValidationException::withMessages(['extension' => __('يوجد طلب تمديد بانتظار رد العميل.')]);
        }

        $until = $current->grace_until->copy()->addDays($extraDays);

        $request = ProjectChangeRequest::create([
            'project_id' => $project->id, 'type' => ProjectChangeRequest::EXTENSION,
            'payload' => ['extra_days' => $extraDays, 'grace_until' => $until->toDateString()],
            'note' => mb_substr(trim($reason), 0, 500), 'requested_by' => (int) $business->id,
        ]);

        $this->tellCustomer($project, 'طلب تمديد موعد المشروع', 'A request to extend the project',
            'يطلب النشاط تمديد سماح «' . $project->title . '» ' . $extraDays . ' يومًا حتى ' . $until->toDateString() . '. السبب: ' . mb_substr(trim($reason), 0, 200),
            'The business asks to extend the grace of "' . $project->title . '" by ' . $extraDays . ' days, to ' . $until->toDateString() . '.');

        return $request;
    }

    /** A change of scope raised by an edit of a quantity: the customer decides before anything changes. */
    public function requestScope(Project $project, User $by, array $payload, ?string $reason): ProjectChangeRequest
    {
        $request = ProjectChangeRequest::create([
            'project_id' => $project->id, 'type' => ProjectChangeRequest::SCOPE, 'payload' => $payload,
            'note' => $reason !== null ? mb_substr(trim($reason), 0, 500) : null, 'requested_by' => (int) $by->id,
        ]);

        $this->tellCustomer($project, 'طلب تعديل نطاق العمل', 'A request to change the scope of work',
            'يطلب النشاط تعديل كمية في «' . $project->title . '» وتحتاج موافقتك.',
            'The business asks to change a quantity in "' . $project->title . '" and needs your approval.');

        return $request;
    }

    /** The customer's answer; an approval is applied and written as a new agreement version. */
    public function decide(ProjectChangeRequest $request, User $customer, bool $approve, ?string $note = null): ProjectChangeRequest
    {
        $project = Project::query()->findOrFail($request->project_id);
        $this->assertCustomer($project, $customer);

        if ($request->status !== ProjectChangeRequest::PENDING) {
            throw ValidationException::withMessages(['request' => __('تم الرد على هذا الطلب من قبل.')]);
        }

        if (! $approve && ($note === null || trim($note) === '')) {
            throw ValidationException::withMessages(['rejection_note' => __('اكتب سبب الرفض.')]);
        }

        return DB::transaction(function () use ($request, $project, $customer, $approve, $note) {
            $request->update([
                'status' => $approve ? ProjectChangeRequest::APPROVED : ProjectChangeRequest::REJECTED,
                'decided_by' => (int) $customer->id, 'decided_at' => now(), 'decision_note' => $note !== null ? mb_substr(trim($note), 0, 500) : null,
            ]);

            if ($approve) {
                if ($request->type === ProjectChangeRequest::EXTENSION) {
                    $this->newVersion($project, $request, (string) $request->payload['grace_until']);
                } else {
                    $this->items->applyScopeRequest($request, $customer);
                    $this->newVersion($project, $request, null);
                }
            }

            $this->tell((int) $project->business_id,
                $approve ? 'وافق العميل على طلبك' : 'رفض العميل طلبك',
                $approve ? 'The customer approved your request' : 'The customer declined your request',
                ($approve ? 'وافق' : 'رفض') . ' العميل طلب ' . ($request->type === ProjectChangeRequest::EXTENSION ? 'التمديد' : 'تعديل النطاق') . ' في «' . $project->title . '».'
                    . (! $approve && $note ? ' السبب: ' . mb_substr(trim($note), 0, 200) : ''),
                'The customer ' . ($approve ? 'approved' : 'declined') . ' the request on "' . $project->title . '".', $project);

            return $request->refresh();
        });
    }

    /** A new immutable version after an approval: same plan unless the scope moved, the grace moved for an extension. */
    private function newVersion(Project $project, ProjectChangeRequest $request, ?string $graceUntil): void
    {
        $current = $this->current($project);

        if (! $current) {
            return;
        }

        $grace = $graceUntil ? Carbon::parse($graceUntil) : $current->grace_until->copy();
        $snapshot = $this->snapshotOf($project);

        ProjectAgreement::query()->where('project_id', $project->id)->where('status', ProjectAgreement::AGREED)->update(['status' => ProjectAgreement::SUPERSEDED]);

        ProjectAgreement::create([
            'project_id' => $project->id,
            'version' => (int) ProjectAgreement::query()->where('project_id', $project->id)->max('version') + 1,
            'status' => ProjectAgreement::AGREED,
            'due_on' => $current->due_on->toDateString(),
            'grace_until' => $grace->toDateString(),
            'snapshot' => $snapshot,
            'content_hash' => $this->hash($snapshot, $current->due_on, $grace),
            'proposed_by' => (int) $request->requested_by,
            'business_agreed_at' => $request->created_at,
            'customer_id' => (int) $request->decided_by,
            'customer_agreed_at' => $request->decided_at,
            'change_request_id' => (int) $request->id,
        ]);

        if ($graceUntil) {
            $project->forceFill(['grace_until' => $grace->toDateString()])->save();
        }
    }

    /* ------------------------------------------------------------------ reading */

    /**
     * What either side reads: the version in force (or the one waiting), every version, and the requests with their answers.
     *
     * @return array<string,mixed>
     */
    public function block(Project $project): array
    {
        $current = $this->current($project);
        $pending = $this->pending($project);
        $shown = $current ?? $pending;
        $fmt = fn (?ProjectAgreement $a) => $a ? [
            'version' => (int) $a->version, 'status' => (string) $a->status,
            'due_on' => $a->due_on->toDateString(), 'grace_until' => $a->grace_until->toDateString(),
            'business_agreed_at' => optional($a->business_agreed_at)->toIso8601String(),
            'customer_agreed_at' => optional($a->customer_agreed_at)->toIso8601String(),
            'customer_note' => $a->customer_note, 'content_hash' => (string) $a->content_hash,
            'change_request_id' => $a->change_request_id ? (int) $a->change_request_id : null,
        ] : null;

        return [
            'agreement' => $fmt($shown),
            'agreed' => $current !== null,
            'versions' => ProjectAgreement::query()->where('project_id', $project->id)->orderBy('version')->get()->map(fn ($a) => $fmt($a))->all(),
            'change_requests' => ProjectChangeRequest::query()->where('project_id', $project->id)->orderByDesc('id')->limit(30)->get()->map(fn (ProjectChangeRequest $r) => [
                'id' => (int) $r->id, 'type' => (string) $r->type, 'payload' => $r->payload, 'note' => $r->note, 'status' => (string) $r->status,
                'requested_at' => optional($r->created_at)->toIso8601String(), 'decided_at' => optional($r->decided_at)->toIso8601String(),
                'decision_note' => $r->decision_note,
            ])->all(),
        ];
    }

    /** The plan as the two sides see it right now — what a version freezes. */
    public function snapshotOf(Project $project): array
    {
        return $project->tasks()->with('items')->get()->map(fn (ProjectTask $t) => [
            'id' => (int) $t->id, 'parent_id' => $t->parent_id ? (int) $t->parent_id : null, 'title' => (string) $t->title, 'zone' => $t->zone,
            'weight' => (int) $t->weight, 'starts_on' => optional($t->starts_on)->toDateString(), 'ends_on' => optional($t->ends_on)->toDateString(),
            'items' => $t->items->map(fn ($i) => ['name' => (string) $i->name, 'unit' => $i->unit, 'planned' => (float) $i->planned])->all(),
        ])->values()->all();
    }

    private function hash(array $snapshot, Carbon $due, Carbon $grace): string
    {
        return hash('sha256', json_encode([$snapshot, $due->toDateString(), $grace->toDateString()], JSON_UNESCAPED_UNICODE));
    }

    private function assertCustomer(Project $project, User $customer): void
    {
        if ($this->projects->contractedCustomerId($project) !== (int) $customer->id) {
            throw ValidationException::withMessages(['project' => __('هذا المشروع ليس مشروعك.')]);
        }
    }

    private function tellCustomer(Project $project, string $titleAr, string $titleEn, string $bodyAr, string $bodyEn): void
    {
        $id = $this->projects->contractedCustomerId($project);
        $id && $this->tell($id, $titleAr, $titleEn, $bodyAr, $bodyEn, $project);
    }

    private function tell(int $userId, string $titleAr, string $titleEn, string $bodyAr, string $bodyEn, Project $project): void
    {
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
