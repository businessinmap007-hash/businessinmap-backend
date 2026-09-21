<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\ExerciseCategory;
use App\Models\LibraryExercise;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Curates the exercise catalogue trainers pick from (see ExerciseLibrarySeeder
 * for the starter set): sections, and the exercises inside them with their
 * kind, equipment and suggested sets/reps.
 */
class ExerciseLibraryAdminController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $categoryId = (int) $request->get('category_id', 0);

        $exercises = LibraryExercise::query()
            ->with('category:id,name_ar')
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('name_ar', 'like', "%{$q}%")->orWhere('name_en', 'like', "%{$q}%")))
            ->when($categoryId > 0, fn ($w) => $w->where('exercise_category_id', $categoryId))
            ->orderBy('exercise_category_id')->orderBy('sort_order')->orderBy('id')
            ->paginate(60)
            ->appends($request->query());

        return view('admin-v2.training.exercise-library', [
            'exercises' => $exercises,
            'categories' => ExerciseCategory::query()->withCount('exercises')->orderBy('sort_order')->orderBy('id')->get(),
            'q' => $q,
            'categoryId' => $categoryId,
            'kinds' => LibraryExercise::KINDS,
            'equipment' => LibraryExercise::EQUIPMENT,
        ]);
    }

    public function storeExercise(Request $request)
    {
        $data = $this->exerciseData($request);

        LibraryExercise::firstOrCreate(
            ['exercise_category_id' => $data['exercise_category_id'], 'name_ar' => $data['name_ar']],
            $data,
        );

        return back()->with('success', __('تمت إضافة التمرين'));
    }

    public function updateExercise(Request $request, LibraryExercise $exercise)
    {
        $data = $this->exerciseData($request);
        $data['is_active'] = $request->boolean('is_active');

        $exercise->update($data);

        return back()->with('success', __('تم حفظ التمرين'));
    }

    public function destroyExercise(LibraryExercise $exercise)
    {
        // Plans keep their own copy of the name/sets/reps; only the link nulls out.
        $exercise->delete();

        return back()->with('success', __('تم حذف التمرين'));
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => ['nullable', 'string', 'max:120'],
        ]);

        ExerciseCategory::firstOrCreate(
            ['name_ar' => trim($data['name_ar'])],
            ['name_en' => $data['name_en'] ?? null, 'sort_order' => (int) ExerciseCategory::max('sort_order') + 1],
        );

        return back()->with('success', __('تمت إضافة القسم'));
    }

    public function updateCategory(Request $request, ExerciseCategory $category)
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $category->update([
            'name_ar' => trim($data['name_ar']),
            'name_en' => $data['name_en'] ?? null,
            'sort_order' => $data['sort_order'] ?? $category->sort_order,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', __('تم حفظ القسم'));
    }

    public function destroyCategory(ExerciseCategory $category)
    {
        // Deleting a section would delete every exercise in it; refuse and say why.
        if ($category->exercises()->exists()) {
            return back()->withErrors(['category' => __('لا يمكن حذف قسم فيه تمارين. انقل التمارين أو عطّل القسم بدلاً من ذلك.')]);
        }

        $category->delete();

        return back()->with('success', __('تم حذف القسم'));
    }

    private function exerciseData(Request $request): array
    {
        $data = $request->validate([
            'exercise_category_id' => ['required', 'integer', Rule::exists('exercise_categories', 'id')],
            'name_ar' => ['required', 'string', 'max:160'],
            'name_en' => ['nullable', 'string', 'max:160'],
            'kind' => ['required', Rule::in(array_keys(LibraryExercise::KINDS))],
            'equipment' => ['nullable', Rule::in(array_keys(LibraryExercise::EQUIPMENT))],
            'default_sets' => ['nullable', 'integer', 'min:0', 'max:100'],
            'default_reps' => ['nullable', 'string', 'max:40'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $data['name_ar'] = trim($data['name_ar']);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
