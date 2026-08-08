<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\PlanExercise;
use App\Models\PlanMeal;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Services\Media\ImageUploadService;
use App\Services\Training\TrainingPlanService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The trainer's side of training plans: build a client's workout + nutrition
 * plan and manage it. Scoped to the acting business (owner, or a delegate with
 * the `training` capability). The client's side is ClientTrainingController.
 */
class TrainingPlanController extends Controller
{
    /** Enough to show a machine from two angles and the movement; not an album. */
    private const MAX_IMAGES = 6;

    public function __construct(private readonly TrainingPlanService $service)
    {
    }

    /** GET /api/v2/business/training-plans — my clients' plans. */
    public function index(Request $request)
    {
        $rows = TrainingPlan::query()
            ->where('trainer_id', BusinessContext::id($request))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', (int) $request->get('client_id')))
            ->with('client:id,name')
            ->withCount(['exercises', 'meals'])
            ->latest('id')
            ->paginate((int) $request->get('per_page', 20));

        $rows->getCollection()->transform(fn (TrainingPlan $p) => $this->serialize($p));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** POST /api/v2/business/training-plans — create a plan for a client. */
    public function store(Request $request)
    {
        $trainerId = BusinessContext::id($request);

        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:users,id', 'different:' . $trainerId],
            'title' => ['required', 'string', 'max:200'],
            'goal' => ['nullable', 'string', 'max:200'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'exercises' => ['nullable', 'array', 'max:200'],
            'exercises.*.name' => ['required', 'string', 'max:200'],
            'exercises.*.day_of_week' => ['nullable', 'integer', 'between:0,6'],
            'exercises.*.sets' => ['nullable', 'integer', 'min:0'],
            'exercises.*.reps' => ['nullable', 'string', 'max:40'],
            'exercises.*.rest_seconds' => ['nullable', 'integer', 'min:0'],
            'exercises.*.notes' => ['nullable', 'string', 'max:255'],
            'exercises.*.sort_order' => ['nullable', 'integer'],

            'meals' => ['nullable', 'array', 'max:200'],
            'meals.*.meal_type' => ['required', Rule::in(PlanMeal::TYPES)],
            'meals.*.name' => ['required', 'string', 'max:200'],
            'meals.*.calories' => ['nullable', 'integer', 'min:0'],
            'meals.*.notes' => ['nullable', 'string', 'max:255'],
            'meals.*.sort_order' => ['nullable', 'integer'],
        ]);

        $trainer = User::query()->findOrFail($trainerId);
        $client = User::query()->findOrFail((int) $data['client_id']);

        $plan = $this->service->create($trainer, $client, [
            'title' => $data['title'],
            'goal' => $data['goal'] ?? null,
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], $data['exercises'] ?? [], $data['meals'] ?? []);

        return response()->json([
            'success' => true,
            'message' => __('تم إنشاء خطة التدريب.'),
            'data' => ['plan' => $this->serialize($plan)],
        ], 201);
    }

    /** GET /api/v2/business/training-plans/{plan} — a plan I own, in full. */
    public function show(Request $request, int $plan)
    {
        $row = $this->ownedOrFail($request, $plan);

        return response()->json([
            'success' => true,
            'data' => ['plan' => $this->serialize($row->load([
                'exercises' => fn ($q) => $q->withCount(['rounds as completed_rounds_today' => fn ($r) => $r->whereDate('for_date', now()->toDateString())]),
                'exercises.images', 'meals', 'meals.images', 'progressLogs', 'client:id,name',
            ]))],
        ]);
    }

    /** PATCH /api/v2/business/training-plans/{plan} — status/header edits. */
    public function update(Request $request, int $plan)
    {
        $row = $this->ownedOrFail($request, $plan);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'goal' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'ends_on' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(TrainingPlan::STATUSES)],
        ]);

        if (! empty($data['status'])) {
            $this->service->setStatus($row, $data['status']);
        }

        $row->update(array_intersect_key($data, array_flip(['title', 'goal', 'notes', 'ends_on'])));

        return response()->json([
            'success' => true,
            'message' => __('تم تحديث خطة التدريب.'),
            'data' => ['plan' => $this->serialize($row->fresh(['exercises', 'meals']))],
        ]);
    }

    /**
     * GET /api/v2/business/training-plans/weekly-summary — weekly adherence for
     * ALL my clients at once (defaults to active plans; ?status= to override).
     */
    public function clientsWeeklySummary(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(TrainingPlan::STATUSES)],
        ]);

        return response()->json([
            'success' => true,
            'data' => ['summary' => $this->service->weeklySummaryForTrainer(
                BusinessContext::id($request),
                $data['from'] ?? null,
                $data['status'] ?? TrainingPlan::STATUS_ACTIVE,
            )],
        ]);
    }

    /** GET /api/v2/business/training-plans/{plan}/weekly-summary?from=YYYY-MM-DD */
    public function weeklySummary(Request $request, int $plan)
    {
        $row = $this->ownedOrFail($request, $plan);
        $data = $request->validate(['from' => ['nullable', 'date']]);

        return response()->json([
            'success' => true,
            'data' => ['summary' => $this->service->weeklySummary($row, $data['from'] ?? null)],
        ]);
    }

    public function addExercise(Request $request, int $plan)
    {
        $row = $this->ownedOrFail($request, $plan);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'day_of_week' => ['nullable', 'integer', 'between:0,6'],
            'sets' => ['nullable', 'integer', 'min:0'],
            'reps' => ['nullable', 'string', 'max:40'],
            'rest_seconds' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $exercise = $row->exercises()->create($data);

        return response()->json(['success' => true, 'data' => ['exercise' => $this->exercise($exercise)]], 201);
    }

    public function addMeal(Request $request, int $plan)
    {
        $row = $this->ownedOrFail($request, $plan);

        $data = $request->validate([
            'meal_type' => ['required', Rule::in(PlanMeal::TYPES)],
            'name' => ['required', 'string', 'max:200'],
            'calories' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $meal = $row->meals()->create($data);

        return response()->json(['success' => true, 'data' => ['meal' => $this->meal($meal)]], 201);
    }

    /**
     * Fetched, then deleted.
     *
     * `->where('id', $x)->delete()` is a mass delete, and a mass delete fires
     * no model events — so the illustrative photos would keep their rows and
     * their FILES after the exercise they belong to was gone, with nothing left
     * that could find them.
     */
    public function removeExercise(Request $request, int $plan, int $exercise)
    {
        $row = $this->ownedOrFail($request, $plan);
        $row->exercises()->where('id', $exercise)->first()?->delete();

        return response()->json(['success' => true, 'message' => __('تم حذف التمرين.')]);
    }

    public function removeMeal(Request $request, int $plan, int $meal)
    {
        $row = $this->ownedOrFail($request, $plan);
        $row->meals()->where('id', $meal)->first()?->delete();

        return response()->json(['success' => true, 'message' => __('تم حذف الوجبة.')]);
    }

    // ─────────────────────── Illustrative photos ───────────────────────

    /**
     * POST /api/v2/business/training-plans/{plan}/exercises/{exercise}/images
     *
     * «الصور تكون توضيحية فقط من الكابتن — للجهاز مثلا او شكل التمرين».
     *
     * The plan is a private thing between two people, and the client's side of
     * the API has no route that writes an image at all — that absence is the
     * enforcement, not a flag anyone could flip. What a trainee sends stays
     * text, in the plan chat.
     */
    public function addExerciseImages(Request $request, int $plan, int $exercise)
    {
        $row = $this->ownedOrFail($request, $plan);
        $target = $row->exercises()->where('id', $exercise)->firstOrFail();

        return $this->attachImages($request, $target);
    }

    /** DELETE .../exercises/{exercise}/images/{image} — the row and the file. */
    public function removeExerciseImage(Request $request, int $plan, int $exercise, int $image)
    {
        $row = $this->ownedOrFail($request, $plan);
        $target = $row->exercises()->where('id', $exercise)->firstOrFail();

        return $this->detachImage($target, $image);
    }

    /** POST .../meals/{meal}/images — a picture of the meal, same rule. */
    public function addMealImages(Request $request, int $plan, int $meal)
    {
        $row = $this->ownedOrFail($request, $plan);
        $target = $row->meals()->where('id', $meal)->firstOrFail();

        return $this->attachImages($request, $target);
    }

    /** DELETE .../meals/{meal}/images/{image}. */
    public function removeMealImage(Request $request, int $plan, int $meal, int $image)
    {
        $row = $this->ownedOrFail($request, $plan);
        $target = $row->meals()->where('id', $meal)->firstOrFail();

        return $this->detachImage($target, $image);
    }

    /** @param  PlanExercise|PlanMeal  $target */
    private function attachImages(Request $request, $target)
    {
        $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:' . self::MAX_IMAGES],
            'images.*' => ImageUploadService::validationRules(),
        ]);

        if ($target->images()->count() + count($request->file('images', [])) > self::MAX_IMAGES) {
            return response()->json([
                'success' => false,
                'message' => __('الحد الأقصى :max صور.', ['max' => self::MAX_IMAGES]),
            ], 422);
        }

        $uploads = app(ImageUploadService::class);
        $saved = [];

        foreach ($request->file('images') as $file) {
            $saved[] = $target->images()->create([
                'image' => $uploads->store($file),
                // Not evidence. A captain illustrates with the picture that
                // shows the movement best, wherever he got it.
                'source' => Image::SOURCE_UPLOAD,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => ['images' => array_map(
                fn (Image $image) => ['id' => (int) $image->id, 'image' => $image->image],
                $saved
            )],
        ], 201);
    }

    /** @param  PlanExercise|PlanMeal  $target */
    private function detachImage($target, int $imageId)
    {
        $image = $target->images()->findOrFail($imageId);

        app(ImageUploadService::class)->delete($image->image);
        $image->delete();

        return response()->json(['success' => true]);
    }

    private function ownedOrFail(Request $request, int $planId): TrainingPlan
    {
        return TrainingPlan::query()
            ->where('id', $planId)
            ->where('trainer_id', BusinessContext::id($request))
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
            'client' => $p->relationLoaded('client') && $p->client
                ? ['id' => (int) $p->client->id, 'name' => $p->client->name]
                : ['id' => (int) $p->client_id],
            'exercises_count' => $p->exercises_count !== null ? (int) $p->exercises_count : null,
            'meals_count' => $p->meals_count !== null ? (int) $p->meals_count : null,
            'exercises' => $p->relationLoaded('exercises') ? $p->exercises->map(fn ($e) => $this->exercise($e))->all() : null,
            'meals' => $p->relationLoaded('meals') ? $p->meals->map(fn ($m) => $this->meal($m))->all() : null,
            'progress' => $p->relationLoaded('progressLogs') ? $p->progressLogs->map(fn ($l) => [
                'logged_on' => optional($l->logged_on)->toDateString(),
                'weight' => $l->weight !== null ? (float) $l->weight : null,
                'notes' => $l->notes,
            ])->all() : null,
        ];
    }

    private function exercise(PlanExercise $e): array
    {
        return [
            'id' => (int) $e->id,
            'day_of_week' => $e->day_of_week !== null ? (int) $e->day_of_week : null,
            'name' => (string) $e->name,
            'sets' => $e->sets !== null ? (int) $e->sets : null,
            'reps' => $e->reps,
            'rest_seconds' => $e->rest_seconds !== null ? (int) $e->rest_seconds : null,
            'notes' => $e->notes,
            'sort_order' => (int) $e->sort_order,
            'images' => $e->relationLoaded('images') ? $e->imagePayload() : [],
            'completed_rounds_today' => $e->completed_rounds_today !== null ? (int) $e->completed_rounds_today : null,
        ];
    }

    private function meal(PlanMeal $m): array
    {
        return [
            'id' => (int) $m->id,
            'meal_type' => (string) $m->meal_type,
            'name' => (string) $m->name,
            'calories' => $m->calories !== null ? (int) $m->calories : null,
            'notes' => $m->notes,
            'sort_order' => (int) $m->sort_order,
            'images' => $m->relationLoaded('images') ? $m->imagePayload() : [],
        ];
    }
}
