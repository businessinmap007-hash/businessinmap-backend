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
            ->inDisplayOrder()
            ->get(['id', 'name_ar', 'name_en', 'reorder', 'price_role'])
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
                // `child_ids` PRE-SELECTS, it does not filter. It used to hide
                // every other child, which after a save left the admin locked
                // inside the set he had just finished with and unable to reach
                // the next one without clearing the URL by hand.
                'children' => function ($q) {
                    $q->select('category_children_master.id', 'name_ar', 'name_en', 'reorder')
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

        /*
        | The active root must be one of the roots this screen actually DREW.
        |
        | Arriving with `?parent_id=` naming a root the screen did not draw left
        | no panel open. Every child checkbox rendered `disabled`, the browser
        | therefore submitted no `child_ids`, and the save could only ever answer
        | «حقل الأقسام الفرعية مطلوب» — a page with nothing tickable on it and no
        | way to say so.
        */
        $available = $roots->pluck('id')->map(fn ($id) => (int) $id);

        $activeRootId = $available->contains($parentId)
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
        // price_role travels with the group because it is what tells an admin
        // WHERE the group surfaces: a line group is offered to the merchant and
        // becomes a priced booking row, a descriptive one only ever filters a
        // search. Ticking «الغرف» and ticking «مرافق الإقامة» look identical on
        // this screen and mean two entirely different things downstream.
        $optionGroups = OptionGroup::query()
            ->where('is_active', 1)
            ->with([
                'options' => function ($q) {
                    $q->select('id', 'group_id', 'name_ar', 'name_en')
                        ->when($this->hasIsActiveColumn(), fn ($sub) => $sub->where('is_active', 1))
                        ->orderBy('id');
                },
            ])
            // Same order the customer meets them in, so the screen an admin
            // curates and the list a customer filters by cannot disagree.
            ->inDisplayOrder()
            ->get(['id', 'name_ar', 'name_en', 'reorder', 'price_role'])
            ->filter(fn ($group) => $group->options->isNotEmpty())
            ->values();

        $ungroupedOptions = Option::query()
            ->when($this->hasIsActiveColumn(), fn ($q) => $q->where('is_active', 1))
            ->whereNull('group_id')
            ->orderBy('id')
            ->get(['id', 'name_ar', 'name_en', 'group_id']);

        $childOptionMap = $this->childOptionMap($roots);

        return view('admin-v2.category-children.options.bulk', [
            'roots' => $roots,
            'optionGroups' => $optionGroups,
            'ungroupedOptions' => $ungroupedOptions,
            'parentId' => $activeRootId,
            'parent' => $parent,
            'selectedChildIds' => $childIds,
            'optionIndex' => $this->optionIndex($optionGroups, $ungroupedOptions, $childOptionMap),
            'childOptionMap' => $childOptionMap,
        ]);
    }

    /**
     * Three ways to write, and «استبدال بالكامل» is the one that can withdraw.
     *
     * The screen used to open with every option unticked whatever the children
     * already carried, so there was nothing to untick — withdrawing meant
     * switching to «حذف المحدد» and ticking what should GO, where a tick means
     * the opposite of a tick two modes above. The view now seeds the boxes from
     * the child's real set when exactly one child is picked, so replace mode
     * reads as «this is what it has» and unticking removes.
     *
     * An empty `option_ids` is therefore meaningful and must not be rejected:
     * a browser sends nothing at all for a set of cleared checkboxes, and that
     * is exactly how «withdraw everything» arrives here.
     */
    public function bulkUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'child_ids' => ['required', 'array', 'min:1'],
            'child_ids.*' => ['integer', 'exists:category_children_master,id'],
            'option_ids' => ['nullable', 'array'],
            'option_ids.*' => ['integer', 'exists:options,id'],
            'mode' => ['required', 'in:append,replace,remove'],
            'parent_id' => ['nullable', 'integer', 'min:0'],
        ], [
            'child_ids.required' => __('اختر قسمًا فرعيًا واحدًا على الأقل قبل الحفظ.'),
        ]);

        $ids = fn (string $key) => collect($data[$key] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $childIds = $ids('child_ids');
        $optionIds = $ids('option_ids');

        // The bulk edit is launched from a root, and every write below is scoped
        // to it: appending under معارض must not also grant the option under
        // مصانع, and replacing under one root must not wipe the others.
        $parentId = (int) ($data['parent_id'] ?? 0);

        $children = CategoryChild::query()->whereIn('id', $childIds)->get(['id']);

        $added = 0;
        $removed = 0;

        foreach ($children as $child) {
            $childId = (int) $child->id;

            if ($data['mode'] === 'replace') {
                $result = $this->scope->syncFor($childId, $parentId, $optionIds, [], true);
                $added += (int) $result['added'];
                $removed += (int) $result['removed'];

                continue;
            }

            if ($data['mode'] === 'append') {
                $added += $this->scope->grantFor($childId, $parentId, $optionIds);

                continue;
            }

            if ($data['mode'] === 'remove' && $optionIds !== []) {
                $removed += (int) ($this->scope->revokeFor($childId, $parentId, $optionIds)['removed'] ?? 0);
            }
        }

        // «اريد عند الحفظ ان يظل فى نفس الصفحة» — owner, 2026-08-09. Curating a
        // child's options is a walk down a list, and being thrown to the
        // children index after each save made one save the end of the session.
        // The root and the selection come back with it, so the next child is one
        // click away rather than four.
        $routeParams = ['child_ids' => $childIds];

        if ($parentId > 0) {
            $routeParams['parent_id'] = $parentId;
        }

        return redirect()
            ->route('admin.category-child-options.bulk.edit', $routeParams)
            ->with('success', __('تم الحفظ: :added إضافة و :removed إزالة على :children قسمًا.', [
                'added' => $added,
                'removed' => $removed,
                'children' => count($childIds),
            ]));
    }

    /**
     * option id => [name, group name, price role], for the screen to name an
     * option it is not currently rendering.
     *
     * The bulk screen draws every option once, inside its group. Telling an
     * admin what a CHILD already carries means naming options scattered across
     * forty collapsed groups, so the names travel as a flat index and the panel
     * is built from ids. 530 options is a few kilobytes; re-querying per child
     * would be forty round trips.
     *
     * @param  \Illuminate\Support\Collection<int,\App\Models\OptionGroup>  $groups
     * @param  \Illuminate\Support\Collection<int,\App\Models\Option>  $ungrouped
     * @param  array{shared:array,scoped:array}  $childOptions
     * @return array<int,array{0:string,1:string,2:string}>
     */
    private function optionIndex($groups, $ungrouped, array $childOptions): array
    {
        $index = [];

        $name = fn ($row) => (string) ($row->name_ar ?: $row->name_en ?: ('#' . $row->id));

        foreach ($groups as $group) {
            foreach ($group->options as $option) {
                $index[(int) $option->id] = [$name($option), $name($group), (string) $group->price_role];
            }
        }

        foreach ($ungrouped as $option) {
            $index[(int) $option->id] = [$name($option), (string) __('بدون جروب'), ''];
        }

        // The picker only draws ACTIVE groups, but deactivating a group does not
        // withdraw it from the children already carrying its options. Those have
        // to be nameable too, or a child's panel would count more than it lists
        // — and the option nobody can see is exactly the one worth seeing.
        $referenced = collect($childOptions['shared'] ?? [])
            ->merge(collect($childOptions['scoped'] ?? [])->flatMap(fn ($byChild) => $byChild))
            ->flatten()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn ($id) => isset($index[$id]));

        if ($referenced->isEmpty()) {
            return $index;
        }

        foreach (
            Option::query()
                ->leftJoin('option_groups as g', 'g.id', '=', 'options.group_id')
                ->whereIn('options.id', $referenced->all())
                ->get([
                    'options.id',
                    'options.name_ar',
                    'options.name_en',
                    'g.name_ar as group_name_ar',
                    'g.price_role as group_role',
                ]) as $option
        ) {
            $index[(int) $option->id] = [
                $name($option),
                (string) ($option->group_name_ar ?: __('خارج الجروبات النشطة')),
                (string) ($option->group_role ?? ''),
            ];
        }

        return $index;
    }

    /**
     * What each child already carries, kept split the way the table stores it.
     *
     * `category_child_option.category_id` is 0 for «under every root» and a root
     * id for «under that root alone», so a child's real set is the union of the
     * two — and it differs by root. The screen switches roots without reloading,
     * so both halves ship and the union is taken client-side. Sending the union
     * pre-computed would repeat the 4,619 shared rows once per root the child
     * sits under.
     *
     * @param  \Illuminate\Support\Collection<int,\App\Models\Category>  $roots
     * @return array{shared:array<int,array<int,int>>,scoped:array<int,array<int,array<int,int>>>}
     */
    private function childOptionMap($roots): array
    {
        $childIds = $roots->flatMap(fn ($root) => collect($root->children)->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($childIds->isEmpty()) {
            return ['shared' => [], 'scoped' => []];
        }

        $shared = [];
        $scoped = [];

        foreach (
            CategoryChildOption::query()
                ->whereIn('child_id', $childIds)
                ->orderBy('reorder')
                ->orderBy('id')
                ->get(['child_id', 'category_id', 'option_id']) as $row
        ) {
            $childId = (int) $row->child_id;
            $rootId = (int) $row->category_id;
            $optionId = (int) $row->option_id;

            if ($rootId === CategoryChildOption::ALL_ROOTS) {
                $shared[$childId][] = $optionId;

                continue;
            }

            $scoped[$rootId][$childId][] = $optionId;
        }

        return ['shared' => $shared, 'scoped' => $scoped];
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