<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryChild;
use App\Models\CategoryChildOption;
use App\Models\Option;
use App\Models\OptionGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CategoryChildOptionController extends Controller
{
    public function edit(Request $request, CategoryChild $categoryChild): View
    {
        $parentId = (int) $request->get('parent_id', 0);
        $q = trim((string) $request->get('q', ''));

        // =========================
        // GROUPS + OPTIONS
        // =========================
        $groups = OptionGroup::query()
            ->where('is_active', 1)
            ->with([
                'options' => function ($query) use ($q) {
                    $query
                        ->when($this->hasIsActiveColumn(), fn ($sub) => $sub->where('is_active', 1))
                        ->when($q !== '', function ($sub) use ($q) {
                            $sub->where(function ($w) use ($q) {
                                $w->where('name_ar', 'like', "%{$q}%")
                                ->orWhere('name_en', 'like', "%{$q}%");
                            });
                        })
                        ->orderBy('id', 'asc');
                }
            ])
            ->orderBy('reorder')
            ->orderBy('id')
            ->get(['id', 'name_ar', 'name_en', 'reorder'])
            ->map(function ($group) {
                $group->options = collect($group->options)->values();
                return $group;
            })
            ->filter(fn ($group) => $group->options->isNotEmpty()) // 🔥 مهم
            ->values();

        // =========================
        // SELECTED OPTIONS
        // =========================
        $selectedOptionIds = CategoryChildOption::query()
            ->where('child_id', $categoryChild->id)
            ->orderBy('reorder')
            ->orderBy('id')
            ->pluck('option_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        // =========================
        // UNGROUPED OPTIONS
        // =========================
        $ungroupedOptions = Option::query()
            ->when($this->hasIsActiveColumn(), fn ($query) => $query->where('is_active', 1))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('name_ar', 'like', "%{$q}%")
                    ->orWhere('name_en', 'like', "%{$q}%");
                });
            })
            ->whereNull('group_id')
            ->orderBy('id', 'asc')
            ->get(['id', 'name_ar', 'name_en', 'group_id']);

        // =========================
        // CHILD LOAD
        // =========================
        $categoryChild->load([
            'parents:id,name_ar,name_en',
        ]);

        // =========================
        // PARENT
        // =========================
        $parent = null;
        if ($parentId > 0) {
            $parent = Category::query()
                ->where('parent_id', 0)
                ->find($parentId, ['id', 'name_ar', 'name_en']);
        }

        return view('admin-v2.category-children.options.edit', [
            'categoryChild' => $categoryChild,
            'groups' => $groups,
            'selectedOptionIds' => $selectedOptionIds,
            'ungroupedOptions' => $ungroupedOptions,
            'parentId' => $parentId,
            'parent' => $parent,
            'q' => $q,
        ]);
    }

    public function update(Request $request, CategoryChild $categoryChild): RedirectResponse
    {
        $data = $request->validate([
            'rows' => ['nullable', 'array'],
            'rows.*.option_id' => ['required', 'integer', 'exists:options,id'],
            'rows.*.reorder' => ['nullable', 'integer', 'min:0'],
            'parent_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $rows = collect($data['rows'] ?? [])
            ->map(function ($row, $index) use ($categoryChild) {
                $optionId = (int) ($row['option_id'] ?? 0);
                $reorder = (int) ($row['reorder'] ?? $index);

                if ($optionId <= 0) {
                    return null;
                }

                return [
                    'child_id' => $categoryChild->id,
                    'option_id' => $optionId,
                    'reorder' => max(0, $reorder),
                ];
            })
            ->filter()
            ->unique('option_id')
            ->values();

        DB::transaction(function () use ($categoryChild, $rows) {
            CategoryChildOption::query()
                ->where('child_id', $categoryChild->id)
                ->delete();

            if ($rows->isNotEmpty()) {
                CategoryChildOption::query()->insert($rows->all());
            }
        });

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
            $currentLinks = CategoryChildOption::query()
                ->where('child_id', $child->id)
                ->get(['option_id', 'reorder']);

            $currentIds = $currentLinks
                ->pluck('option_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($data['mode'] === 'replace') {
                CategoryChildOption::query()
                    ->where('child_id', $child->id)
                    ->delete();

                foreach ($optionIds as $index => $optionId) {
                    CategoryChildOption::query()->create([
                        'child_id' => $child->id,
                        'option_id' => (int) $optionId,
                        'reorder' => $index,
                    ]);
                }

                continue;
            }

            if ($data['mode'] === 'append') {
                $toAdd = collect($optionIds)
                    ->reject(fn ($id) => in_array((int) $id, $currentIds, true))
                    ->values()
                    ->all();

                $start = ((int) $currentLinks->max('reorder')) + 1;

                foreach ($toAdd as $i => $optionId) {
                    CategoryChildOption::query()->create([
                        'child_id' => $child->id,
                        'option_id' => (int) $optionId,
                        'reorder' => $start + $i,
                    ]);
                }

                continue;
            }

            if ($data['mode'] === 'remove' && !empty($optionIds)) {
                CategoryChildOption::query()
                    ->where('child_id', $child->id)
                    ->whereIn('option_id', $optionIds)
                    ->delete();
            }
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