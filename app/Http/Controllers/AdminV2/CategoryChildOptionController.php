<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryChild;
use App\Models\Option;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CategoryChildOptionController extends Controller
{
    public function edit(Request $request, CategoryChild $categoryChild): View
    {
        $qAvailable = trim((string) $request->get('q_available', ''));
        $qSelected = trim((string) $request->get('q_selected', ''));
        $parentId = (int) $request->get('parent_id', 0);

        $assignedOptionIds = $categoryChild->options()
            ->pluck('options.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $selectedOptions = Option::query()
            ->when($this->hasIsActiveColumn(), fn ($q) => $q->where('is_active', 1))
            ->whereIn('id', !empty($assignedOptionIds) ? $assignedOptionIds : [0])
            ->when($qSelected !== '', function ($query) use ($qSelected) {
                $query->where(function ($w) use ($qSelected) {
                    $w->where('name_ar', 'like', "%{$qSelected}%")
                      ->orWhere('name_en', 'like', "%{$qSelected}%");
                });
            })
            ->ordered()
            ->get(['id', 'name_ar', 'name_en']);

        $availableOptions = Option::query()
            ->when($this->hasIsActiveColumn(), fn ($q) => $q->where('is_active', 1))
            ->whereNotIn('id', !empty($assignedOptionIds) ? $assignedOptionIds : [0])
            ->when($qAvailable !== '', function ($query) use ($qAvailable) {
                $query->where(function ($w) use ($qAvailable) {
                    $w->where('name_ar', 'like', "%{$qAvailable}%")
                      ->orWhere('name_en', 'like', "%{$qAvailable}%");
                });
            })
            ->ordered()
            ->get(['id', 'name_ar', 'name_en']);

        $categoryChild->load([
            'parents:id,name_ar,name_en',
        ]);

        $parent = null;
        if ($parentId > 0) {
            $parent = Category::query()
                ->where('parent_id', 0)
                ->find($parentId, ['id', 'name_ar', 'name_en']);
        }

        return view('admin-v2.category-children.options.edit', [
            'categoryChild' => $categoryChild,
            'availableOptions' => $availableOptions,
            'selectedOptions' => $selectedOptions,
            'qAvailable' => $qAvailable,
            'qSelected' => $qSelected,
            'parentId' => $parentId,
            'parent' => $parent,
        ]);
    }

    public function update(Request $request, CategoryChild $categoryChild): RedirectResponse
    {
        $data = $request->validate([
            'option_ids' => ['nullable', 'array'],
            'option_ids.*' => ['integer', 'exists:options,id'],
            'parent_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $optionIds = collect($data['option_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $categoryChild->options()->sync($optionIds);

        $routeParams = [
            'categoryChild' => $categoryChild->id,
        ];

        if (!empty($data['parent_id'])) {
            $routeParams['parent_id'] = (int) $data['parent_id'];
        }

        return redirect()
            ->route('admin.category-child-options.edit', $routeParams)
            ->with('success', 'تم تحديث خيارات القسم الفرعي بنجاح.');
    }

    public function bulkEdit(Request $request): View
    {
        $parentId = (int) $request->get('parent_id', 0);

        $childIds = collect($request->get('child_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($childIds)) {
            abort(404);
        }

        $children = CategoryChild::query()
            ->with('parents:id,name_ar,name_en')
            ->whereIn('id', $childIds)
            ->orderByRaw('COALESCE(reorder, 999999) ASC')
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get(['id', 'name_ar', 'name_en', 'reorder']);

        $options = Option::query()
            ->when($this->hasIsActiveColumn(), fn ($q) => $q->where('is_active', 1))
            ->ordered()
            ->get(['id', 'name_ar', 'name_en']);

        $parent = null;
        if ($parentId > 0) {
            $parent = Category::query()
                ->where('parent_id', 0)
                ->find($parentId, ['id', 'name_ar', 'name_en']);
        }

        return view('admin-v2.category-children.options.bulk', [
            'children' => $children,
            'options' => $options,
            'parentId' => $parentId,
            'parent' => $parent,
        ]);
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'child_ids' => ['required', 'array', 'min:1'],
            'child_ids.*' => ['integer', 'exists:category_children_master,id'],

            'option_ids' => ['nullable', 'array'],
            'option_ids.*' => ['integer', 'exists:options,id'],

            'mode' => ['required', 'in:append,replace,remove'],
            'parent_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $childIds = collect($data['child_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $optionIds = collect($data['option_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $children = CategoryChild::query()
            ->whereIn('id', $childIds)
            ->get(['id']);

        foreach ($children as $child) {
            $currentIds = $child->options()
                ->pluck('options.id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $finalIds = match ($data['mode']) {
                'append' => collect(array_merge($currentIds, $optionIds))->unique()->values()->all(),
                'replace' => $optionIds,
                'remove' => collect($currentIds)
                    ->reject(fn ($id) => in_array((int) $id, $optionIds, true))
                    ->values()
                    ->all(),
                default => $currentIds,
            };

            $child->options()->sync($finalIds);
        }

        $routeParams = [];
        if (!empty($data['parent_id'])) {
            $routeParams['parent_id'] = (int) $data['parent_id'];
        }

        return redirect()
            ->route('admin.category-children.index', $routeParams)
            ->with('success', 'تم تحديث خيارات الأقسام الفرعية المحددة بنجاح.');
    }

    protected function hasIsActiveColumn(): bool
    {
        static $hasColumn = null;

        if ($hasColumn !== null) {
            return $hasColumn;
        }

        $hasColumn = Schema::hasColumn('options', 'is_active');

        return $hasColumn;
    }
}