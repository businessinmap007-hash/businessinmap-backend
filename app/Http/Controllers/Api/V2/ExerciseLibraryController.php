<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\ExerciseCategory;
use App\Models\LibraryExercise;
use Illuminate\Http\Request;

/**
 * The exercise catalogue a trainer picks from when adding an exercise to a
 * plan or a template. Read-only here — the catalogue is curated in the admin
 * panel — and returned whole (a few hundred short rows) so the app can filter
 * and search it locally without a round trip per keystroke.
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

        $q = trim((string) ($data['q'] ?? ''));

        $exercises = LibraryExercise::query()->active()
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
            'exercises' => $exercises->map(fn (LibraryExercise $e) => [
                'id' => $e->id,
                'category_id' => $e->exercise_category_id,
                'name' => $e->label(),
                'kind' => $e->kind,
                'equipment' => $e->equipment,
                'default_sets' => $e->default_sets,
                'default_reps' => $e->default_reps,
            ])->values(),
        ]]);
    }
}
