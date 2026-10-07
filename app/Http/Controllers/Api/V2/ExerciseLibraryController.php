<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\ExerciseCategory;
use App\Models\LibraryExercise;
use App\Support\BusinessContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The exercise catalogue a trainer or a rehabilitation specialist picks from when adding an exercise to a plan or a
 * template. The shared catalogue is curated by the platform and read-only here; an exercise the specialist adds is THEIR
 * own — visible to them alone, theirs to correct and delete («مع امكانية اضافة من المتخصص لنوع تمرين»).
 *
 * Returned whole (a few hundred short rows) so the app can filter and search it locally without a round trip per
 * keystroke.
 */
final class ExerciseLibraryController extends Controller
{
    /**
     * GET /api/v2/business/training/exercise-library
     *
     * Optional narrowing: category_id, kind, equipment, q (name search).
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'category_id' => ['nullable', 'integer', 'min:1'],
            'kind' => ['nullable', 'string', 'in:' . implode(',', array_keys(LibraryExercise::KINDS))],
            'equipment' => ['nullable', 'string', 'in:' . implode(',', array_keys(LibraryExercise::EQUIPMENT))],
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $me = BusinessContext::id($request);
        $q = trim((string) ($data['q'] ?? ''));

        $exercises = LibraryExercise::query()->active()->visibleTo($me)->with('images')
            ->whereHas('category', fn ($c) => $c->where('is_active', true))
            ->when(! empty($data['category_id']), fn ($w) => $w->where('exercise_category_id', $data['category_id']))
            ->when(! empty($data['kind']), fn ($w) => $w->where('kind', $data['kind']))
            ->when(! empty($data['equipment']), fn ($w) => $w->where('equipment', $data['equipment']))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('name_ar', 'like', "%{$q}%")->orWhere('name_en', 'like', "%{$q}%")))
            ->orderBy('exercise_category_id')->orderBy('sort_order')->orderBy('id')
            ->get();

        $categories = ExerciseCategory::query()->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')->get();

        return response()->json(['success' => true, 'data' => [
            'categories' => $categories->map(fn (ExerciseCategory $c) => [
                'id' => $c->id,
                'name' => $c->label(),
            ])->values(),
            'kinds' => LibraryExercise::labels(LibraryExercise::KINDS),
            'equipment' => LibraryExercise::labels(LibraryExercise::EQUIPMENT),
            'exercises' => $exercises->map(fn (LibraryExercise $e) => $this->serialize($e, $me))->values(),
        ]]);
    }

    /** POST /api/v2/business/training/exercise-library — the specialist's own exercise. */
    public function store(Request $request)
    {
        $data = $this->validated($request);
        $me = BusinessContext::id($request);

        $exercise = LibraryExercise::query()->create($data + [
            'owner_id' => $me,
            'sort_order' => 1 + (int) LibraryExercise::query()->where('owner_id', $me)->max('sort_order'),
            'is_active' => true,
        ]);

        return response()->json(['success' => true, 'data' => ['exercise' => $this->serialize($exercise->load('images'), $me)]], 201);
    }

    /** PUT /api/v2/business/training/exercise-library/{exercise} — only one's own. */
    public function update(Request $request, int $exercise)
    {
        $row = $this->ownedOrFail($request, $exercise);
        $data = $this->validated($request);

        // an edit that does not mention the kind keeps the one the exercise has
        if (! $request->filled('kind')) {
            unset($data['kind']);
        }

        $row->update($data);

        return response()->json(['success' => true, 'data' => ['exercise' => $this->serialize($row->fresh('images'), BusinessContext::id($request))]]);
    }

    /** DELETE /api/v2/business/training/exercise-library/{exercise} — only one's own; a plan keeps the exercise it already has. */
    public function destroy(Request $request, int $exercise)
    {
        $this->ownedOrFail($request, $exercise)->delete();

        return response()->json(['success' => true, 'message' => __('تم حذف التمرين.')]);
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'exercise_category_id' => ['required', 'integer', 'exists:exercise_categories,id'],
            'name_ar' => ['required', 'string', 'max:160'],
            'name_en' => ['nullable', 'string', 'max:160'],
            'kind' => ['nullable', Rule::in(array_keys(LibraryExercise::KINDS))],
            'equipment' => ['nullable', Rule::in(array_keys(LibraryExercise::EQUIPMENT))],
            'default_sets' => ['nullable', 'integer', 'min:1', 'max:20'],
            'default_reps' => ['nullable', 'string', 'max:40'],
            'instructions' => ['nullable', 'string', 'max:255'],
        ]) + ['kind' => 'rehab'];
    }

    private function ownedOrFail(Request $request, int $id): LibraryExercise
    {
        return LibraryExercise::query()->where('id', $id)->where('owner_id', BusinessContext::id($request))->firstOrFail();
    }

    /** @return array<string,mixed> */
    private function serialize(LibraryExercise $e, int $me): array
    {
        return [
            'id' => $e->id,
            'category_id' => $e->exercise_category_id,
            'name' => $e->label(),
            'kind' => $e->kind,
            'equipment' => $e->equipment,
            'default_sets' => $e->default_sets,
            'default_reps' => $e->default_reps,
            'instructions' => $e->instructions,
            'images' => $e->images->pluck('image')->values()->all(),
            // a specialist's own entry — the app lets them edit and delete it
            'mine' => $e->owner_id !== null && (int) $e->owner_id === $me,
        ];
    }
}
