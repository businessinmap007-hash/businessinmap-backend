<?php

namespace App\Services\Training;

use App\Models\PlanExercise;
use App\Models\PlanExerciseRound;
use App\Models\PlanProgressLog;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
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
