<?php

namespace App\Services\Training;

use App\Models\PlanExercise;
use App\Models\PlanExerciseRound;
use App\Models\PlanProgressLog;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Services\Fees\PlatformEventFeeService;
use App\Services\Notifications\NotificationDispatcherService;
use App\Support\ExerciseProgression;
use App\Support\TrainingProgramRules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The training-plan lifecycle: a trainer builds a client's workout + nutrition
 * plan, the client follows it and logs progress. Assigning a plan pushes it to
 * the client.
 */
class TrainingPlanService
{
    public function __construct(
        private readonly NotificationDispatcherService $notifications,
        private readonly TrainingPerformanceService $performance,
        private readonly PlatformEventFeeService $eventFees,
    ) {
    }

    /**
     * A trainer creates a plan for a client, with its exercises and meals.
     *
     * @param  array<int,array<string,mixed>>  $exercises
     * @param  array<int,array<string,mixed>>  $meals
     */
    public function create(User $trainer, User $client, array $header, array $exercises, array $meals): TrainingPlan
    {
        return DB::transaction(function () use ($trainer, $client, $header, $exercises, $meals) {
            $weeks = isset($header['duration_weeks']) ? (int) $header['duration_weeks'] : null;
            $startsOn = $header['starts_on'] ?? ($weeks ? now()->toDateString() : null);
            $endsOn = $header['ends_on'] ?? ($weeks && $startsOn
                ? Carbon::parse($startsOn)->addDays($weeks * 7 - 1)->toDateString()
                : null);

            $plan = TrainingPlan::create([
                'trainer_id' => (int) $trainer->id,
                'client_id' => (int) $client->id,
                'title' => $header['title'],
                'goal' => $header['goal'] ?? null,
                // The client confirms before it activates - see accept().
                'status' => TrainingPlan::STATUS_PENDING,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'duration_weeks' => $weeks,
                // the paid month: the fee below is taken for it, and it is what keeps the plan in front of the client
                'paid_until' => $this->periodEnd(),
                'renewals' => 0,
                'notes' => $header['notes'] ?? null,
            ]);

            foreach ($exercises as $e) {
                $plan->exercises()->create(TrainingProgramRules::attributes($e, $header['progression'] ?? null));
            }

            foreach ($meals as $m) {
                $plan->meals()->create([
                    'meal_type' => $m['meal_type'],
                    'name' => $m['name'],
                    'calories' => $m['calories'] ?? null,
                    'notes' => $m['notes'] ?? null,
                    'sort_order' => $m['sort_order'] ?? 0,
                ]);
            }

            // Sharing a programme with a client is the use of the service: the first month's fee is taken here, and the
            // whole thing is undone when a side cannot pay.
            $this->settle($plan);

            $this->notifyAssigned($plan);

            return $plan->load(['exercises', 'meals']);
        });
    }

    /** The last day of a paid month that starts today. */
    private function periodEnd(): string
    {
        return now()->addMonthsNoOverflow((int) config('bim.training.plan_months', 1))->toDateString();
    }

    /** The month the trainer paid for is over: the client no longer sees the plan until it is renewed. */
    public function isExpired(TrainingPlan $plan): bool
    {
        return $plan->paid_until !== null && $plan->paid_until->lt(now()->startOfDay());
    }

    /**
     * Take this month's fee for each kind of service the plan uses (the workout = `training_program_change`, the meals =
     * `nutrition_program_change`; the panel prices each side). Idempotent per month, so adding the first meal to a plan
     * that already paid for its workout charges only the nutrition fee. `$extra` names a kind about to be added.
     *
     * @param  list<string>  $extra  'training' | 'nutrition'
     */
    public function settle(TrainingPlan $plan, array $extra = []): void
    {
        $kinds = $extra;

        if ($plan->exercises()->exists()) {
            $kinds[] = 'training';
        }

        if ($plan->meals()->exists()) {
            $kinds[] = 'nutrition';
        }

        $parties = ['trainer' => (int) $plan->trainer_id, 'client' => (int) $plan->client_id];

        DB::transaction(function () use ($plan, $kinds, $parties) {
            foreach (array_unique($kinds) as $kind) {
                $event = $kind === 'nutrition' ? PlatformEventFeeService::NUTRITION_PROGRAM_CHANGE : PlatformEventFeeService::TRAINING_PROGRAM_CHANGE;
                $this->eventFees->charge($event, $parties, 0, 'training_plan', (int) $plan->id, (string) ((int) $plan->renewals + 1));
            }
        });
    }

    /**
     * «ينتهي شهر الخطة»: trainer and client are told, once per month, a few days before the plan runs out of its paid month
     * (`bim.training.renew_reminder_days`). Pending / active / paused plans only; a finished one is not renewed anyway.
     *
     * @return int plans announced
     */
    public function alertExpiring(Carbon $asOf): int
    {
        $days = (int) config('bim.training.renew_reminder_days', 3);

        $plans = TrainingPlan::query()
            ->whereIn('status', [TrainingPlan::STATUS_PENDING, TrainingPlan::STATUS_ACTIVE, TrainingPlan::STATUS_PAUSED])
            ->whereNotNull('paid_until')
            ->whereBetween('paid_until', [$asOf->copy()->toDateString(), $asOf->copy()->addDays($days)->toDateString()])
            ->where(fn ($q) => $q->whereNull('expiry_alerted_for')->orWhereColumn('expiry_alerted_for', '!=', 'paid_until'))
            ->get();

        foreach ($plans as $plan) {
            $until = $plan->paid_until->toDateString();

            foreach ([
                [(int) $plan->trainer_id, "ينتهي الشهر المدفوع لخطة «{$plan->title}» يوم {$until}. جدّدها لتبقى ظاهرة للعميل.", "The paid month of \"{$plan->title}\" ends on {$until}. Renew it to keep it in front of your client."],
                [(int) $plan->client_id, "ينتهي الشهر المدفوع لخطتك «{$plan->title}» يوم {$until}. اطلب من مدربك تجديدها حتى لا تختفي.", "The paid month of your plan \"{$plan->title}\" ends on {$until}. Ask your trainer to renew it or it will disappear."],
            ] as [$userId, $ar, $en]) {
                try {
                    $this->notifications->dispatch('training_plan_expiring', $userId, [
                        'body_ar' => $ar,
                        'body_en' => $en,
                        'action_type' => $userId === (int) $plan->trainer_id ? 'open_training_plan_manage' : 'open_training_plan',
                        'notifiable_type' => TrainingPlan::class,
                        'notifiable_id' => (int) $plan->id,
                        'source_type' => 'training_plan_expiring',
                        'source_id' => (int) $plan->id,
                        'meta' => ['plan_id' => (int) $plan->id, 'paid_until' => $until],
                    ]);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            $plan->forceFill(['expiry_alerted_for' => $until])->save();
        }

        return $plans->count();
    }

    /**
     * Another paid month (owner, 2026-10-27): the fee is taken again and the plan is back in front of the client. A plan
     * that is finished, cancelled or declined is not renewed.
     *
     * @param  list<string>  $extra
     */
    public function renew(TrainingPlan $plan, array $extra = []): TrainingPlan
    {
        if (in_array($plan->status, [TrainingPlan::STATUS_COMPLETED, TrainingPlan::STATUS_CANCELLED, TrainingPlan::STATUS_DECLINED], true)) {
            throw ValidationException::withMessages(['status' => __('لا يمكن تجديد خطة منتهية.')]);
        }

        return DB::transaction(function () use ($plan, $extra) {
            $plan->update(['renewals' => (int) $plan->renewals + 1, 'paid_until' => $this->periodEnd()]);
            $this->settle($plan, $extra);

            return $plan->refresh();
        });
    }

    /**
     * Called BEFORE a change to the programme. Editing a plan whose month has run out renews it (the fee is taken again,
     * the same as a renewal); inside the month an edit is free, except that the first item of a kind the plan did not
     * use yet (a meal on a workout-only plan) starts that service's fee for the month.
     *
     * @param  string  $kind  'training' | 'nutrition'
     */
    public function prepareEdit(TrainingPlan $plan, string $kind): void
    {
        if ($plan->paid_until === null) {
            return; // a plan from before the paid month
        }

        if ($this->isExpired($plan)) {
            $this->renew($plan, [$kind]);

            return;
        }

        $this->settle($plan, [$kind]);
    }

    /**
     * Change a plan's status (guarding against reviving a finished one). Never
     * the way out of `pending` - accept()/decline() are the client's own,
     * or a trainer could hand themselves the client's confirmation.
     */
    public function setStatus(TrainingPlan $plan, string $status): TrainingPlan
    {
        if (in_array($plan->status, [TrainingPlan::STATUS_COMPLETED, TrainingPlan::STATUS_CANCELLED], true)) {
            throw ValidationException::withMessages([
                'status' => __('لا يمكن تعديل حالة خطة منتهية.'),
            ]);
        }

        if ($plan->status === TrainingPlan::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => __('بانتظار قبول العميل للخطة.'),
            ]);
        }

        $plan->update(['status' => $status]);

        return $plan;
    }

    /** The client accepts the assigned plan - the only way it becomes active. */
    public function accept(TrainingPlan $plan): TrainingPlan
    {
        if ($plan->status !== TrainingPlan::STATUS_PENDING) {
            return $plan;
        }

        $plan->update(['status' => TrainingPlan::STATUS_ACTIVE]);
        $this->notifyResponse($plan, accepted: true);

        return $plan;
    }

    /** The client declines - the plan stays on record but never activates. */
    public function decline(TrainingPlan $plan): TrainingPlan
    {
        if ($plan->status !== TrainingPlan::STATUS_PENDING) {
            return $plan;
        }

        $plan->update(['status' => TrainingPlan::STATUS_DECLINED]);
        $this->notifyResponse($plan, accepted: false);

        return $plan;
    }

    /**
     * Set how long the programme runs and/or how the weights climb, for the
     * whole plan at once. Progression goes to the given exercises, or to every
     * exercise that has a weight; later weeks follow automatically because
     * targets are computed from the rule (see ExerciseProgression).
     *
     * @param  array<string,mixed>  $data  duration_weeks?, starts_on?, progression?{every_weeks,increment_kg,exercise_ids?}
     */
    public function applyProgram(TrainingPlan $plan, array $data): TrainingPlan
    {
        return DB::transaction(function () use ($plan, $data) {
            $updates = [];

            if (isset($data['starts_on'])) {
                $updates['starts_on'] = $data['starts_on'];
            }

            if (isset($data['duration_weeks'])) {
                $updates['duration_weeks'] = (int) $data['duration_weeks'];
            }

            $weeks = $updates['duration_weeks'] ?? $plan->duration_weeks;
            $start = $updates['starts_on'] ?? optional($plan->starts_on)->toDateString();

            if ($weeks && $start) {
                $updates['ends_on'] = Carbon::parse($start)->addDays($weeks * 7 - 1)->toDateString();
            }

            if ($updates !== []) {
                $plan->update($updates);
            }

            $rule = $data['progression'] ?? null;

            if (is_array($rule) && (array_key_exists('every_weeks', $rule) || array_key_exists('increment_kg', $rule))) {
                $query = $plan->exercises();

                if (! empty($rule['exercise_ids'])) {
                    $query->whereIn('id', array_map('intval', $rule['exercise_ids']));
                }

                foreach ($query->get() as $exercise) {
                    $weighted = ! empty($exercise->set_weights) || $exercise->target_weight !== null;

                    if (! $weighted && empty($rule['exercise_ids'])) {
                        continue;
                    }

                    $exercise->update([
                        'progress_every_weeks' => $rule['every_weeks'] ?? null,
                        'progress_increment_kg' => $rule['increment_kg'] ?? null,
                    ]);
                }
            }

            return $plan->fresh();
        });
    }

    /** The client records a check-in against the plan. */
    public function logProgress(TrainingPlan $plan, User $client, array $data): PlanProgressLog
    {
        if ($plan->status !== TrainingPlan::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'status' => __('لا يمكن تسجيل تقدّم على خطة غير نشطة.'),
            ]);
        }

        return $plan->progressLogs()->create([
            'client_id' => (int) $client->id,
            'logged_on' => $data['logged_on'] ?? now()->toDateString(),
            'weight' => $data['weight'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * The trainee confirms finishing ONE round (set) of an exercise. The round
     * number is assigned server-side (the next unconfirmed round for that day),
     * so the trainee just taps "done" after each round. Refused once every
     * prescribed set is already confirmed, or on a non-active plan.
     *
     * Optionally records what was actually done in the set (reps, weight). Both
     * are numbers only; a set confirmed without them still counts as done.
     *
     * @return array{round:PlanExerciseRound,completed_rounds:int,total_sets:?int,session_completed:bool}
     */
    public function confirmRound(TrainingPlan $plan, PlanExercise $exercise, User $client, ?string $date, ?int $reps = null, ?float $weight = null): array
    {
        if ($plan->status !== TrainingPlan::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'status' => __('لا يمكن تسجيل تقدّم على خطة غير نشطة.'),
            ]);
        }

        $forDate = $date ?: now()->toDateString();
        $totalSets = $exercise->sets !== null ? (int) $exercise->sets : null;

        $done = PlanExerciseRound::query()
            ->where('plan_exercise_id', (int) $exercise->id)
            ->whereDate('for_date', $forDate)
            ->count();

        if ($totalSets !== null && $done >= $totalSets) {
            throw ValidationException::withMessages([
                'round' => __('أكملت جميع جولات هذا التمرين لهذا اليوم.'),
            ]);
        }

        $round = PlanExerciseRound::create([
            'plan_exercise_id' => (int) $exercise->id,
            'training_plan_id' => (int) $plan->id,
            'client_id' => (int) $client->id,
            'for_date' => $forDate,
            'round_number' => $done + 1,
            'reps' => $reps,
            'weight' => $weight,
            'completed_at' => now(),
        ]);

        return [
            'round' => $round,
            'completed_rounds' => $done + 1,
            'total_sets' => $totalSets,
            // True only for the call that finished the whole day (and told the trainer).
            'session_completed' => $this->performance->recordCompletionIfDone($plan, $forDate),
        ];
    }

    /** The trainee corrects the reps/weight of a set they already confirmed. */
    public function updateRound(TrainingPlan $plan, PlanExerciseRound $round, ?int $reps, ?float $weight): PlanExerciseRound
    {
        if ($plan->status !== TrainingPlan::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'status' => __('لا يمكن تسجيل تقدّم على خطة غير نشطة.'),
            ]);
        }

        $round->update(['reps' => $reps, 'weight' => $weight]);
        $this->performance->refreshCompletion($plan, $round->for_date->toDateString());

        return $round;
    }

    /**
     * A client's weekly adherence to a plan over a 7-day window: confirmed
     * exercise rounds vs. the weekly target (sum of prescribed sets), the days
     * they were active, and their progress check-ins. Window starts at $from (a
     * date), else the Saturday of the current week.
     *
     * @return array<string,mixed>
     */
    public function weeklySummary(TrainingPlan $plan, ?string $from = null): array
    {
        $start = ($from ? Carbon::parse($from) : Carbon::now()->startOfWeek(Carbon::SATURDAY))->startOfDay();
        $end = $start->copy()->addDays(6);

        $targetRounds = (int) $plan->exercises()->sum('sets');

        // Confirmed rounds per day in the window.
        $perDay = PlanExerciseRound::query()
            ->where('training_plan_id', (int) $plan->id)
            ->whereBetween('for_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('for_date, COUNT(*) AS rounds')
            ->groupBy('for_date')
            ->pluck('rounds', 'for_date');

        $days = [];
        $completed = 0;
        $activeDays = 0;
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->toDateString();
            $count = (int) ($perDay[$key] ?? 0);
            $completed += $count;
            if ($count > 0) {
                $activeDays++;
            }
            $days[] = ['date' => $key, 'completed_rounds' => $count];
        }

        $progress = $plan->progressLogs()
            ->whereBetween('logged_on', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('logged_on')->orderByDesc('id')
            ->get(['weight', 'logged_on']);

        return [
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'weekly_target_rounds' => $targetRounds,
            'completed_rounds' => $completed,
            'adherence_percent' => $targetRounds > 0 ? min(100, (int) round($completed / $targetRounds * 100)) : null,
            'active_days' => $activeDays,
            'days' => $days,
            'progress' => [
                'check_ins' => $progress->count(),
                'latest_weight' => $progress->first() && $progress->first()->weight !== null
                    ? (float) $progress->first()->weight
                    : null,
            ],
        ];
    }

    /**
     * A trainer's weekly adherence overview across ALL their clients' plans, in
     * one pass (bulk queries, no N+1). Same 7-day window as weeklySummary().
     * Defaults to active plans.
     *
     * @return array<string,mixed>
     */
    public function weeklySummaryForTrainer(int $trainerId, ?string $from = null, ?string $status = TrainingPlan::STATUS_ACTIVE): array
    {
        $start = ($from ? Carbon::parse($from) : Carbon::now()->startOfWeek(Carbon::SATURDAY))->startOfDay();
        $end = $start->copy()->addDays(6);

        $plans = TrainingPlan::query()
            ->where('trainer_id', $trainerId)
            // a plan past its paid month is hidden from the client, so it has no adherence to average
            ->visibleToClient()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with('client:id,name')
            ->latest('id')
            ->get(['id', 'client_id', 'title', 'status']);

        $planIds = $plans->pluck('id')->all();

        if ($planIds === []) {
            return ['from' => $start->toDateString(), 'to' => $end->toDateString(), 'plans' => 0, 'average_adherence' => null, 'clients' => []];
        }

        $targets = PlanExercise::query()
            ->whereIn('training_plan_id', $planIds)
            ->groupBy('training_plan_id')
            ->selectRaw('training_plan_id, COALESCE(SUM(sets),0) AS target')
            ->pluck('target', 'training_plan_id');

        $rounds = PlanExerciseRound::query()
            ->whereIn('training_plan_id', $planIds)
            ->whereBetween('for_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('training_plan_id')
            ->selectRaw('training_plan_id, COUNT(*) AS rounds, COUNT(DISTINCT for_date) AS active_days')
            ->get()
            ->keyBy('training_plan_id');

        $checkIns = PlanProgressLog::query()
            ->whereIn('training_plan_id', $planIds)
            ->whereBetween('logged_on', [$start->toDateString(), $end->toDateString()])
            ->groupBy('training_plan_id')
            ->selectRaw('training_plan_id, COUNT(*) AS check_ins')
            ->pluck('check_ins', 'training_plan_id');

        $adherences = [];
        $clients = $plans->map(function (TrainingPlan $p) use ($targets, $rounds, $checkIns, &$adherences) {
            $target = (int) ($targets[$p->id] ?? 0);
            $completed = (int) ($rounds[$p->id]->rounds ?? 0);
            $adherence = $target > 0 ? min(100, (int) round($completed / $target * 100)) : null;
            if ($adherence !== null) {
                $adherences[] = $adherence;
            }

            return [
                'plan_id' => (int) $p->id,
                'status' => (string) $p->status,
                'title' => (string) $p->title,
                'client' => $p->client ? ['id' => (int) $p->client->id, 'name' => $p->client->name] : ['id' => (int) $p->client_id],
                'weekly_target_rounds' => $target,
                'completed_rounds' => $completed,
                'adherence_percent' => $adherence,
                'active_days' => (int) ($rounds[$p->id]->active_days ?? 0),
                'check_ins' => (int) ($checkIns[$p->id] ?? 0),
            ];
        })->values()->all();

        return [
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'plans' => count($clients),
            'average_adherence' => $adherences === [] ? null : (int) round(array_sum($adherences) / count($adherences)),
            'clients' => $clients,
        ];
    }

    /** Tell the client a plan was assigned to them, pending their acceptance. */
    private function notifyAssigned(TrainingPlan $plan): void
    {
        try {
            $trainerName = trim((string) (User::query()->find($plan->trainer_id)?->name ?? ''));

            $this->notifications->dispatch('training_plan_assigned', (int) $plan->client_id, [
                'actor_id' => (int) $plan->trainer_id,
                // Stored bilingual content — deliberately not wrapped in __().
                'title_ar' => 'خطة تدريب جديدة بانتظار قبولك',
                'title_en' => 'New training plan awaiting your acceptance',
                'body_ar' => trim(($trainerName !== '' ? $trainerName . ' ' : '') . 'أنشأ لك خطة تدريب وتغذية جديدة: ' . $plan->title . '.'),
                'body_en' => trim(($trainerName !== '' ? $trainerName . ': ' : '') . 'created a new training & nutrition plan for you: ' . $plan->title . '.'),
                'action_type' => 'open_training_plan',
                'notifiable_type' => TrainingPlan::class,
                'notifiable_id' => (int) $plan->id,
                'source_type' => 'training_plan_assigned',
                'source_id' => (int) $plan->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Tell the trainer whether the client accepted or declined. */
    private function notifyResponse(TrainingPlan $plan, bool $accepted): void
    {
        try {
            $clientName = trim((string) (User::query()->find($plan->client_id)?->name ?? ''));

            $this->notifications->dispatch($accepted ? 'training_plan_accepted' : 'training_plan_declined', (int) $plan->trainer_id, [
                'actor_id' => (int) $plan->client_id,
                'title_ar' => $accepted ? 'تم قبول خطة التدريب' : 'تم رفض خطة التدريب',
                'title_en' => $accepted ? 'Training plan accepted' : 'Training plan declined',
                'body_ar' => trim(($clientName !== '' ? $clientName . ' ' : '') . ($accepted ? 'قبل خطة ' : 'رفض خطة ') . $plan->title . '.'),
                'body_en' => trim(($clientName !== '' ? $clientName . ' ' : '') . ($accepted ? 'accepted' : 'declined') . ' the plan ' . $plan->title . '.'),
                'action_type' => 'open_training_plan_manage',
                'notifiable_type' => TrainingPlan::class,
                'notifiable_id' => (int) $plan->id,
                'source_type' => $accepted ? 'training_plan_accepted' : 'training_plan_declined',
                'source_id' => (int) $plan->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
