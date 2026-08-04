<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryChild;
use App\Models\CategoryChildOption;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Services\CategoryChildOptionScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CategoryChildOptionController extends Controller
{
    public function __construct(private readonly CategoryChildOptionScope $scope)
    {
    }

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
        // Scoped to the root when the screen was opened from one: the same child
        // carries a different set under each root it sits beneath.
        $selectedOptionIds = CategoryChildOption::query()
            ->where('child_id', $categoryChild->id)
            ->when($parentId > 0, fn ($q) => $q->whereIn('category_id', [CategoryChildOption::ALL_ROOTS, $parentId]))
            ->orderBy('reorder')
            ->orderBy('id')
            ->pluck('option_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
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

        $parentId = (int) ($data['parent_id'] ?? 0);

        $optionIds = collect($data['rows'] ?? [])
            ->sortBy(fn ($row, $index) => (int) ($row['reorder'] ?? $index))
            ->map(fn ($row) => (int) ($row['option_id'] ?? 0))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        // Saves the set for THIS root when one is in hand. It used to delete
        // every row of the child and rewrite it, which — now that a child may
        // answer differently under each root — would flatten the other roots'
        // work on every save. With no root the whole child is replaced, exactly
        // as before.
        $this->scope->syncFor($categoryChild->id, $parentId, $optionIds, [], true);

        $routeParams = [
            'categoryChild' => $categoryChild->id,
        ];

        if (!empty($data['parent_id'])) {
            $routeParams['parent_id'] = (int) $data['parent_id'];
        }

        return redirect()
            ->route('admin.category-child-options.edit', $routeParams)
            ->with('success', __('تم تحديث خيارات القسم الفرعي بنجاح.'));
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

        /*
        |--------------------------------------------------------------------------
        | Root Categories + Children
        |--------------------------------------------------------------------------
        */
        $roots = Category::query()
            ->where('parent_id', 0)
            ->with([
                'children' => function ($q) use ($childIds) {
                    $q->select('category_children_master.id', 'name_ar', 'name_en', 'reorder')
                        ->when(! empty($childIds), function ($sub) use ($childIds) {
                            $sub->whereIn('category_children_master.id', $childIds);
                        })
                        ->orderByRaw('COALESCE(category_children_master.reorder, 999999) ASC')
                        ->orderBy('category_children_master.name_ar')
                        ->orderBy('category_children_master.id');
                },
            ])
            ->orderByRaw('COALESCE(reorder, 999999) ASC')
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get(['id', 'name_ar', 'name_en', 'reorder'])
            ->filter(fn ($root) => $root->children->isNotEmpty())
            ->values();

        $activeRootId = $parentId > 0
            ? $parentId
            : (int) optional($roots->first())->id;

        $parent = null;
        if ($activeRootId > 0) {
            $parent = Category::query()
                ->where('parent_id', 0)
                ->find($activeRootId, ['id', 'name_ar', 'name_en']);
        }

        /*
        |--------------------------------------------------------------------------
        | Option Groups + Options
        |--------------------------------------------------------------------------
        */
        $optionGroups = OptionGroup::query()
            ->where('is_active', 1)
            ->with([
                'options' => function ($q) {
                    $q->select('id', 'group_id', 'name_ar', 'name_en')
                        ->when($this->hasIsActiveColumn(), fn ($sub) => $sub->where('is_active', 1))
                        ->orderBy('id');
                },
            ])
            ->orderByRaw('COALESCE(reorder, 999999) ASC')
            ->orderBy('id')
            ->get(['id', 'name_ar', 'name_en', 'reorder'])
            ->filter(fn ($group) => $group->options->isNotEmpty())
            ->values();

        $ungroupedOptions = Option::query()
            ->when($this->hasIsActiveColumn(), fn ($q) => $q->where('is_active', 1))
            ->whereNull('group_id')
            ->orderBy('id')
            ->get(['id', 'name_ar', 'name_en', 'group_id']);

        return view('admin-v2.category-children.options.bulk', [
            'roots' => $roots,
            'optionGroups' => $optionGroups,
            'ungroupedOptions' => $ungroupedOptions,
            'parentId' => $activeRootId,
            'parent' => $parent,
            'selectedChildIds' => $childIds,
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

        // The bulk edit is launched from a root, and every write below is scoped
        // to it: appending under معارض must not also grant the option under
        // مصانع, and replacing under one root must not wipe the others.
        $parentId = (int) ($data['parent_id'] ?? 0);

        $children = CategoryChild::query()
            ->whereIn('id', $childIds)
            ->get(['id']);

        foreach ($children as $child) {
            $childId = (int) $child->id;

            if ($data['mode'] === 'replace') {
                $this->scope->syncFor($childId, $parentId, $optionIds, [], true);

                continue;
            }

            if ($data['mode'] === 'append') {
                $this->scope->grantFor($childId, $parentId, $optionIds);

                continue;
            }

            if ($data['mode'] === 'remove' && ! empty($optionIds)) {
                $this->scope->revokeFor($childId, $parentId, $optionIds);
            }
        }

        $routeParams = [];
        if (!empty($data['parent_id'])) {
            $routeParams['parent_id'] = (int) $data['parent_id'];
        }

        return redirect()
            ->route('admin.category-children.index', $routeParams)
            ->with('success', __('تم تحديث خيارات الأقسام الفرعية المحددة بنجاح.'));
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