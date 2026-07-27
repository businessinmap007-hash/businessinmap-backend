<?php

namespace App\Services\Training;

use App\Models\PlanExercise;
use App\Models\PlanExerciseRound;
use App\Models\PlanProgressLog;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
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
    public function __construct(private readonly NotificationDispatcherService $notifications)
    {
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
            $plan = TrainingPlan::create([
                'trainer_id' => (int) $trainer->id,
                'client_id' => (int) $client->id,
                'title' => $header['title'],
                'goal' => $header['goal'] ?? null,
                'status' => TrainingPlan::STATUS_ACTIVE,
                'starts_on' => $header['starts_on'] ?? null,
                'ends_on' => $header['ends_on'] ?? null,
                'notes' => $header['notes'] ?? null,
            ]);

            foreach ($exercises as $e) {
                $plan->exercises()->create([
                    'day_of_week' => $e['day_of_week'] ?? null,
                    'name' => $e['name'],
                    'sets' => $e['sets'] ?? null,
                    'reps' => $e['reps'] ?? null,
                    'rest_seconds' => $e['rest_seconds'] ?? null,
                    'notes' => $e['notes'] ?? null,
                    'sort_order' => $e['sort_order'] ?? 0,
                ]);
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

            $this->notifyAssigned($plan);

            return $plan->load(['exercises', 'meals']);
        });
    }

    /** Change a plan's status (guarding against reviving a finished one). */
    public function setStatus(TrainingPlan $plan, string $status): TrainingPlan
    {
        if (in_array($plan->status, [TrainingPlan::STATUS_COMPLETED, TrainingPlan::STATUS_CANCELLED], true)) {
            throw ValidationException::withMessages([
                'status' => __('لا يمكن تعديل حالة خطة منتهية.'),
            ]);
        }

        $plan->update(['status' => $status]);

        return $plan;
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
     * @return array{round:PlanExerciseRound,completed_rounds:int,total_sets:?int}
     */
    public function confirmRound(TrainingPlan $plan, PlanExercise $exercise, User $client, ?string $date): array
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
            'completed_at' => now(),
        ]);

        return [
            'round' => $round,
            'completed_rounds' => $done + 1,
            'total_sets' => $totalSets,
        ];
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

    /** Tell the client a plan was assigned to them. Best-effort. */
    private function notifyAssigned(TrainingPlan $plan): void
    {
        try {
            $this->notifications->dispatch('training_plan_assigned', (int) $plan->client_id, [
                // Stored bilingual content — deliberately not wrapped in __().
                'title_ar' => 'خطة تدريب جديدة',
                'title_en' => 'New training plan',
                'body_ar' => 'أنشأ لك مدرّبك خطة تدريب وتغذية جديدة: ' . $plan->title . '.',
                'body_en' => 'Your trainer created a new training & nutrition plan: ' . $plan->title . '.',
                'notifiable_type' => TrainingPlan::class,
                'notifiable_id' => (int) $plan->id,
                'source_type' => TrainingPlan::class,
                'source_id' => (int) $plan->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
