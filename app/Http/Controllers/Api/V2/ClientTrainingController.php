<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\PlanExercise;
use App\Models\TrainingPlan;
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
            ->with('trainer:id,name,logo')
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
                'exercises.images', 'meals', 'meals.images', 'progressLogs', 'trainer:id,name,logo',
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
        ]);

        $result = $this->service->confirmRound($row, $ex, $request->user(), $data['for_date'] ?? null);

        return response()->json([
            'success' => true,
            'message' => __('تم تأكيد إتمام الجولة.'),
            'data' => [
                'round_number' => (int) $result['round']->round_number,
                'completed_rounds' => (int) $result['completed_rounds'],
                'total_sets' => $result['total_sets'],
            ],
        ], 201);
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

    private function mineOrFail(Request $request, int $planId): TrainingPlan
    {
        return TrainingPlan::query()
            ->where('id', $planId)
            ->where('client_id', (int) $request->user()->id)
            ->firstOrFail();
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
            'notes' => $p->notes,
            'trainer' => $p->relationLoaded('trainer') && $p->trainer
                ? ['id' => (int) $p->trainer->id, 'name' => $p->trainer->name, 'logo' => $p->trainer->logo]
                : ['id' => (int) $p->trainer_id],
            'exercises_count' => $p->exercises_count !== null ? (int) $p->exercises_count : null,
            'meals_count' => $p->meals_count !== null ? (int) $p->meals_count : null,
            'exercises' => $p->relationLoaded('exercises') ? $p->exercises->map(fn ($e) => [
                'id' => (int) $e->id,
                'day_of_week' => $e->day_of_week !== null ? (int) $e->day_of_week : null,
                'name' => (string) $e->name,
                'sets' => $e->sets !== null ? (int) $e->sets : null,
                'reps' => $e->reps,
                'rest_seconds' => $e->rest_seconds !== null ? (int) $e->rest_seconds : null,
                'notes' => $e->notes,
                // The captain's illustration: the machine, the grip, the
                // position. Read here, never written from this side.
                'images' => $e->relationLoaded('images') ? $e->imagePayload() : [],
                'completed_rounds_today' => (int) ($e->completed_rounds_today ?? 0),
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
