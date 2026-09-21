<?php

namespace App\Services\Training;

use App\Models\BodyCompositionReport;
use App\Models\PlanExercise;
use App\Models\PlanExerciseRound;
use App\Models\PlanSessionCompletion;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
use App\Support\ExerciseProgression;
use Illuminate\Support\Carbon;

/**
 * What the trainee actually did: per-set reps and weight, the moment a day's
 * work is finished (which tells the trainer), and the monthly roll-up.
 *
 * Volume is reps × weight and only counts sets where BOTH were recorded — a set
 * confirmed without numbers still counts as a set done, it just carries no
 * volume, rather than being invented as zero.
 */
class TrainingPerformanceService
{
    public function __construct(private readonly NotificationDispatcherService $notifications)
    {
    }

    /**
     * The exercises a trainee owes on a date: those scheduled for that weekday
     * plus the "any day" ones, and only those with a set count (an exercise with
     * no sets has nothing to finish).
     *
     * @return \Illuminate\Support\Collection<int,PlanExercise>
     */
    public function exercisesForDay(TrainingPlan $plan, Carbon $date)
    {
        return $plan->exercises()
            ->where(fn ($q) => $q->whereNull('day_of_week')->orWhere('day_of_week', $date->dayOfWeek))
            ->where('sets', '>', 0)
            ->get();
    }

    /**
     * Called after a set is confirmed. When every exercise owed that day has all
     * its sets, records the completion (once) and tells the trainer.
     *
     * @return bool true when this call is the one that completed the day
     */
    public function recordCompletionIfDone(TrainingPlan $plan, string $forDate): bool
    {
        $date = Carbon::parse($forDate);
        $owed = $this->exercisesForDay($plan, $date);

        if ($owed->isEmpty()) {
            return false;
        }

        $rounds = PlanExerciseRound::query()
            ->where('training_plan_id', (int) $plan->id)
            ->whereDate('for_date', $date->toDateString())
            ->get()
            ->groupBy('plan_exercise_id');

        foreach ($owed as $exercise) {
            if (($rounds->get($exercise->id)?->count() ?? 0) < (int) $exercise->sets) {
                return false;
            }
        }

        $all = $rounds->flatten(1);
        $totals = [
            'exercises_count' => $owed->count(),
            'sets_count' => $all->count(),
            'total_reps' => (int) $all->sum('reps'),
            'volume_kg' => round($this->volume($all), 2),
        ];

        $completion = PlanSessionCompletion::query()->firstOrNew([
            'training_plan_id' => (int) $plan->id,
            'for_date' => $date->toDateString(),
        ]);
        $isNew = ! $completion->exists;

        $completion->fill($totals + [
            'client_id' => (int) $plan->client_id,
            'completed_at' => $completion->completed_at ?? now(),
        ])->save();

        if ($isNew) {
            $this->notifyTrainer($plan, $completion);
        }

        return $isNew;
    }

    /** Re-total a finished day after a set was corrected. */
    public function refreshCompletion(TrainingPlan $plan, string $forDate): void
    {
        $completion = PlanSessionCompletion::query()
            ->where('training_plan_id', (int) $plan->id)
            ->whereDate('for_date', $forDate)
            ->first();

        if (! $completion) {
            return;
        }

        $all = PlanExerciseRound::query()
            ->where('training_plan_id', (int) $plan->id)
            ->whereDate('for_date', $forDate)
            ->get();

        $completion->update([
            'sets_count' => $all->count(),
            'total_reps' => (int) $all->sum('reps'),
            'volume_kg' => round($this->volume($all), 2),
        ]);
    }

    /**
     * One day's sets, grouped by exercise, next to what was prescribed — what
     * the trainer opens when told a day was finished.
     *
     * @return array<string,mixed>
     */
    public function dayLog(TrainingPlan $plan, string $date): array
    {
        $day = Carbon::parse($date)->toDateString();

        $rounds = PlanExerciseRound::query()
            ->where('training_plan_id', (int) $plan->id)
            ->whereDate('for_date', $day)
            ->orderBy('round_number')
            ->get()
            ->groupBy('plan_exercise_id');

        $exercises = $plan->exercises()->whereIn('id', $rounds->keys())->get()->keyBy('id');

        $week = ExerciseProgression::weekNumber($plan, Carbon::parse($day));

        return [
            'date' => $day,
            'week_number' => $week,
            'completed' => PlanSessionCompletion::query()
                ->where('training_plan_id', (int) $plan->id)->whereDate('for_date', $day)->exists(),
            'exercises' => $rounds->map(function ($sets, $exerciseId) use ($exercises, $week) {
                $ex = $exercises->get($exerciseId);

                return [
                    'exercise_id' => (int) $exerciseId,
                    'name' => (string) ($ex?->name ?? ''),
                    'target_sets' => $ex?->sets !== null ? (int) $ex->sets : null,
                    'target_reps' => $ex?->reps,
                    'target_weight' => $ex?->target_weight !== null ? (float) $ex->target_weight : null,
                    // What that week prescribed per set (the climb applied).
                    'target_weights' => $ex ? ExerciseProgression::targetsFor($ex, $week) : [],
                    'sets' => $sets->map(fn (PlanExerciseRound $r) => $this->serializeRound($r))->values()->all(),
                ];
            })->values()->all(),
        ];
    }

    /**
     * The month's roll-up for one plan: how many days were finished, the totals,
     * each exercise's best and total, and what the scale did. Built from the
     * rounds themselves, so nothing has to be "saved into" it — every confirmed
     * set is already in the summary.
     *
     * @return array<string,mixed>
     */
    public function monthlySummary(TrainingPlan $plan, ?string $month): array
    {
        $start = ($month ? Carbon::createFromFormat('Y-m', $month) : Carbon::now())->startOfMonth()->startOfDay();
        $end = $start->copy()->endOfMonth();

        $rounds = PlanExerciseRound::query()
            ->where('training_plan_id', (int) $plan->id)
            ->whereBetween('for_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $names = $plan->exercises()->pluck('name', 'id');

        $exercises = $rounds->groupBy('plan_exercise_id')->map(function ($sets, $exerciseId) use ($names) {
            $withWeight = $sets->whereNotNull('weight');

            return [
                'exercise_id' => (int) $exerciseId,
                'name' => (string) ($names[$exerciseId] ?? ''),
                'sets_done' => $sets->count(),
                'total_reps' => (int) $sets->sum('reps'),
                'max_weight' => $withWeight->isEmpty() ? null : (float) $withWeight->max('weight'),
                'volume_kg' => round($this->volume($sets), 2),
                'last_done' => optional($sets->max('for_date'))->toDateString(),
            ];
        })->sortByDesc('volume_kg')->values()->all();

        $sessions = PlanSessionCompletion::query()
            ->where('training_plan_id', (int) $plan->id)
            ->whereBetween('for_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('for_date')
            ->get();

        $logs = $plan->progressLogs()
            ->whereBetween('logged_on', [$start->toDateString(), $end->toDateString()])
            // reorder(): the relation itself is newest-first, and "first weight"
            // of the month must be the earliest reading.
            ->reorder()->orderBy('logged_on')->orderBy('id')
            ->get(['weight', 'logged_on']);
        $weights = $logs->whereNotNull('weight')->values();

        $report = BodyCompositionReport::query()
            ->where('training_plan_id', (int) $plan->id)
            ->where('for_month', $start->toDateString())
            ->first();

        return [
            'month' => $start->format('Y-m'),
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'sessions_completed' => $sessions->count(),
            'active_days' => $rounds->pluck('for_date')->map(fn ($d) => $d->toDateString())->unique()->count(),
            'total_sets' => $rounds->count(),
            'total_reps' => (int) $rounds->sum('reps'),
            'volume_kg' => round($this->volume($rounds), 2),
            'exercises' => $exercises,
            'sessions' => $sessions->map(fn (PlanSessionCompletion $s) => [
                'date' => $s->for_date->toDateString(),
                'exercises_count' => (int) $s->exercises_count,
                'sets_count' => (int) $s->sets_count,
                'total_reps' => (int) $s->total_reps,
                'volume_kg' => (float) $s->volume_kg,
            ])->values()->all(),
            'progress' => [
                'check_ins' => $logs->count(),
                'first_weight' => $weights->isEmpty() ? null : (float) $weights->first()->weight,
                'latest_weight' => $weights->isEmpty() ? null : (float) $weights->last()->weight,
            ],
            'body_report' => $report ? [
                'weight_kg' => $report->weight_kg !== null ? (float) $report->weight_kg : null,
                'muscle_mass_kg' => $report->muscle_mass_kg !== null ? (float) $report->muscle_mass_kg : null,
                'fat_percent' => $report->fat_percent !== null ? (float) $report->fat_percent : null,
                'water_percent' => $report->water_percent !== null ? (float) $report->water_percent : null,
            ] : null,
        ];
    }

    public function serializeRound(PlanExerciseRound $r): array
    {
        return [
            'id' => (int) $r->id,
            'round_number' => (int) $r->round_number,
            'reps' => $r->reps !== null ? (int) $r->reps : null,
            'weight' => $r->weight !== null ? (float) $r->weight : null,
        ];
    }

    /** Σ reps × weight over the sets that recorded both. */
    private function volume($rounds): float
    {
        return (float) $rounds
            ->filter(fn ($r) => $r->reps !== null && $r->weight !== null)
            ->sum(fn ($r) => $r->reps * (float) $r->weight);
    }

    private function notifyTrainer(TrainingPlan $plan, PlanSessionCompletion $completion): void
    {
        try {
            $clientName = trim((string) (User::query()->find($plan->client_id)?->name ?? ''));
            $date = $completion->for_date->toDateString();
            $volume = rtrim(rtrim(number_format((float) $completion->volume_kg, 1, '.', ''), '0'), '.');

            $this->notifications->dispatch('training_session_completed', (int) $plan->trainer_id, [
                'actor_id' => (int) $plan->client_id,
                // Stored bilingual content — deliberately not wrapped in __().
                'title_ar' => 'أنهى المتدرب تمارين اليوم',
                'title_en' => 'Your client finished today\'s workout',
                'body_ar' => trim(($clientName !== '' ? $clientName . ' ' : '') . "أنهى تمارين {$date}: {$completion->sets_count} مجموعة"
                    . ((float) $completion->volume_kg > 0 ? "، حجم {$volume} كجم" : '') . '.'),
                'body_en' => trim(($clientName !== '' ? $clientName . ' ' : '') . "finished {$date}: {$completion->sets_count} sets"
                    . ((float) $completion->volume_kg > 0 ? ", volume {$volume} kg" : '') . '.'),
                'action_type' => 'open_training_plan_manage',
                'notifiable_type' => TrainingPlan::class,
                'notifiable_id' => (int) $plan->id,
                'source_type' => 'training_session_completed',
                'source_id' => (int) $completion->id,
                'meta' => ['plan_id' => (int) $plan->id, 'for_date' => $date],
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
