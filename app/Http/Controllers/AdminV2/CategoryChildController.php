<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryChild;
use App\Models\CategoryPlatformService;
use App\Models\PlatformService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Sub-category (category child) management.
 *
 * A "category child" is a normalized sub-category stored in the
 * `category_children_master` table. Unlike the legacy model, a child is NOT a
 * row in `categories` with parent_id > 0; instead it can belong to several
 * root categories at once through the `category_parent_child` pivot.
 *
 * This controller owns the child CRUD only. Root categories live in
 * {@see CategoryController}. See docs/categories.md for the full data model.
 */
class CategoryChildController extends Controller
{
    private const PER_PAGE_ALLOWED = [10, 20, 50, 100];

    private function normalizePerPage($perPage): int
    {
        $perPage = (int) $perPage;

        return in_array($perPage, self::PER_PAGE_ALLOWED, true) ? $perPage : 50;
    }

    /**
     * Children are always managed in the context of their root category, so
     * after a write we land back on the categories screen (scoped to the root
     * when we know which one), not on a standalone children screen.
     */
    private function redirectToCategories(?int $rootId = null): RedirectResponse
    {
        $rootId = (int) ($rootId ?? 0);

        return $rootId > 0
            ? redirect()->route('admin.categories.index', ['root_id' => $rootId])
            : redirect()->route('admin.categories.index');
    }

    /**
     * Reconcile the platform-services a child offers, storing the first parent
     * as the fallback category_id. Passing an empty $serviceIds list soft-
     * disables every existing link instead of deleting the rows.
     */
    private function syncChildServices(CategoryChild $categoryChild, array $parentIds, array $serviceIds): void
    {
        $parentIds = collect($parentIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $serviceIds = collect($serviceIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $fallbackCategoryId = (int) ($parentIds[0] ?? 0);

        if (empty($serviceIds)) {
            CategoryPlatformService::query()
                ->where('child_id', $categoryChild->id)
                ->update([
                    'is_active' => 0,
                    'updated_at' => now(),
                ]);

            return;
        }

        $order = 1;

        foreach ($serviceIds as $serviceId) {
            CategoryPlatformService::query()->updateOrCreate(
                [
                    'child_id' => $categoryChild->id,
                    'platform_service_id' => $serviceId,
                ],
                [
                    'category_id' => $fallbackCategoryId > 0 ? $fallbackCategoryId : null,
                    'is_active' => true,
                    'sort_order' => $order,
                    'meta' => null,
                ]
            );

            $order++;
        }

        CategoryPlatformService::query()
            ->where('child_id', $categoryChild->id)
            ->whereNotIn('platform_service_id', $serviceIds)
            ->update([
                'is_active' => 0,
                'updated_at' => now(),
            ]);
    }

    public function index(Request $request): View
    {
        $parentId = (int) $request->get('parent_id', 0);
        $q = trim((string) $request->get('q', ''));
        $perPage = $this->normalizePerPage($request->get('per_page', 50));

        $sort = (string) $request->get('sort', 'reorder');
        $dir = strtolower((string) $request->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $allowedSorts = ['id', 'reorder', 'name_ar', 'name_en'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'reorder';
        }

        $parents = Category::query()
            ->where('parent_id', 0)
            ->orderByRaw('COALESCE(reorder, 999999) ASC')
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get(['id', 'name_ar', 'name_en', 'reorder']);

        $parent = null;
        $selectedChildIds = collect();

        if ($parentId > 0) {
            $parent = Category::query()
                ->with([
                    'children:id,name_ar,name_en,reorder',
                ])
                ->where('parent_id', 0)
                ->find($parentId);

            if ($parent) {
                $selectedChildIds = $parent->children
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values();
            }
        }

        $rows = CategoryChild::query()
            ->withCount([
                'parents',
                'options',
                'activePlatformServices',
            ])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('name_ar', 'like', "%{$q}%")
                        ->orWhere('name_en', 'like', "%{$q}%");
                });
            })
            ->select(['id', 'name_ar', 'name_en', 'reorder', 'created_at', 'updated_at'])
            ->orderByRaw('COALESCE(reorder, 999999) ASC')
            ->orderBy('id', 'asc')
            ->when($sort !== 'reorder', function ($query) use ($sort, $dir) {
                $query->reorder()->orderBy($sort, $dir)->orderBy('id', 'asc');
            })
            ->paginate($perPage)
            ->withQueryString();

        $perPageOptions = self::PER_PAGE_ALLOWED;

        return view('admin-v2.category-children.index', [
            'rows' => $rows,
            'parents' => $parents,
            'parent' => $parent,
            'parentId' => $parentId,
            'selectedChildIds' => $selectedChildIds,
            'q' => $q,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function create(Request $request): View
    {
        $parentId = (int) $request->get('parent_id', 0);

        $root = null;
        if ($parentId > 0) {
            $root = Category::query()
                ->where('parent_id', 0)
                ->find($parentId);
        }

        $parents = Category::query()
            ->where('parent_id', 0)
            ->orderByRaw('COALESCE(reorder, 999999) ASC')
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get(['id', 'name_ar', 'name_en']);

        $services = PlatformService::query()
            ->where('is_active', 1)
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get(['id', 'key', 'name_ar', 'name_en']);

        return view('admin-v2.category-children.create', [
            'categoryChild' => new CategoryChild([
                'reorder' => 0,
            ]),
            'parents' => $parents,
            'services' => $services,
            'selectedServiceIds' => [],
            'serviceConfigs' => collect(),
            'parentId' => $parentId,
            'root' => $root,
            'selectedParentIds' => $parentId > 0 ? [$parentId] : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name_ar' => 'required|string|max:191',
            'name_en' => 'nullable|string|max:191',
            'reorder' => 'nullable|integer|min:0|max:1000000',
            'parent_ids' => 'nullable|array',
            'parent_ids.*' => 'integer|exists:categories,id',
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'integer|exists:platform_services,id',
            'return_to' => 'nullable|string|max:50',
        ]);

        $parentIds = collect($request->input('parent_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $serviceIds = collect($request->input('service_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $row = null;

        DB::transaction(function () use ($data, $parentIds, $serviceIds, &$row) {
            $row = CategoryChild::query()->create([
                'name_ar' => trim((string) $data['name_ar']),
                'name_en' => trim((string) ($data['name_en'] ?? '')) ?: null,
                'reorder' => (int) ($data['reorder'] ?? 0),
            ]);

            if (! empty($parentIds)) {
                $row->parents()->sync($parentIds);
            }

            $this->syncChildServices($row, $parentIds, $serviceIds);
        });

        $redirectParentId = ! empty($parentIds) ? (int) $parentIds[0] : 0;

        return redirect()
            ->route('admin.category-children.index', $redirectParentId > 0 ? ['parent_id' => $redirectParentId] : [])
            ->with('success', 'تم إضافة القسم الفرعي بنجاح.');
    }

    public function syncChildren(Request $request, Category $parent): RedirectResponse
    {
        abort_if((int) $parent->parent_id !== 0, 404);

        $data = $request->validate([
            'child_ids' => ['nullable', 'array'],
            'child_ids.*' => ['integer', 'exists:category_children_master,id'],
        ]);

        $childIds = collect($data['child_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $parent->children()->sync($childIds);

        return $this->redirectToCategories($parent->id)
            ->with('success', 'تم تحديث ربط الأقسام الفرعية بنجاح.');
    }

    public function edit(Request $request, CategoryChild $categoryChild): View
    {
        $parentId = (int) $request->get('parent_id', 0);

        $categoryChild->load([
            'parents:id,name_ar,name_en,parent_id',
            'options' => function ($q) {
                $q->select('options.id', 'options.name_ar', 'options.name_en', 'options.group_id')
                    ->orderBy('options.id');
            },
            'activePlatformServices:id,key,name_ar,name_en',
        ])->loadCount('options');

        $selectedParentIds = $categoryChild->parents
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $selectedServiceIds = $categoryChild->activePlatformServices
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $selectedOptionIds = $categoryChild->options
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $parents = Category::query()
            ->where('parent_id', 0)
            ->orderByRaw('COALESCE(reorder, 999999) ASC')
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get(['id', 'name_ar', 'name_en']);

        $services = PlatformService::query()
            ->where('is_active', 1)
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get(['id', 'key', 'name_ar', 'name_en']);

        $root = null;
        if ($parentId > 0) {
            $root = Category::query()
                ->where('parent_id', 0)
                ->find($parentId, ['id', 'name_ar', 'name_en']);
        }

        return view('admin-v2.category-children.edit', [
            'categoryChild' => $categoryChild,
            'parents' => $parents,
            'services' => $services,
            'selectedServiceIds' => $selectedServiceIds,
            'selectedOptionIds' => $selectedOptionIds,
            'parentId' => $parentId,
            'root' => $root,
            'selectedParentIds' => $selectedParentIds,
        ]);
    }

    public function update(Request $request, CategoryChild $categoryChild): RedirectResponse
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
            'reorder' => ['nullable', 'integer', 'min:0'],
            'parent_ids' => ['nullable', 'array'],
            'parent_ids.*' => ['integer', 'exists:categories,id'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'exists:platform_services,id'],
        ]);

        $parentIds = collect($data['parent_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $serviceIds = collect($data['service_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        DB::transaction(function () use ($categoryChild, $data, $parentIds, $serviceIds) {
            $categoryChild->update([
                'name_ar' => $data['name_ar'],
                'name_en' => $data['name_en'] ?? null,
                'reorder' => (int) ($data['reorder'] ?? 0),
            ]);

            $categoryChild->parents()->sync($parentIds);
            $this->syncChildServices($categoryChild, $parentIds, $serviceIds);
        });

        $firstParentId = (int) ($parentIds[0] ?? 0);

        return $this->redirectToCategories($firstParentId)
            ->with('success', 'تم تحديث القسم الفرعي بنجاح.');
    }

    public function destroy(Request $request, CategoryChild $categoryChild): RedirectResponse
    {
        $parentId = (int) $request->input('parent_id', $request->get('parent_id', 0));

        DB::transaction(function () use ($categoryChild) {
            DB::table('category_parent_child')
                ->where('child_id', $categoryChild->id)
                ->delete();

            DB::table('category_child_option')
                ->where('child_id', $categoryChild->id)
                ->delete();

            CategoryPlatformService::query()
                ->where('child_id', $categoryChild->id)
                ->delete();

            $categoryChild->delete();
        });

        return $this->redirectToCategories($parentId)
            ->with('success', 'تم حذف القسم الفرعي بنجاح.');
    }

    public function detachParent(Request $request, CategoryChild $categoryChild, Category $parent): RedirectResponse
    {
        $rootId = (int) $request->input('root_id', $request->get('root_id', 0));

        DB::table('category_parent_child')
            ->where('child_id', $categoryChild->id)
            ->where('parent_id', $parent->id)
            ->delete();

        $hasParents = DB::table('category_parent_child')
            ->where('child_id', $categoryChild->id)
            ->exists();

        if (! $hasParents) {
            CategoryPlatformService::query()
                ->where('child_id', $categoryChild->id)
                ->delete();

            DB::table('category_child_option')
                ->where('child_id', $categoryChild->id)
                ->delete();

            $categoryChild->delete();

            return $this->redirectToCategories($rootId)
                ->with('success', 'تم فصل القسم الفرعي، ولأنه لم يعد مرتبطًا بأي قسم رئيسي تم حذفه نهائيًا.');
        }

        return $this->redirectToCategories($rootId > 0 ? $rootId : $parent->id)
            ->with('success', 'تم فصل القسم الفرعي عن القسم الرئيسي بنجاح.');
    }
}
