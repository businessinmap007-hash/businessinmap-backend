<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryChild;
use App\Models\CategoryChildOption;
use App\Models\CategoryChildOptionGroup;
use App\Models\Option;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CategoryChildOptionController extends Controller
{
    public function edit(Request $request, CategoryChild $categoryChild): View
    {
        $qAvailable = trim((string) $request->get('q_available', ''));
        $qSelected  = trim((string) $request->get('q_selected', ''));
        $parentId   = (int) $request->get('parent_id', 0);
        $groupId    = (int) $request->get('group_id', 0);

        $groups = $categoryChild->optionGroups()
            ->get(['id', 'child_id', 'name_ar', 'name_en', 'reorder', 'is_active']);

        if ($groups->isEmpty()) {
            $groups = collect([
                CategoryChildOptionGroup::create([
                    'child_id'   => $categoryChild->id,
                    'name_ar'    => 'عام',
                    'name_en'    => 'General',
                    'reorder'    => 0,
                    'is_active'  => 1,
                ])
            ]);
        }

        $defaultGroupId = (int) ($groups->first()->id ?? 0);

        $assignedOptionIds = CategoryChildOption::query()
            ->where('child_id', $categoryChild->id)
            ->pluck('option_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $selectedLinks = CategoryChildOption::query()
            ->with([
                'option:id,name_ar,name_en',
                'group:id,name_ar,name_en',
            ])
            ->where('child_id', $categoryChild->id)
            ->when($groupId > 0, fn ($q) => $q->where('group_id', $groupId))
            ->when($qSelected !== '', function ($query) use ($qSelected) {
                $query->whereHas('option', function ($w) use ($qSelected) {
                    $w->where('name_ar', 'like', "%{$qSelected}%")
                      ->orWhere('name_en', 'like', "%{$qSelected}%");
                });
            })
            ->orderBy('group_id')
            ->orderBy('reorder')
            ->orderBy('id')
            ->get();

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
            'categoryChild'    => $categoryChild,
            'availableOptions' => $availableOptions,
            'selectedLinks'    => $selectedLinks,
            'groups'           => $groups,
            'defaultGroupId'   => $defaultGroupId,
            'currentGroupId'   => $groupId,
            'qAvailable'       => $qAvailable,
            'qSelected'        => $qSelected,
            'parentId'         => $parentId,
            'parent'           => $parent,
        ]);
    }

    public function update(Request $request, CategoryChild $categoryChild): RedirectResponse
    {
        $data = $request->validate([
            'rows'                 => ['nullable', 'array'],
            'rows.*.option_id'     => ['required', 'integer', 'exists:options,id'],
            'rows.*.group_id'      => ['nullable', 'integer', 'exists:category_child_option_groups,id'],
            'rows.*.reorder'       => ['nullable', 'integer', 'min:0'],
            'parent_id'            => ['nullable', 'integer', 'min:0'],
            'group_id'             => ['nullable', 'integer', 'min:0'],
        ]);

        $rows = collect($data['rows'] ?? [])
            ->map(function ($row) use ($categoryChild) {
                $optionId = (int) ($row['option_id'] ?? 0);
                $groupId  = (int) ($row['group_id'] ?? 0);
                $reorder  = (int) ($row['reorder'] ?? 0);

                if ($optionId <= 0) {
                    return null;
                }

                if ($groupId <= 0 || !CategoryChildOptionGroup::query()
                    ->where('id', $groupId)
                    ->where('child_id', $categoryChild->id)
                    ->exists()) {
                    $groupId = $this->getOrCreateDefaultGroupId($categoryChild);
                }

                return [
                    'child_id'  => $categoryChild->id,
                    'option_id' => $optionId,
                    'group_id'  => $groupId,
                    'reorder'   => max(0, $reorder),
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

        if (!empty($data['group_id'])) {
            $routeParams['group_id'] = (int) $data['group_id'];
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
                ->get(['option_id', 'group_id', 'reorder']);

            $currentIds = $currentLinks
                ->pluck('option_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $defaultGroupId = $this->getOrCreateDefaultGroupId($child);

            if ($data['mode'] === 'replace') {
                CategoryChildOption::query()
                    ->where('child_id', $child->id)
                    ->delete();

                foreach ($optionIds as $index => $optionId) {
                    CategoryChildOption::query()->create([
                        'child_id'  => $child->id,
                        'option_id' => (int) $optionId,
                        'group_id'  => $defaultGroupId,
                        'reorder'   => $index,
                    ]);
                }

                continue;
            }

            if ($data['mode'] === 'append') {
                $toAdd = collect($optionIds)
                    ->reject(fn ($id) => in_array((int) $id, $currentIds, true))
                    ->values()
                    ->all();

                $start = (int) $currentLinks->max('reorder') + 1;

                foreach ($toAdd as $i => $optionId) {
                    CategoryChildOption::query()->create([
                        'child_id'  => $child->id,
                        'option_id' => (int) $optionId,
                        'group_id'  => $defaultGroupId,
                        'reorder'   => $start + $i,
                    ]);
                }

                continue;
            }

            if ($data['mode'] === 'remove') {
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

    protected function getOrCreateDefaultGroupId(CategoryChild $categoryChild): int
    {
        $group = CategoryChildOptionGroup::query()
            ->where('child_id', $categoryChild->id)
            ->orderBy('reorder')
            ->orderBy('id')
            ->first();

        if ($group) {
            return (int) $group->id;
        }

        $group = CategoryChildOptionGroup::query()->create([
            'child_id'  => $categoryChild->id,
            'name_ar'   => 'عام',
            'name_en'   => 'General',
            'reorder'   => 0,
            'is_active' => 1,
        ]);

        return (int) $group->id;
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