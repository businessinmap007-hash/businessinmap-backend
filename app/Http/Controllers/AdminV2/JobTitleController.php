<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryChild;
use App\Models\JobTitle;
use Illuminate\Http\Request;

/**
 * The closed list of job titles a business picks from when it posts a
 * vacancy (see JobTitlesSeeder for the starter set). One scope per title:
 * general (any business), a whole root, or one child.
 */
class JobTitleController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $scope = (string) $request->get('scope', '');

        $titles = JobTitle::query()
            ->with(['category:id,name_ar', 'categoryChild:id,name_ar'])
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('name_ar', 'like', "%{$q}%")->orWhere('name_en', 'like', "%{$q}%")))
            ->when($scope !== '', function ($w) use ($scope) {
                [$kind, $id] = $this->parseScope($scope);
                match ($kind) {
                    'g' => $w->whereNull('category_id')->whereNull('category_child_id'),
                    'r' => $w->where('category_id', $id)->whereNull('category_child_id'),
                    'c' => $w->where('category_child_id', $id),
                    default => null,
                };
            })
            ->orderByRaw('category_child_id is null')->orderBy('category_child_id')->orderBy('sort_order')->orderBy('id')
            ->paginate(60)
            ->appends($request->query());

        return view('admin-v2.jobs.titles', [
            'titles' => $titles,
            'q' => $q,
            'scope' => $scope,
            'roots' => Category::query()->orderBy('name_ar')->get(['id', 'name_ar']),
            'children' => CategoryChild::query()->orderBy('name_ar')->get(['id', 'name_ar']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'scope' => ['required', 'string', 'max:20'],
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => ['nullable', 'string', 'max:120'],
        ]);

        [$kind, $id] = $this->parseScope($data['scope']);

        abort_unless(in_array($kind, ['g', 'r', 'c'], true), 422);

        JobTitle::firstOrCreate([
            'category_id' => $kind === 'r' ? $id : null,
            'category_child_id' => $kind === 'c' ? $id : null,
            'name_ar' => trim($data['name_ar']),
        ], ['name_en' => $data['name_en'] ?? null]);

        return back()->with('success', __('تمت إضافة المسمى'));
    }

    public function update(Request $request, JobTitle $jobTitle)
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $jobTitle->update([
            'name_ar' => trim($data['name_ar']),
            'name_en' => $data['name_en'] ?? null,
            'sort_order' => $data['sort_order'] ?? $jobTitle->sort_order,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', __('تم حفظ المسمى'));
    }

    public function destroy(JobTitle $jobTitle)
    {
        // Jobs already posted keep their text title; the FK just nulls out.
        $jobTitle->delete();

        return back()->with('success', __('تم حذف المسمى'));
    }

    /** "g" | "r:12" | "c:245" → [kind, id]. */
    private function parseScope(string $scope): array
    {
        $parts = explode(':', $scope, 2);

        return [$parts[0], isset($parts[1]) ? (int) $parts[1] : null];
    }
}
