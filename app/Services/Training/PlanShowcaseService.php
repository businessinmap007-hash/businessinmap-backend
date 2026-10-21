<?php

namespace App\Services\Training;

use App\Models\FeedPost;
use App\Models\PlanShowcase;
use App\Models\ShowcaseApproval;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «نتائج موثَّقة» for a training / nutrition plan: the trainer shows the plan's results as a post — anonymous, and no photos ever
 * (the training section carries none). The client's word is the answer to the request itself: ONE approval covers the whole
 * programme, so the post then updates month by month from the trainer's body reports without asking him again for each month.
 * He can take it down any time.
 *
 * The post holds a title and a note; the results are read live at every view.
 */
final class PlanShowcaseService
{
    public function __construct(private readonly NotificationDispatcherService $notifications) {}

    public function forPlan(TrainingPlan $plan): ?PlanShowcase
    {
        return PlanShowcase::query()->where('training_plan_id', $plan->id)->first();
    }

    /** training | nutrition | combined — what the plan actually contains; null for an empty plan. */
    public function kindOf(TrainingPlan $plan): ?string
    {
        $training = $plan->exercises()->exists();
        $nutrition = $plan->meals()->exists();

        return $training && $nutrition ? 'combined' : ($training ? 'training' : ($nutrition ? 'nutrition' : null));
    }

    public function request(TrainingPlan $plan, string $publicTitle, ?string $note, string $mode = 'once'): PlanShowcase
    {
        if ($this->kindOf($plan) === null) {
            throw ValidationException::withMessages(['plan' => __('أضف تمارين أو وجبات إلى الخطة أولًا.')]);
        }

        if (! in_array($plan->status, [TrainingPlan::STATUS_ACTIVE, TrainingPlan::STATUS_COMPLETED], true)) {
            throw ValidationException::withMessages(['plan' => __('لا تُعرض الخطة قبل أن يقبلها العميل.')]);
        }

        $existing = $this->forPlan($plan);

        if ($existing && in_array($existing->status, [PlanShowcase::REQUESTED, PlanShowcase::APPROVED], true)) {
            throw ValidationException::withMessages(['plan' => $existing->isLive() ? __('الخطة منشورة بالفعل.') : __('طلب النشر ينتظر رد العميل.')]);
        }

        $showcase = PlanShowcase::query()->updateOrCreate(['training_plan_id' => $plan->id], [
            'trainer_id' => (int) $plan->trainer_id,
            'public_title' => mb_substr(trim($publicTitle), 0, 191),
            'note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null,
            'consent_mode' => $mode === 'each' ? 'each' : 'once',
            'status' => PlanShowcase::REQUESTED,
            'requested_at' => now(),
            'decided_at' => null, 'decision_note' => null, 'decided_by' => null,
        ]);
        ShowcaseApproval::query()->where('kind', ShowcaseApproval::PLAN_MONTH)->where('showcase_id', $showcase->id)->delete();

        $this->tell((int) $plan->client_id, $plan, 'طلب نشر نتائج خطتك', 'A request to show your plan results',
            'يطلب المدرب عرض نتائج الخطة «' . $plan->title . '» كمنشور دون اسمك، وتتحدّث نتائجها شهرًا بعد شهر بموافقتك هذه وحدها. لا يُنشر شيء قبل موافقتك.',
            'The trainer asks to show the results of "' . $plan->title . '" as a post without your name; they update month by month under this one approval. Nothing is published before you agree.');

        return $showcase;
    }

    public function decide(TrainingPlan $plan, User $client, bool $approve, ?string $note = null, ?string $mode = null): PlanShowcase
    {
        $this->assertClient($plan, $client);
        $showcase = $this->forPlan($plan);

        if (! $showcase || $showcase->status !== PlanShowcase::REQUESTED) {
            throw ValidationException::withMessages(['plan' => __('لا يوجد طلب نشر ينتظر ردك.')]);
        }

        DB::transaction(function () use ($showcase, $plan, $client, $approve, $note, $mode) {
            // he may only make it STRICTER than asked: approve once-for-all as «month by month»
            if ($approve && $mode === 'each') {
                $showcase->consent_mode = 'each';
            }
            $showcase->forceFill([
                'status' => $approve ? PlanShowcase::APPROVED : PlanShowcase::DECLINED,
                'decided_at' => now(), 'decided_by' => (int) $client->id,
                'decision_note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null,
            ])->save();

            $approve && $this->publish($showcase, $plan);
        });

        $this->tell((int) $plan->trainer_id, $plan,
            $approve ? 'وافق العميل على نشر النتائج' : 'رفض العميل نشر النتائج', $approve ? 'The client agreed to show the results' : 'The client declined to show the results',
            ($approve ? 'نُشرت نتائج الخطة «' : 'لم يوافق العميل على عرض نتائج «') . $plan->title . ($approve ? '» وتتحدّث مع كل تقرير شهري تسجّله.' : '».'),
            $approve ? 'The results of "' . $plan->title . '" are a post now and update with every monthly report you record.' : 'The client did not agree to show "' . $plan->title . '".');

        return $showcase->fresh();
    }

    /** `each` mode: the client answers for ONE month — «انشر نتائج هذا الشهر؟». */
    public function decideMonth(TrainingPlan $plan, User $client, string $month, bool $approve): array
    {
        $this->assertClient($plan, $client);
        $showcase = $this->forPlan($plan);

        if (! $showcase || ! $showcase->isLive() || $showcase->consent_mode !== 'each') {
            throw ValidationException::withMessages(['plan' => __('هذا المنشور لا يطلب موافقة كل شهر.')]);
        }

        if (! collect($this->units($plan, $showcase))->contains('key', $month)) {
            throw ValidationException::withMessages(['plan' => __('هذا الشهر لا ينتظر موافقتك على النشر.')]);
        }

        ShowcaseApproval::decide(ShowcaseApproval::PLAN_MONTH, (int) $showcase->id, $month, $approve);

        return $this->block($plan);
    }

    /**
     * The renewal of a paid month opens that month's question when the plan asks month by month: the client is told and answers
     * inside the plan's own screen. A plan that asked once for all months needs nothing here.
     */
    public function monthRenewed(TrainingPlan $plan): void
    {
        $showcase = $this->forPlan($plan);

        if (! $showcase || ! $showcase->isLive() || $showcase->consent_mode !== 'each') {
            return;
        }

        $key = now()->format('Y-m');
        $row = ShowcaseApproval::query()->firstOrCreate(['kind' => ShowcaseApproval::PLAN_MONTH, 'showcase_id' => $showcase->id, 'unit_key' => $key], ['status' => ShowcaseApproval::PENDING]);

        if ($row->wasRecentlyCreated) {
            $this->tell((int) $plan->client_id, $plan, 'نتائج شهر جديد تنتظر موافقتك', 'A new month waits for your approval',
                'جُدِّدت الخطة «' . $plan->title . '». هل توافق على نشر نتائج هذا الشهر؟ تجيب من شاشة الخطة.',
                'The plan "' . $plan->title . '" was renewed. Do you agree to show this month\'s results? Answer from the plan screen.');
        }
    }

    /**
     * The months the client is asked about in `each` mode: every month with a report or with a renewal, pending / approved / declined.
     *
     * @return array<int,array{key:string,status:string}>
     */
    public function units(TrainingPlan $plan, PlanShowcase $showcase): array
    {
        if ($showcase->consent_mode !== 'each') {
            return [];
        }

        $answers = ShowcaseApproval::statuses(ShowcaseApproval::PLAN_MONTH, (int) $showcase->id);
        $months = $plan->bodyReports()->reorder('for_month')->get()->map(fn ($r) => optional($r->for_month)->format('Y-m'))->filter()->all();
        $keys = array_values(array_unique(array_merge($months, array_keys($answers))));
        sort($keys);

        return array_map(fn (string $k) => ['key' => $k, 'status' => $answers[$k] ?? ShowcaseApproval::PENDING], $keys);
    }

    /** Either side takes it down. */
    public function withdraw(TrainingPlan $plan, User $actor): PlanShowcase
    {
        $isTrainer = (int) $plan->trainer_id === (int) $actor->id;

        if (! $isTrainer) {
            $this->assertClient($plan, $actor);
        }

        $showcase = $this->forPlan($plan);

        if (! $showcase || ! in_array($showcase->status, [PlanShowcase::REQUESTED, PlanShowcase::APPROVED], true)) {
            throw ValidationException::withMessages(['plan' => __('لا يوجد منشور لسحبه.')]);
        }

        DB::transaction(function () use ($showcase) {
            $showcase->forceFill(['status' => PlanShowcase::WITHDRAWN, 'decided_at' => now()])->save();
            $showcase->post_id && FeedPost::query()->whereKey($showcase->post_id)->update(['is_active' => false]);
        });

        $this->tell($isTrainer ? (int) $plan->client_id : (int) $plan->trainer_id, $plan, 'سُحب عرض النتائج', 'The results are no longer shown',
            'أُوقف عرض نتائج الخطة «' . $plan->title . '» كمنشور.', 'The post showing the results of "' . $plan->title . '" was taken down.');

        return $showcase->fresh();
    }

    private function publish(PlanShowcase $showcase, TrainingPlan $plan): void
    {
        $attrs = [
            'user_id' => (int) $plan->trainer_id, 'is_active' => true, 'share_count' => 0,
            'title' => $showcase->public_title, 'body' => (string) ($showcase->note ?? ''),
            'subject_type' => PlanShowcase::POST_SUBJECT, 'subject_id' => (int) $showcase->id,
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
     * The anonymous, live results: the kind of plan, how long it has run, and how far each measure moved since the first monthly
     * reading — month by month and in total. Changes only, never absolute weights, and nothing that names a person.
     *
     * @return array<string,mixed>|null
     */
    public function payload(PlanShowcase $showcase): ?array
    {
        if (! $showcase->isLive()) {
            return null;
        }

        $plan = TrainingPlan::query()->find($showcase->training_plan_id);

        if (! $plan) {
            return null;
        }

        // one approval covers the whole programme: every month the trainer records is read live
        $reports = $plan->bodyReports()->reorder('for_month')->get();

        // month by month: only the months the client approved are read
        if ($showcase->consent_mode === 'each') {
            $answers = ShowcaseApproval::statuses(ShowcaseApproval::PLAN_MONTH, (int) $showcase->id);
            $reports = $reports->filter(fn ($r) => ($answers[optional($r->for_month)->format('Y-m')] ?? null) === ShowcaseApproval::APPROVED)->values();
        }

        $first = $reports->first();
        $measures = ['weight_kg', 'muscle_mass_kg', 'fat_percent', 'water_percent'];

        $months = [];
        foreach ($reports->skip(1) as $r) {
            $months[] = [
                'month' => optional($r->for_month)->format('Y-m'),
                'measured_on' => optional($r->measured_on)->toDateString(),
                'change' => array_intersect_key($r->deltaFrom($first), array_flip($measures)),
            ];
        }

        $end = $plan->ends_on && $plan->ends_on->isPast() ? $plan->ends_on : now();

        return [
            'id' => (int) $showcase->id,
            'kind' => $this->kindOf($plan),
            'weeks' => $plan->starts_on ? max(1, (int) ceil($plan->starts_on->diffInDays($end) / 7)) : null,
            'confirmed_months' => count($months),
            'months' => $months,
            'total_change' => $months ? end($months)['change'] : null,
            'updated_on' => optional($reports->max('measured_on'))->toDateString(),
            'verified' => true,
        ];
    }

    public function block(TrainingPlan $plan): ?array
    {
        $s = $this->forPlan($plan);

        return $s ? [
            'id' => (int) $s->id,
            'status' => $s->status,
            'public_title' => $s->public_title,
            'note' => $s->note,
            'post_id' => $s->post_id ? (int) $s->post_id : null,
            'requested_at' => optional($s->requested_at)->toIso8601String(),
            'decided_at' => optional($s->decided_at)->toIso8601String(),
            'decision_note' => $s->decision_note,
            'consent_mode' => $s->consent_mode ?? 'once',
            'units' => $s->isLive() ? $this->units($plan, $s) : [],
            'kind' => $this->kindOf($plan),
        ] : null;
    }

    private function assertClient(TrainingPlan $plan, User $client): void
    {
        if ((int) $plan->client_id !== (int) $client->id) {
            throw ValidationException::withMessages(['plan' => __('هذه الخطة ليست خطتك.')]);
        }
    }

    private function tell(int $userId, TrainingPlan $plan, string $titleAr, string $titleEn, string $bodyAr, string $bodyEn): void
    {
        if ($userId <= 0) {
            return;
        }

        try {
            $this->notifications->dispatch('project_stage_completed', $userId, [
                'title_ar' => $titleAr, 'title_en' => $titleEn, 'body_ar' => $bodyAr, 'body_en' => $bodyEn,
                'notifiable_type' => TrainingPlan::class, 'notifiable_id' => (int) $plan->id,
                'source_type' => TrainingPlan::class, 'source_id' => (int) $plan->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
