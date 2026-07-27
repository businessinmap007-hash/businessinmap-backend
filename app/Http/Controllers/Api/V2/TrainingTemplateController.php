<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\PlanMeal;
use App\Models\TemplateExercise;
use App\Models\TemplateMeal;
use App\Models\TrainingPlanTemplate;
use App\Models\User;
use App\Services\Training\TrainingTemplateService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Reusable training templates for a trainer (a gym/coach business): build once,
 * apply to many clients. Scoped to the acting business (owner or a delegate with
 * the `training` capability). Applying copies the template into a real plan
 * (see ClientTrainingController for the client side).
 */
class TrainingTemplateController extends Controller
{
    public function __construct(private readonly TrainingTemplateService $service)
    {
    }

    public function index(Request $request)
    {
        $rows = TrainingPlanTemplate::query()
            ->where('trainer_id', BusinessContext::id($request))
            ->withCount(['exercises', 'meals'])
            ->latest('id')
            ->paginate((int) $request->get('per_page', 20));

        $rows->getCollection()->transform(fn (TrainingPlanTemplate $t) => $this->serialize($t));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'goal' => ['nullable', 'string', 'max:200'],
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

        $trainer = User::query()->findOrFail(BusinessContext::id($request));

        $template = $this->service->create($trainer, [
            'title' => $data['title'],
            'goal' => $data['goal'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], $data['exercises'] ?? [], $data['meals'] ?? []);

        return response()->json([
            'success' => true,
            'message' => __('تم حفظ القالب.'),
            'data' => ['template' => $this->serialize($template)],
        ], 201);
    }

    public function show(Request $request, int $template)
    {
        $row = $this->ownedOrFail($request, $template);

        return response()->json([
            'success' => true,
            'data' => ['template' => $this->serialize($row->load(['exercises', 'meals']))],
        ]);
    }

    public function update(Request $request, int $template)
    {
        $row = $this->ownedOrFail($request, $template);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'goal' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $row->update(array_intersect_key($data, array_flip(['title', 'goal', 'notes'])));

        return response()->json([
            'success' => true,
            'message' => __('تم تحديث القالب.'),
            'data' => ['template' => $this->serialize($row->fresh(['exercises', 'meals']))],
        ]);
    }

    public function destroy(Request $request, int $template)
    {
        $row = $this->ownedOrFail($request, $template);
        $row->exercises()->delete();
        $row->meals()->delete();
        $row->delete();

        return response()->json(['success' => true, 'message' => __('تم حذف القالب.')]);
    }

    public function addExercise(Request $request, int $template)
    {
        $row = $this->ownedOrFail($request, $template);

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

    public function addMeal(Request $request, int $template)
    {
        $row = $this->ownedOrFail($request, $template);

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

    public function removeExercise(Request $request, int $template, int $exercise)
    {
        $row = $this->ownedOrFail($request, $template);
        $row->exercises()->where('id', $exercise)->delete();

        return response()->json(['success' => true, 'message' => __('تم حذف التمرين.')]);
    }

    public function removeMeal(Request $request, int $template, int $meal)
    {
        $row = $this->ownedOrFail($request, $template);
        $row->meals()->where('id', $meal)->delete();

        return response()->json(['success' => true, 'message' => __('تم حذف الوجبة.')]);
    }

    /** POST .../{template}/apply — instantiate a plan for a client from a copy. */
    public function apply(Request $request, int $template)
    {
        $row = $this->ownedOrFail($request, $template);
        $trainerId = BusinessContext::id($request);

        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:users,id', 'different:' . $trainerId],
            'title' => ['nullable', 'string', 'max:200'],
            'goal' => ['nullable', 'string', 'max:200'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $trainer = User::query()->findOrFail($trainerId);
        $client = User::query()->findOrFail((int) $data['client_id']);

        $plan = $this->service->apply($row, $trainer, $client, $data);

        return response()->json([
            'success' => true,
            'message' => __('تم تطبيق القالب وإنشاء خطة للعميل.'),
            'data' => ['plan_id' => (int) $plan->id],
        ], 201);
    }

    private function ownedOrFail(Request $request, int $id): TrainingPlanTemplate
    {
        return TrainingPlanTemplate::query()
            ->where('id', $id)
            ->where('trainer_id', BusinessContext::id($request))
            ->firstOrFail();
    }

    private function serialize(TrainingPlanTemplate $t): array
    {
        return [
            'id' => (int) $t->id,
            'title' => (string) $t->title,
            'goal' => $t->goal,
            'notes' => $t->notes,
            'exercises_count' => $t->exercises_count !== null
                ? (int) $t->exercises_count
                : ($t->relationLoaded('exercises') ? $t->exercises->count() : null),
            'meals_count' => $t->meals_count !== null
                ? (int) $t->meals_count
                : ($t->relationLoaded('meals') ? $t->meals->count() : null),
            'exercises' => $t->relationLoaded('exercises') ? $t->exercises->map(fn ($e) => $this->exercise($e))->all() : null,
            'meals' => $t->relationLoaded('meals') ? $t->meals->map(fn ($m) => $this->meal($m))->all() : null,
        ];
    }

    private function exercise(TemplateExercise $e): array
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
        ];
    }

    private function meal(TemplateMeal $m): array
    {
        return [
            'id' => (int) $m->id,
            'meal_type' => (string) $m->meal_type,
            'name' => (string) $m->name,
            'calories' => $m->calories !== null ? (int) $m->calories : null,
            'notes' => $m->notes,
            'sort_order' => (int) $m->sort_order,
        ];
    }
}
