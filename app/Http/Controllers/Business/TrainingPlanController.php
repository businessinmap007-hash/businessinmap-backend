<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\BodyCompositionReport;
use App\Models\PlanMeal;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Services\Training\TrainingPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «أين يمكن انشاء خطة التدريب والنظام الغذائي للعميل» — owner, 2026-08-09.
 *
 * Nowhere, was the answer. The plans existed, the API existed and the admin
 * could read them, but a gym owner at a desk had no screen to write one: the
 * only door was the mobile client. Every other thing a business sells has a
 * panel screen — the menu, the units, the products, the trip legs — and the
 * plan, which is the most writing-heavy of them all, had none.
 *
 * This is the web face of Api\V2\TrainingPlanController. It reuses the same
 * TrainingPlanService, so the lifecycle (and the push that tells the client a
 * plan is waiting) is identical whichever door the trainer came through.
 *
 * Every query is scoped to the signed-in owner as `trainer_id`; a plan that is
 * not his 404s rather than 403s, for the same reason the API does it — a 403
 * would confirm that this client trains with this captain.
 */
class TrainingPlanController extends Controller
{
    public function __construct(private readonly TrainingPlanService $service)
    {
    }

    public function index(Request $request): View
    {
        $status = trim((string) $request->get('status', ''));

        $rows = TrainingPlan::query()
            ->where('trainer_id', $this->trainerId())
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->with('client:id,name,phone')
            ->withCount(['exercises', 'meals', 'bodyReports'])
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('business.training-plans.index', [
            'rows' => $rows,
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        return view('business.training-plans.create');
    }

    /**
     * Find the client by phone or e-mail — the two things a trainer standing in
     * front of him can actually ask for. Deliberately an exact match: a partial
     * search over every account on the platform is a people-finder, not a
     * client picker.
     */
    public function lookup(Request $request): JsonResponse
    {
        $term = trim((string) $request->get('q', ''));

        if ($term === '') {
            return response()->json(['found' => false]);
        }

        $client = User::query()
            ->where('type', User::TYPE_CLIENT)
            ->where(fn ($query) => $query->where('phone', $term)->orWhere('email', $term))
            ->first(['id', 'name', 'phone']);

        return response()->json($client
            ? ['found' => true, 'id' => (int) $client->id, 'name' => (string) $client->name, 'phone' => (string) $client->phone]
            : ['found' => false]);
    }

    public function store(Request $request): RedirectResponse
    {
        $trainerId = $this->trainerId();

        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:users,id', 'different:' . $trainerId],
            'title' => ['required', 'string', 'max:200'],
            'goal' => ['nullable', 'string', 'max:200'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $plan = $this->service->create(
            User::query()->findOrFail($trainerId),
            User::query()->findOrFail((int) $data['client_id']),
            [
                'title' => $data['title'],
                'goal' => $data['goal'] ?? null,
                'starts_on' => $data['starts_on'] ?? null,
                'ends_on' => $data['ends_on'] ?? null,
                'notes' => $data['notes'] ?? null,
            ],
            [],
            []
        );

        return redirect()
            ->route('business.training-plans.show', $plan->id)
            ->with('success', __('تم إنشاء الخطة. أضف التمارين والوجبات الآن.'));
    }

    public function show(int $id): View
    {
        $plan = $this->scoped($id)->load([
            'client:id,name,phone',
            'exercises',
            'exercises.images',
            'meals',
            'meals.images',
        ]);

        return view('business.training-plans.show', [
            'plan' => $plan,
            'reports' => $this->reportSeries($plan),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $plan = $this->scoped($id);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'goal' => ['nullable', 'string', 'max:200'],
            'ends_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in(TrainingPlan::STATUSES)],
        ]);

        $status = $data['status'] ?? null;
        unset($data['status']);

        $plan->update($data);

        // The service guards a finished plan from being revived; let it decide.
        if ($status !== null && $status !== $plan->status) {
            $this->service->setStatus($plan, $status);
        }

        return back()->with('success', __('تم حفظ الخطة.'));
    }

    public function addExercise(Request $request, int $id): RedirectResponse
    {
        $plan = $this->scoped($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'day_of_week' => ['nullable', 'integer', 'between:0,6'],
            'sets' => ['nullable', 'integer', 'min:0'],
            'reps' => ['nullable', 'string', 'max:40'],
            'rest_seconds' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $plan->exercises()->create($data + [
            'sort_order' => (int) $plan->exercises()->max('sort_order') + 1,
        ]);

        return back()->with('success', __('تمت إضافة التمرين.'));
    }

    public function removeExercise(int $id, int $exercise): RedirectResponse
    {
        // first()->delete() and not ->delete() on the query: a mass delete fires
        // no model events, so the owned photos would be left on disk forever.
        $this->scoped($id)->exercises()->where('id', $exercise)->first()?->delete();

        return back()->with('success', __('تم حذف التمرين.'));
    }

    public function addMeal(Request $request, int $id): RedirectResponse
    {
        $plan = $this->scoped($id);

        $data = $request->validate([
            'meal_type' => ['required', Rule::in(PlanMeal::TYPES)],
            'name' => ['required', 'string', 'max:200'],
            'calories' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $plan->meals()->create($data + [
            'sort_order' => (int) $plan->meals()->max('sort_order') + 1,
        ]);

        return back()->with('success', __('تمت إضافة الوجبة.'));
    }

    public function removeMeal(int $id, int $meal): RedirectResponse
    {
        $this->scoped($id)->meals()->where('id', $meal)->first()?->delete();

        return back()->with('success', __('تم حذف الوجبة.'));
    }

    /** The monthly measurement. Same rules as the API: one row per month. */
    public function storeReport(Request $request, int $id): RedirectResponse
    {
        $plan = $this->scoped($id);

        $data = $request->validate([
            'for_month' => ['nullable', 'date'],
            'weight_kg' => ['nullable', 'numeric', 'min:1', 'max:600'],
            'muscle_mass_kg' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'fat_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'water_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $measures = array_filter(
            array_intersect_key($data, array_flip(['weight_kg', 'muscle_mass_kg', 'fat_percent', 'water_percent'])),
            fn ($value) => $value !== null
        );

        if ($measures === []) {
            return back()->withErrors(['weight_kg' => __('سجّل قياسًا واحدًا على الأقل.')])->withInput();
        }

        $fat = $data['fat_percent'] ?? null;
        $water = $data['water_percent'] ?? null;

        if ($fat !== null && $water !== null && ($fat + $water) > 100) {
            return back()->withErrors(['fat_percent' => __('نسبة الدهون والمياه معًا لا تتجاوز ١٠٠٪.')])->withInput();
        }

        $month = BodyCompositionReport::monthOf($data['for_month'] ?? null);

        BodyCompositionReport::updateOrCreate(
            ['training_plan_id' => (int) $plan->id, 'for_month' => $month->toDateString()],
            $measures + [
                'client_id' => (int) $plan->client_id,
                'trainer_id' => (int) $plan->trainer_id,
                'measured_on' => $month->toDateString(),
                'notes' => $data['notes'] ?? null,
            ]
        );

        return back()->with('success', __('تم حفظ تقرير الشهر.'));
    }

    public function destroyReport(int $id, int $report): RedirectResponse
    {
        $this->scoped($id)->bodyReports()->where('id', $report)->delete();

        return back()->with('success', __('تم حذف التقرير.'));
    }

    /**
     * Newest first, each month carrying its signed change from the one before —
     * the same series the app shows, so the two faces never disagree.
     *
     * @return array<int, array{report: BodyCompositionReport, change: array<string, float|null>}>
     */
    private function reportSeries(TrainingPlan $plan): array
    {
        $ordered = $plan->bodyReports()->get()->sortBy('for_month')->values();
        $out = [];

        foreach ($ordered as $index => $report) {
            $out[] = [
                'report' => $report,
                'change' => $report->deltaFrom($index > 0 ? $ordered[$index - 1] : null),
            ];
        }

        return array_reverse($out);
    }

    private function scoped(int $id): TrainingPlan
    {
        return TrainingPlan::query()
            ->where('trainer_id', $this->trainerId())
            ->findOrFail($id);
    }

    private function trainerId(): int
    {
        return (int) Auth::id();
    }
}
