<?php

namespace App\Services\Training;

use App\Models\BodyCompositionReport;
use App\Models\FeedPost;
use App\Models\PlanShowcase;
use App\Models\ShowcaseConsent;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «نتائج موثَّقة» for a training / nutrition plan: the trainer shows the plan's results as a post — anonymous, no photos ever (the
 * training section carries none), and only the monthly results the CLIENT confirmed. The client's word comes either with each
 * request or once and for all as a general consent (ShowcaseConsent), which never overrides a post he took down himself.
 *
 * The post holds a title and a note; the results are read live at every view, so they grow as the client confirms more months.
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

    /** Does the client's general consent cover this plan (both switches for a combined one)? */
    public function consentCovers(TrainingPlan $plan, ?string $kind): bool
    {
        if ($kind === null) {
            return false;
        }

        $c = ShowcaseConsent::forUser((int) $plan->client_id);

        return match ($kind) {
            'training' => $c['training'],
            'nutrition' => $c['nutrition'],
            default => $c['training'] && $c['nutrition'],
        };
    }

    public function request(TrainingPlan $plan, string $publicTitle, ?string $note): PlanShowcase
    {
        $kind = $this->kindOf($plan);

        if ($kind === null) {
            throw ValidationException::withMessages(['plan' => __('أضف تمارين أو وجبات إلى الخطة أولًا.')]);
        }

        if (! in_array($plan->status, [TrainingPlan::STATUS_ACTIVE, TrainingPlan::STATUS_COMPLETED], true)) {
            throw ValidationException::withMessages(['plan' => __('لا تُعرض الخطة قبل أن يقبلها العميل.')]);
        }

        $existing = $this->forPlan($plan);

        if ($existing && in_array($existing->status, [PlanShowcase::REQUESTED, PlanShowcase::APPROVED], true)) {
            throw ValidationException::withMessages(['plan' => $existing->isLive() ? __('الخطة منشورة بالفعل.') : __('طلب النشر ينتظر رد العميل.')]);
        }

        // what the client himself took down or refused is asked again, never put back by a standing consent
        $general = $this->consentCovers($plan, $kind) && (! $existing || $existing->withdrawn_by === 'business');

        $showcase = PlanShowcase::query()->updateOrCreate(['training_plan_id' => $plan->id], [
            'trainer_id' => (int) $plan->trainer_id,
            'public_title' => mb_substr(trim($publicTitle), 0, 191),
            'note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null,
            'status' => PlanShowcase::REQUESTED,
            'requested_at' => now(),
            'decided_at' => null, 'decision_note' => null, 'decided_by' => null, 'decided_via' => null, 'withdrawn_by' => null,
        ]);

        if ($general) {
            DB::transaction(function () use ($showcase, $plan) {
                $showcase->forceFill(['status' => PlanShowcase::APPROVED, 'decided_at' => now(), 'decided_by' => (int) $plan->client_id, 'decided_via' => 'general'])->save();
                $this->publish($showcase, $plan);
            });

            $this->tell((int) $plan->client_id, $plan, 'نُشرت نتائج خطتك بموافقتك العامة', 'Your plan results were shown under your general consent',
                'عُرضت نتائج الخطة «' . $plan->title . '» دون اسمك، وفق موافقتك العامة. يمكنك إيقافها في أي وقت.',
                'The results of "' . $plan->title . '" are shown without your name under your general consent. You can stop it any time.');
        } else {
            $this->tell((int) $plan->client_id, $plan, 'طلب نشر نتائج خطتك', 'A request to show your plan results',
                'يطلب المدرب عرض نتائج الخطة «' . $plan->title . '» كمنشور دون اسمك. لا يُنشر شيء قبل موافقتك.',
                'The trainer asks to show the results of "' . $plan->title . '" as a post without your name. Nothing is published before you agree.');
        }

        return $showcase->fresh();
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
                'decided_at' => now(), 'decided_by' => (int) $client->id, 'decided_via' => 'request',
                'decision_note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null,
            ])->save();

            $approve && $this->publish($showcase, $plan);
        });

        $this->tell((int) $plan->trainer_id, $plan,
            $approve ? 'وافق العميل على نشر النتائج' : 'رفض العميل نشر النتائج', $approve ? 'The client agreed to show the results' : 'The client declined to show the results',
            ($approve ? 'نُشرت نتائج الخطة «' : 'لم يوافق العميل على عرض نتائج «') . $plan->title . ($approve ? '» وتتحدّث كلما أكّد العميل نتيجة شهر.' : '».'),
            $approve ? 'The results of "' . $plan->title . '" are a post now and grow as the client confirms months.' : 'The client did not agree to show "' . $plan->title . '".');

        return $showcase->fresh();
    }

    /** Either side takes it down. What the client withdraws is never re-published by a general consent. */
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

        DB::transaction(function () use ($showcase, $isTrainer) {
            $showcase->forceFill(['status' => PlanShowcase::WITHDRAWN, 'decided_at' => now(), 'withdrawn_by' => $isTrainer ? 'business' : 'customer'])->save();
            $showcase->post_id && FeedPost::query()->whereKey($showcase->post_id)->update(['is_active' => false]);
        });

        $this->tell($isTrainer ? (int) $plan->client_id : (int) $plan->trainer_id, $plan, 'سُحب عرض النتائج', 'The results are no longer shown',
            'أُوقف عرض نتائج الخطة «' . $plan->title . '» كمنشور.', 'The post showing the results of "' . $plan->title . '" was taken down.');

        return $showcase->fresh();
    }

    /** The client confirms a month's result: the "stage succeeded" the public figures rest on. */
    public function confirmReport(TrainingPlan $plan, BodyCompositionReport $report, User $client): BodyCompositionReport
    {
        $this->assertClient($plan, $client);

        if ((int) $report->training_plan_id !== (int) $plan->id) {
            throw ValidationException::withMessages(['report' => __('هذا التقرير ليس من خطتك.')]);
        }

        $report->client_confirmed_at ?: $report->forceFill(['client_confirmed_at' => now()])->save();

        return $report->fresh();
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
     * The anonymous, live results: the kind of plan, how long it has run, and — for the months the client confirmed — how far each
     * measure moved since the first confirmed reading. Changes only, never absolute weights, and nothing that names a person.
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

        $confirmed = $plan->bodyReports()->whereNotNull('client_confirmed_at')->reorder('for_month')->get();
        $first = $confirmed->first();
        $measures = ['weight_kg', 'muscle_mass_kg', 'fat_percent', 'water_percent'];

        $months = [];
        foreach ($confirmed->skip(1) as $r) {
            $change = $r->deltaFrom($first);
            $months[] = [
                'month' => optional($r->for_month)->format('Y-m'),
                'confirmed_on' => optional($r->client_confirmed_at)->toDateString(),
                'change' => array_intersect_key($change, array_flip($measures)),
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
            'updated_on' => optional($confirmed->max('client_confirmed_at'))->toDateString(),
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
            'decided_via' => $s->decided_via,
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
