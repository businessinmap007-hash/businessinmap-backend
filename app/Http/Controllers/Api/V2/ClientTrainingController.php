<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\PlanExercise;
use App\Models\PlanExerciseRound;
use App\Models\TrainingPlan;
use App\Services\Training\TrainingPerformanceService;
use App\Support\ExerciseProgression;
use App\Services\Training\TrainingPlanService;
use Illuminate\Http\Request;

/**
 * The client's side of training plans: read the plans a trainer assigned me and
 * log my progress. A plan is visible only to its two parties.
 */
class ClientTrainingController extends Controller
{
    public function __construct(private readonly TrainingPlanService $service)
    {
    }

    /** GET /api/v2/training-plans — plans assigned to me. */
    public function index(Request $request)
    {
        $rows = TrainingPlan::query()
            ->where('client_id', (int) $request->user()->id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->with('trainer:id,name,logo,phone')
            ->withCount(['exercises', 'meals'])
            ->latest('id')
            ->paginate((int) $request->get('per_page', 20));

        $rows->getCollection()->transform(fn (TrainingPlan $p) => $this->serialize($p));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** GET /api/v2/training-plans/{plan} — one of my plans, in full. */
    public function show(Request $request, int $plan)
    {
        $row = $this->mineOrFail($request, $plan);

        return response()->json([
            'success' => true,
            'data' => ['plan' => $this->serialize($row->load([
                'exercises' => fn ($q) => $q->withCount(['rounds as completed_rounds_today' => fn ($r) => $r->whereDate('for_date', now()->toDateString())]),
                'exercises.rounds' => fn ($q) => $q->whereDate('for_date', now()->toDateString())->orderBy('round_number'),
                'exercises.images', 'exercises.libraryExercise.images', 'meals', 'meals.images', 'progressLogs', 'trainer:id,name,logo,phone',
            ]))],
        ]);
    }

    /** POST /api/v2/training-plans/{plan}/progress — log a check-in. */
    public function logProgress(Request $request, int $plan)
    {
        $row = $this->mineOrFail($request, $plan);

        $data = $request->validate([
            'logged_on' => ['nullable', 'date'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $log = $this->service->logProgress($row, $request->user(), $data);

        return response()->json([
            'success' => true,
            'message' => __('تم تسجيل تقدّمك.'),
            'data' => ['progress' => [
                'logged_on' => optional($log->logged_on)->toDateString(),
                'weight' => $log->weight !== null ? (float) $log->weight : null,
                'notes' => $log->notes,
            ]],
        ], 201);
    }

    /**
     * POST /api/v2/training-plans/{plan}/exercises/{exercise}/complete-round —
     * the trainee confirms finishing one round of an exercise. The only progress
     * action on an exercise; no notes or images by design.
     */
    public function completeRound(Request $request, int $plan, int $exercise)
    {
        $row = $this->mineOrFail($request, $plan);

        $ex = PlanExercise::query()
            ->where('id', $exercise)
            ->where('training_plan_id', (int) $row->id)
            ->firstOrFail();

        $data = $request->validate([
            'for_date' => ['nullable', 'date'],
            // What was actually done in this set. Numbers only, both optional.
            'reps' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);

        $result = $this->service->confirmRound(
            $row, $ex, $request->user(), $data['for_date'] ?? null,
            isset($data['reps']) ? (int) $data['reps'] : null,
            isset($data['weight']) ? (float) $data['weight'] : null,
        );

        return response()->json([
            'success' => true,
            'message' => __('تم تأكيد إتمام الجولة.'),
            'data' => [
                'round_number' => (int) $result['round']->round_number,
                'completed_rounds' => (int) $result['completed_rounds'],
                'total_sets' => $result['total_sets'],
                'round' => app(TrainingPerformanceService::class)->serializeRound($result['round']),
                'session_completed' => $result['session_completed'],
            ],
        ], 201);
    }

    /**
     * PUT /api/v2/training-plans/{plan}/exercises/{exercise}/rounds/{round} —
     * correct the reps/weight of a set I already confirmed.
     */
    public function updateRound(Request $request, int $plan, int $exercise, int $round)
    {
        $row = $this->mineOrFail($request, $plan);

        $set = PlanExerciseRound::query()
            ->where('id', $round)
            ->where('plan_exercise_id', $exercise)
            ->where('training_plan_id', (int) $row->id)
            ->firstOrFail();

        $data = $request->validate([
            'reps' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);

        $set = $this->service->updateRound(
            $row, $set,
            isset($data['reps']) ? (int) $data['reps'] : null,
            isset($data['weight']) ? (float) $data['weight'] : null,
        );

        return response()->json([
            'success' => true,
            'data' => ['round' => app(TrainingPerformanceService::class)->serializeRound($set)],
        ]);
    }

    /** GET /api/v2/training-plans/{plan}/monthly-summary?month=YYYY-MM — my month. */
    public function monthlySummary(Request $request, int $plan)
    {
        $row = $this->mineOrFail($request, $plan);
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        return response()->json([
            'success' => true,
            'data' => ['summary' => app(TrainingPerformanceService::class)->monthlySummary($row, $data['month'] ?? null)],
        ]);
    }

    /** GET /api/v2/training-plans/{plan}/weekly-summary?from=YYYY-MM-DD — my adherence. */
    public function weeklySummary(Request $request, int $plan)
    {
        $row = $this->mineOrFail($request, $plan);
        $data = $request->validate(['from' => ['nullable', 'date']]);

        return response()->json([
            'success' => true,
            'data' => ['summary' => $this->service->weeklySummary($row, $data['from'] ?? null)],
        ]);
    }

    /** POST /api/v2/training-plans/{plan}/accept — I accept the assigned plan. */
    public function accept(Request $request, int $plan)
    {
        $row = $this->service->accept($this->mineOrFail($request, $plan));

        return response()->json([
            'success' => true,
            'message' => __('تم قبول خطة التدريب.'),
            'data' => ['plan' => $this->serialize($row->fresh('trainer'))],
        ]);
    }

    /** POST /api/v2/training-plans/{plan}/decline — I decline it. */
    public function decline(Request $request, int $plan)
    {
        $row = $this->service->decline($this->mineOrFail($request, $plan));

        return response()->json([
            'success' => true,
            'message' => __('تم رفض خطة التدريب.'),
            'data' => ['plan' => $this->serialize($row->fresh('trainer'))],
        ]);
    }

    private function mineOrFail(Request $request, int $planId): TrainingPlan
    {
        return TrainingPlan::query()
            ->where('id', $planId)
            ->where('client_id', (int) $request->user()->id)
            ->firstOrFail();
    }

    /** Next week's per-set weights, only when they differ from this week's. */
    private function nextTargets($exercise, TrainingPlan $plan): array
    {
        $week = ExerciseProgression::weekNumber($plan, now());
        $total = ExerciseProgression::totalWeeks($plan);

        if ($total !== null && $week >= $total) {
            return [];
        }

        $now = ExerciseProgression::targetsFor($exercise, $week);
        $next = ExerciseProgression::targetsFor($exercise, $week + 1);

        return $next !== $now ? $next : [];
    }

    private function serialize(TrainingPlan $p): array
    {
        return [
            'id' => (int) $p->id,
            'title' => (string) $p->title,
            'goal' => $p->goal,
            'status' => (string) $p->status,
            'starts_on' => optional($p->starts_on)->toDateString(),
            'ends_on' => optional($p->ends_on)->toDateString(),
            // Programme position: week 3 of 8.
            'week_number' => ExerciseProgression::weekNumber($p, now()),
            'total_weeks' => ExerciseProgression::totalWeeks($p),
            'notes' => $p->notes,
            'trainer' => $p->relationLoaded('trainer') && $p->trainer
                ? ['id' => (int) $p->trainer->id, 'name' => $p->trainer->name, 'logo' => $p->trainer->logo, 'phone' => $p->trainer->phone]
                : ['id' => (int) $p->trainer_id],
            'exercises_count' => $p->exercises_count !== null ? (int) $p->exercises_count : null,
            'meals_count' => $p->meals_count !== null ? (int) $p->meals_count : null,
            'exercises' => $p->relationLoaded('exercises') ? $p->exercises->map(fn ($e) => [
                'id' => (int) $e->id,
                'day_of_week' => $e->day_of_week !== null ? (int) $e->day_of_week : null,
                'name' => (string) $e->name,
                'sets' => $e->sets !== null ? (int) $e->sets : null,
                'reps' => $e->reps,
                'target_weight' => $e->target_weight !== null ? (float) $e->target_weight : null,
                'day_label' => $e->day_label,
                // Per-set weights for THIS week, and next week's when they change —
                // so the trainee sees the climb coming.
                'current_targets' => ExerciseProgression::targetsFor($e, ExerciseProgression::weekNumber($p, now())),
                'next_targets' => $this->nextTargets($e, $p),
                'rest_seconds' => $e->rest_seconds !== null ? (int) $e->rest_seconds : null,
                'notes' => $e->notes,
                // The captain's illustration: the machine, the grip, the
                // position. Read here, never written from this side.
                'images' => $e->relationLoaded('images') ? $e->imagePayload() : [],
                // The catalogue's demonstration photos, for an exercise picked from
                // the library — shown when the captain attached none of their own.
                'library_images' => $e->relationLoaded('libraryExercise') && $e->libraryExercise?->relationLoaded('images')
                    ? $e->libraryExercise->images->pluck('image')->values()->all()
                    : [],
                'completed_rounds_today' => (int) ($e->completed_rounds_today ?? 0),
                // Today's confirmed sets with what was done in each.
                'today_rounds' => $e->relationLoaded('rounds')
                    ? $e->rounds->map(fn ($r) => app(TrainingPerformanceService::class)->serializeRound($r))->values()->all()
                    : [],
            ])->all() : null,
            'meals' => $p->relationLoaded('meals') ? $p->meals->map(fn ($m) => [
                'meal_type' => (string) $m->meal_type,
                'name' => (string) $m->name,
                'calories' => $m->calories !== null ? (int) $m->calories : null,
                'notes' => $m->notes,
                'images' => $m->relationLoaded('images') ? $m->imagePayload() : [],
            ])->all() : null,
            'progress' => $p->relationLoaded('progressLogs') ? $p->progressLogs->map(fn ($l) => [
                'logged_on' => optional($l->logged_on)->toDateString(),
                'weight' => $l->weight !== null ? (float) $l->weight : null,
                'notes' => $l->notes,
            ])->all() : null,
        ];
    }
}
