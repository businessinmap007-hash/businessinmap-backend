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
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CategoryController extends Controller
{
    private const PER_PAGE_ALLOWED = [10, 20, 50, 100];

    private function normalizePerPage($perPage): int
    {
        $perPage = (int) $perPage;

        return in_array($perPage, self::PER_PAGE_ALLOWED, true) ? $perPage : 50;
    }

    private function redirectToIndex(?int $rootId = null): RedirectResponse
    {
        $rootId = (int) ($rootId ?? 0);

        return $rootId > 0
            ? redirect()->route('admin.categories.index', ['root_id' => $rootId])
            : redirect()->route('admin.categories.index');
    }

    private function storeUploadedImage(Request $request, ?string $oldPath = null): ?string
    {
        if (! $request->hasFile('image')) {
            return $oldPath;
        }

        $file = $request->file('image');

        if (! $file || ! $file->isValid()) {
            return $oldPath;
        }

        $name = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $relativePath = 'files/uploads/' . $name;

        $destination = public_path('files/uploads');

        if (! is_dir($destination)) {
            @mkdir($destination, 0775, true);
        }

        $file->move($destination, $name);

        return $relativePath;
    }

    private function deleteImageIfExists(?string $path): void
    {
        if (! $path) {
            return;
        }

        $publicFullPath = public_path($path);
        if (is_file($publicFullPath)) {
            @unlink($publicFullPath);
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function normalizeSlug(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $value = preg_replace('/\s+/u', '-', $value);
        $value = preg_replace('/[^A-Za-z0-9\-_]/u', '', $value);
        $value = strtolower((string) $value);

        return $value !== '' ? $value : null;
    }

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
        $rootId  = (int) $request->get('root_id', 0);
        $q       = trim((string) $request->get('q', ''));
        $active  = $request->get('active');
        $perPage = $this->normalizePerPage($request->get('per_page', 50));

        $sort = (string) $request->get('sort', 'reorder');
        $dir  = strtolower((string) $request->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $allowedRootSorts = ['reorder', 'name_ar', 'name_en', 'id'];
        $allowedChildSorts = ['reorder', 'name_ar', 'name_en', 'id'];

        $roots = Category::query()
            ->where('parent_id', 0)
            ->orderByRaw('COALESCE(reorder, 999999) ASC')
            ->orderBy('id', 'asc')
            ->get([
                'id',
                'parent_id',
                'name_ar',
                'name_en',
                'image',
                'per_month',
                'per_year',
                'slug',
                'is_active',
                'reorder',
            ]);

        $root = null;

        if ($rootId > 0) {
            $root = Category::query()
                ->where('parent_id', 0)
                ->find($rootId);
        }

        $children = collect();

        if ($rootId > 0 && $root) {
            if (! in_array($sort, $allowedChildSorts, true)) {
                $sort = 'reorder';
            }

            $children = CategoryChild::query()
                ->withCount([
                    'options',
                    'activePlatformServices',
                ])
                ->whereHas('parents', function ($query) use ($rootId) {
                    $query->where('categories.id', $rootId);
                })
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
        } else {
            if (! in_array($sort, $allowedRootSorts, true)) {
                $sort = 'reorder';
            }
        }

        $platformServices = PlatformService::query()
            ->select(['id', 'key', 'name_ar', 'name_en'])
            ->where('is_active', 1)
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get();

        $activeOptions  = ['' => 'الكل', '1' => 'نشط', '0' => 'غير نشط'];
        $perPageOptions = self::PER_PAGE_ALLOWED;

        return view('admin-v2.categories.index', compact(
            'roots',
            'rootId',
            'root',
            'children',
            'q',
            'active',
            'activeOptions',
            'perPage',
            'perPageOptions',
            'sort',
            'dir',
            'platformServices'
        ));
    }

    public function create(): View
    {
        return view('admin-v2.categories.create', [
            'category' => new Category([
                'parent_id' => 0,
                'is_active' => 1,
                'reorder' => 0,
            ]),
            'rootId' => 0,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
            'slug' => ['nullable', 'string', 'max:191'],
            'per_month' => ['nullable', 'numeric', 'min:0'],
            'per_year' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'reorder' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        $data['parent_id'] = 0;
        $data['slug'] = $this->normalizeSlug($data['slug'] ?? null);
        $data['is_active'] = (int) ($data['is_active'] ?? 0);
        $data['reorder'] = (int) ($data['reorder'] ?? 0);
        $data['per_month'] = $data['per_month'] ?? null;
        $data['per_year'] = $data['per_year'] ?? null;
        $data['image'] = $this->storeUploadedImage($request);

        Category::create($data);

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'تم إضافة القسم الرئيسي بنجاح.');
    }

    public function edit(Category $category): View
    {
        abort_if((int) $category->parent_id !== 0, 404);

        $category->load([
            'children:id,name_ar,name_en,reorder',
        ]);

        return view('admin-v2.categories.edit', [
            'category' => $category,
            'rootId' => $category->id,
            'selectedServiceIds' => [],
            'serviceConfigs' => collect(),
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        abort_if((int) $category->parent_id !== 0, 404);

        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
            'slug' => ['nullable', 'string', 'max:191'],
            'per_month' => ['nullable', 'numeric', 'min:0'],
            'per_year' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'reorder' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        $oldImage = $category->image;

        $data['slug'] = $this->normalizeSlug($data['slug'] ?? null);
        $data['is_active'] = (int) ($data['is_active'] ?? 0);
        $data['reorder'] = (int) ($data['reorder'] ?? 0);
        $data['per_month'] = $data['per_month'] ?? null;
        $data['per_year'] = $data['per_year'] ?? null;
        $data['image'] = $this->storeUploadedImage($request, $oldImage);

        $category->update($data);

        if ($oldImage && $oldImage !== $category->image) {
            $this->deleteImageIfExists($oldImage);
        }

        return $this->redirectToIndex($category->id)
            ->with('success', 'تم تحديث القسم الرئيسي بنجاح.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        abort_if((int) $category->parent_id !== 0, 404);

        $rootId = $category->id;
        $oldImage = $category->image;

        DB::transaction(function () use ($category) {
            $childIds = $category->children()->pluck('category_children_master.id')->all();

            if (! empty($childIds)) {
                CategoryPlatformService::query()
                    ->whereIn('child_id', $childIds)
                    ->delete();

                DB::table('category_parent_child')
                    ->where('parent_id', $category->id)
                    ->delete();
            }

            CategoryPlatformService::query()
                ->where('category_id', $category->id)
                ->whereNull('child_id')
                ->delete();

            $category->delete();
        });

        $this->deleteImageIfExists($oldImage);

        return $this->redirectToIndex($rootId)
            ->with('success', 'تم حذف القسم الرئيسي بنجاح.');
    }

    public function toggleActive(Category $category): RedirectResponse
    {
        abort_if((int) $category->parent_id !== 0, 404);

        $category->update([
            'is_active' => ! (bool) $category->is_active,
        ]);

        return $this->redirectToIndex($category->id)
            ->with('success', 'تم تحديث حالة التفعيل بنجاح.');
    }

    public function updateReorder(Request $request, Category $category): RedirectResponse
    {
        abort_if((int) $category->parent_id !== 0, 404);

        $data = $request->validate([
            'reorder' => ['required', 'integer', 'min:0'],
        ]);

        $category->update([
            'reorder' => (int) $data['reorder'],
        ]);

        return $this->redirectToIndex($category->id)
            ->with('success', 'تم تحديث الترتيب بنجاح.');
    }

    public function categoryChildrenIndex(Request $request): View
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

    public function categoryChildrenCreate(Request $request): View
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

    public function categoryChildrenStore(Request $request): RedirectResponse
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
        $returnTo = (string) ($data['return_to'] ?? '');

        if ($returnTo === 'category-children-index') {
            return redirect()
                ->route('admin.category-children.index', $redirectParentId > 0 ? ['parent_id' => $redirectParentId] : [])
                ->with('success', 'تم إضافة القسم الفرعي العام وربطه بالقسم الرئيسي بنجاح.');
        }

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

        return $this->redirectToIndex($parent->id)
            ->with('success', 'تم تحديث ربط الأقسام الفرعية بنجاح.');
    }

    public function categoryChildrenEdit(Request $request, CategoryChild $categoryChild): View
    {
        $parentId = (int) $request->get('parent_id', 0);

        $categoryChild->load([
            'parents:id,name_ar,name_en,parent_id',
            'options' => function ($q) {
                $q->select('options.id', 'options.name_ar', 'options.name_en', 'options.group_id')
                    ->orderBy('options.id');
            },
            'platformServices:id,key,name_ar,name_en',
        ])->loadCount('options');

        $selectedParentIds = $categoryChild->parents
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $selectedServiceIds = $categoryChild->platformServices
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

    public function categoryChildrenUpdate(Request $request, CategoryChild $categoryChild): RedirectResponse
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

        return $this->redirectToIndex($firstParentId)
            ->with('success', 'تم تحديث القسم الفرعي بنجاح.');
    }

    public function categoryChildrenDestroy(Request $request, CategoryChild $categoryChild): RedirectResponse
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

        return $this->redirectToIndex($parentId)
            ->with('success', 'تم حذف القسم الفرعي بنجاح.');
    }

    public function detachChildParent(Request $request, CategoryChild $categoryChild, Category $parent): RedirectResponse
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

            return $this->redirectToIndex($rootId)
                ->with('success', 'تم فصل القسم الفرعي، ولأنه لم يعد مرتبطًا بأي قسم رئيسي تم حذفه نهائيًا.');
        }

        return $this->redirectToIndex($rootId > 0 ? $rootId : $parent->id)
            ->with('success', 'تم فصل القسم الفرعي عن القسم الرئيسي بنجاح.');
    }
}