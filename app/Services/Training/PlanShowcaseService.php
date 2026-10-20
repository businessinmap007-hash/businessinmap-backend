<?php

namespace App\Services\Training;

use App\Models\FeedPost;
use App\Models\PlanShowcase;
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

    public function request(TrainingPlan $plan, string $publicTitle, ?string $note): PlanShowcase
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
            'status' => PlanShowcase::REQUESTED,
            'requested_at' => now(),
            'decided_at' => null, 'decision_note' => null, 'decided_by' => null,
        ]);

        $this->tell((int) $plan->client_id, $plan, 'طلب نشر نتائج خطتك', 'A request to show your plan results',
            'يطلب المدرب عرض نتائج الخطة «' . $plan->title . '» كمنشور دون اسمك، وتتحدّث نتائجها شهرًا بعد شهر بموافقتك هذه وحدها. لا يُنشر شيء قبل موافقتك.',
            'The trainer asks to show the results of "' . $plan->title . '" as a post without your name; they update month by month under this one approval. Nothing is published before you agree.');

        return $showcase;
    }

    public function decide(TrainingPlan $plan, User $client, bool $approve, ?string $note = null): PlanShowcase
    {
        $this->assertClient($plan, $client);
        $showcase = $this->forPlan($plan);

        if (! $showcase || $showcase->status !== PlanShowcase::REQUESTED) {
            throw ValidationException::withMessages(['plan' => __('لا يوجد طلب نشر ينتظر ردك.')]);
        }

        DB::transaction(function () use ($showcase, $plan, $client, $approve, $note) {
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
