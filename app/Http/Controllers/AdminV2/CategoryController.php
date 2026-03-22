<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryChild;
use App\Models\CategoryPlatformService;
use App\Models\CategoryServiceConfig;
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

    private function normalizeSlug(
        ?string $slug,
        ?string $nameEn = null,
        ?string $nameAr = null,
        ?int $ignoreId = null
    ): string {
        $base = trim((string) ($slug ?: $nameEn ?: $nameAr ?: ''));

        $normalized = str($base)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/u', '-')
            ->trim('-')
            ->value();

        if ($normalized === '') {
            $normalized = 'category-' . time();
        }

        $original = $normalized;
        $counter = 1;

        while (
            Category::query()
                ->where('slug', $normalized)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $normalized = $original . '-' . $counter;
            $counter++;
        }

        return $normalized;
    }

    private function normalizeArray($value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function toBool($value, $default = false): bool
    {
        if ($value === null) {
            return $default;
        }

        return in_array($value, [1, '1', true, 'true', 'on', 'yes'], true);
    }

    private function bookingConfigPayload(Request $request): array
    {
        return [
            'booking_modes' => $this->normalizeArray($request->input('booking_modes')),
            'item_family' => trim((string) $request->input('item_family', '')) ?: null,

            'requires_bookable_item' => $this->toBool($request->input('requires_bookable_item'), true),
            'requires_start_end' => $this->toBool($request->input('requires_start_end'), true),

            'supports_quantity' => $this->toBool($request->input('supports_quantity')),
            'supports_guest_count' => $this->toBool($request->input('supports_guest_count')),
            'supports_extras' => $this->toBool($request->input('supports_extras')),

            'allowed_item_types' => $this->normalizeArray($request->input('allowed_item_types')),
            'required_fields' => $this->normalizeArray($request->input('required_fields')),
        ];
    }

    private function menuConfigPayload(Request $request): array
    {
        return [
            'has_variants' => $this->toBool($request->input('menu_has_variants')),
            'has_addons' => $this->toBool($request->input('menu_has_addons')),
            'supports_notes' => $this->toBool($request->input('menu_supports_notes')),
            'supports_stock' => $this->toBool($request->input('menu_supports_stock')),
        ];
    }

    private function deliveryConfigPayload(Request $request): array
    {
        return [
            'has_delivery' => $this->toBool($request->input('delivery_has_delivery'), true),
            'delivery_type' => trim((string) $request->input('delivery_type', 'distance')) ?: 'distance',
            'max_radius_km' => (int) $request->input('delivery_max_radius_km', 0),
            'supports_scheduled_delivery' => $this->toBool($request->input('delivery_supports_scheduled')),
        ];
    }

    private function serviceConfigPayload(Request $request, PlatformService $service): array
    {
        return match ((string) $service->key) {
            'booking' => $this->bookingConfigPayload($request),
            'menu' => $this->menuConfigPayload($request),
            'delivery' => $this->deliveryConfigPayload($request),
            default => [],
        };
    }

    private function syncCategoryServicesAndConfigs(Request $request, Category $category): void
    {
        $serviceIds = collect($request->input('platform_service_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        CategoryPlatformService::query()
            ->where('category_id', $category->id)
            ->delete();

        CategoryServiceConfig::query()
            ->where('category_id', $category->id)
            ->delete();

        if ($serviceIds->isEmpty()) {
            return;
        }

        $services = PlatformService::query()
            ->whereIn('id', $serviceIds->all())
            ->get()
            ->keyBy('id');

        $order = 1;

        foreach ($serviceIds as $serviceId) {
            CategoryPlatformService::create([
                'category_id' => $category->id,
                'platform_service_id' => $serviceId,
                'is_active' => true,
                'sort_order' => $order,
                'meta' => null,
            ]);

            $service = $services->get($serviceId);

            if ($service) {
                CategoryServiceConfig::create([
                    'category_id' => $category->id,
                    'platform_service_id' => $serviceId,
                    'config' => $this->serviceConfigPayload($request, $service),
                    'is_active' => true,
                    'sort_order' => $order,
                ]);
            }

            $order++;
        }
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
                ->with('options:id')
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
        $row = new Category([
            'parent_id' => 0,
            'is_active' => 1,
            'reorder' => 0,
            'per_month' => 0,
            'per_year' => 0,
        ]);

        $parents = Category::query()
            ->select(['id', 'name_ar', 'name_en'])
            ->where('parent_id', 0)
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get();

        $platformServices = PlatformService::query()
            ->where('is_active', 1)
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get(['id', 'key', 'name_ar', 'name_en']);

        return view('admin-v2.categories.create', [
            'row' => $row,
            'parents' => $parents,
            'platformServices' => $platformServices,
            'selectedPlatformServices' => [],
            'bookingConfig' => [],
            'menuConfig' => [],
            'deliveryConfig' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name_ar' => 'required|string|max:191',
            'name_en' => 'nullable|string|max:191',
            'slug' => 'nullable|string|max:191|unique:categories,slug',
            'parent_id' => 'required|integer|min:0',

            'is_active' => 'nullable|in:0,1',
            'reorder' => 'nullable|integer|min:0|max:1000000',

            'per_month' => 'nullable|numeric|min:0',
            'per_year' => 'nullable|numeric|min:0',

            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',

            'platform_service_ids' => 'nullable|array',
            'platform_service_ids.*' => 'integer|exists:platform_services,id',

            'booking_modes' => 'nullable|array',
            'booking_modes.*' => 'string|max:50',
            'item_family' => 'nullable|string|max:100',
            'requires_bookable_item' => 'nullable|in:0,1',
            'requires_start_end' => 'nullable|in:0,1',
            'supports_quantity' => 'nullable|in:0,1',
            'supports_guest_count' => 'nullable|in:0,1',
            'supports_extras' => 'nullable|in:0,1',
            'allowed_item_types' => 'nullable|array',
            'allowed_item_types.*' => 'string|max:100',
            'required_fields' => 'nullable|array',
            'required_fields.*' => 'string|max:100',

            'menu_has_variants' => 'nullable|in:0,1',
            'menu_has_addons' => 'nullable|in:0,1',
            'menu_supports_notes' => 'nullable|in:0,1',
            'menu_supports_stock' => 'nullable|in:0,1',

            'delivery_has_delivery' => 'nullable|in:0,1',
            'delivery_type' => 'nullable|string|max:50',
            'delivery_max_radius_km' => 'nullable|integer|min:0|max:1000',
            'delivery_supports_scheduled' => 'nullable|in:0,1',
        ]);

        $pid = (int) ($data['parent_id'] ?? 0);

        if ($pid > 0) {
            return back()
                ->withErrors([
                    'parent_id' => 'إضافة/تعديل الأقسام الفرعية أصبحت من جدول category_children_master وربط category_parent_child، وليس من CategoryController.',
                ])
                ->withInput();
        }

        $data['is_active'] = (int) ($data['is_active'] ?? 1);
        $data['parent_id'] = 0;
        $data['slug'] = $this->normalizeSlug(
            $data['slug'] ?? null,
            $data['name_en'] ?? null,
            $data['name_ar'] ?? null
        );
        $data['image'] = $this->storeUploadedImage($request, null);

        unset(
            $data['platform_service_ids'],
            $data['booking_modes'],
            $data['item_family'],
            $data['requires_bookable_item'],
            $data['requires_start_end'],
            $data['supports_quantity'],
            $data['supports_guest_count'],
            $data['supports_extras'],
            $data['allowed_item_types'],
            $data['required_fields'],
            $data['menu_has_variants'],
            $data['menu_has_addons'],
            $data['menu_supports_notes'],
            $data['menu_supports_stock'],
            $data['delivery_has_delivery'],
            $data['delivery_type'],
            $data['delivery_max_radius_km'],
            $data['delivery_supports_scheduled']
        );

        DB::transaction(function () use ($request, $data) {
            $category = Category::create($data);
            $this->syncCategoryServicesAndConfigs($request, $category);
        });

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'تم إضافة القسم الرئيسي بنجاح');
    }

    public function edit(Category $category): View
    {
        if ((int) $category->parent_id > 0) {
            abort(404);
        }

        $row = $category->load([
            'categoryPlatformServices:id,category_id,platform_service_id,is_active,sort_order,meta',
            'serviceConfigs:id,category_id,platform_service_id,config,is_active,sort_order',
            'children:id,name_ar,name_en,reorder',
        ]);

        $parents = Category::query()
            ->select(['id', 'name_ar', 'name_en'])
            ->where('id', '!=', $row->id)
            ->where('parent_id', 0)
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get();

        $platformServices = PlatformService::query()
            ->where('is_active', 1)
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get(['id', 'key', 'name_ar', 'name_en']);

        $selectedPlatformServices = $row->categoryPlatformServices
            ->pluck('platform_service_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $serviceConfigs = $row->serviceConfigs->keyBy('platform_service_id');

        $bookingService = $platformServices->firstWhere('key', 'booking');
        $menuService = $platformServices->firstWhere('key', 'menu');
        $deliveryService = $platformServices->firstWhere('key', 'delivery');

        $bookingConfig = $bookingService ? (($serviceConfigs[$bookingService->id]->config ?? []) ?: []) : [];
        $menuConfig = $menuService ? (($serviceConfigs[$menuService->id]->config ?? []) ?: []) : [];
        $deliveryConfig = $deliveryService ? (($serviceConfigs[$deliveryService->id]->config ?? []) ?: []) : [];

        return view('admin-v2.categories.edit', [
            'row' => $row,
            'parents' => $parents,
            'platformServices' => $platformServices,
            'selectedPlatformServices' => $selectedPlatformServices,
            'bookingConfig' => is_array($bookingConfig) ? $bookingConfig : [],
            'menuConfig' => is_array($menuConfig) ? $menuConfig : [],
            'deliveryConfig' => is_array($deliveryConfig) ? $deliveryConfig : [],
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        if ((int) $category->parent_id > 0) {
            abort(404);
        }

        $data = $request->validate([
            'name_ar' => 'required|string|max:191',
            'name_en' => 'nullable|string|max:191',
            'slug' => 'nullable|string|max:191|unique:categories,slug,' . $category->id,
            'parent_id' => 'required|integer|min:0',
            'is_active' => 'nullable|in:0,1',
            'reorder' => 'nullable|integer|min:0|max:1000000',
            'per_month' => 'nullable|numeric|min:0',
            'per_year' => 'nullable|numeric|min:0',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',

            'platform_service_ids' => 'nullable|array',
            'platform_service_ids.*' => 'integer|exists:platform_services,id',

            'booking_modes' => 'nullable|array',
            'booking_modes.*' => 'string|max:50',
            'item_family' => 'nullable|string|max:100',
            'requires_bookable_item' => 'nullable|in:0,1',
            'requires_start_end' => 'nullable|in:0,1',
            'supports_quantity' => 'nullable|in:0,1',
            'supports_guest_count' => 'nullable|in:0,1',
            'supports_extras' => 'nullable|in:0,1',
            'allowed_item_types' => 'nullable|array',
            'allowed_item_types.*' => 'string|max:100',
            'required_fields' => 'nullable|array',
            'required_fields.*' => 'string|max:100',

            'menu_has_variants' => 'nullable|in:0,1',
            'menu_has_addons' => 'nullable|in:0,1',
            'menu_supports_notes' => 'nullable|in:0,1',
            'menu_supports_stock' => 'nullable|in:0,1',

            'delivery_has_delivery' => 'nullable|in:0,1',
            'delivery_type' => 'nullable|string|max:50',
            'delivery_max_radius_km' => 'nullable|integer|min:0|max:1000',
            'delivery_supports_scheduled' => 'nullable|in:0,1',
        ]);

        $pid = (int) ($data['parent_id'] ?? 0);

        if ($pid > 0) {
            return back()
                ->withErrors([
                    'parent_id' => 'تحويل القسم الرئيسي إلى فرعي لم يعد مدعومًا داخل CategoryController بعد فصل children في جداول مستقلة.',
                ])
                ->withInput();
        }

        $data['is_active'] = (int) ($data['is_active'] ?? $category->is_active);
        $data['parent_id'] = 0;
        $data['slug'] = $this->normalizeSlug(
            $data['slug'] ?? $category->slug,
            $data['name_en'] ?? null,
            $data['name_ar'] ?? null,
            $category->id
        );

        $oldImage = $category->image;
        $newImage = $this->storeUploadedImage($request, $oldImage);
        $data['image'] = $newImage;

        unset(
            $data['platform_service_ids'],
            $data['booking_modes'],
            $data['item_family'],
            $data['requires_bookable_item'],
            $data['requires_start_end'],
            $data['supports_quantity'],
            $data['supports_guest_count'],
            $data['supports_extras'],
            $data['allowed_item_types'],
            $data['required_fields'],
            $data['menu_has_variants'],
            $data['menu_has_addons'],
            $data['menu_supports_notes'],
            $data['menu_supports_stock'],
            $data['delivery_has_delivery'],
            $data['delivery_type'],
            $data['delivery_max_radius_km'],
            $data['delivery_supports_scheduled']
        );

        DB::transaction(function () use ($request, $category, $data) {
            $category->update($data);
            $this->syncCategoryServicesAndConfigs($request, $category);
        });

        if ($request->hasFile('image') && $oldImage && $oldImage !== $newImage) {
            $this->deleteImageIfExists($oldImage);
        }

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'تم تحديث القسم الرئيسي بنجاح');
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        if ((int) $category->parent_id > 0) {
            return back()->withErrors([
                'error' => 'حذف الأقسام الفرعية القديمة لم يعد يتم من هذا الكنترولر.',
            ]);
        }

        if ($category->children()->exists()) {
            return back()->withErrors([
                'error' => 'لا يمكن حذف قسم رئيسي لديه أقسام فرعية مرتبطة. قم بفصل الأقسام الفرعية أولاً.',
            ]);
        }

        $this->deleteImageIfExists($category->image);

        $category->delete();

        return $this->redirectToIndex()
            ->with('success', 'تم حذف القسم الرئيسي بنجاح');
    }

    public function toggleActive(Category $category)
    {
        if ((int) $category->parent_id > 0) {
            return response()->json([
                'ok' => false,
                'message' => 'Toggle active للأقسام الفرعية القديمة غير مدعوم من هذا الكنترولر.',
            ], 422);
        }

        $category->is_active = ! $category->is_active;
        $category->save();

        return response()->json([
            'ok' => true,
            'id' => $category->id,
            'is_active' => (int) $category->is_active,
        ]);
    }

    public function updateReorder(Request $request, Category $category)
    {
        if ((int) $category->parent_id > 0) {
            return response()->json([
                'ok' => false,
                'message' => 'إعادة الترتيب من هذا الكنترولر متاحة للأقسام الرئيسية فقط.',
            ], 422);
        }

        $data = $request->validate([
            'reorder' => 'required|integer|min:0|max:999999',
        ]);

        $category->reorder = (int) $data['reorder'];
        $category->save();

        return response()->json([
            'ok' => true,
            'reorder' => $category->reorder,
        ]);
    }

    public function syncChildren(Request $request, $parent)
    {
        $parent = Category::query()
            ->where('parent_id', 0)
            ->findOrFail($parent);

        $childIds = collect($request->input('child_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $parent->children()->sync($childIds);

        return redirect()
            ->route('admin.category-children.index', ['parent_id' => $parent->id])
            ->with('success', 'تم تحديث ربط الأقسام الفرعية بنجاح.');
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
        if ($parentId > 0) {
            $parent = Category::query()
                ->with('children:id,name_ar,name_en,reorder')
                ->where('parent_id', 0)
                ->find($parentId);
        }

        $rows = CategoryChild::query()
            ->with([
                'parents:id,name_ar,name_en',
                'options:id',
            ])
            ->withCount('parents')
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

        return view('admin-v2.category-children.index', compact(
            'rows',
            'parents',
            'parent',
            'parentId',
            'q',
            'perPage',
            'perPageOptions',
            'sort',
            'dir'
        ));
    }

    public function categoryChildrenCreate(Request $request): View
    {
        $parentId = (int) $request->get('parent_id', 0);

        $row = new CategoryChild([
            'name_ar' => '',
            'name_en' => '',
            'reorder' => 0,
        ]);

        $parents = Category::query()
            ->where('parent_id', 0)
            ->orderByRaw('COALESCE(reorder, 999999) ASC')
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get(['id', 'name_ar', 'name_en']);

        $selectedParentIds = $parentId > 0 ? [$parentId] : [];

        return view('admin-v2.category-children.create', compact(
            'row',
            'parents',
            'selectedParentIds',
            'parentId'
        ));
    }

    public function categoryChildrenStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name_ar' => 'required|string|max:191',
            'name_en' => 'nullable|string|max:191',
            'reorder' => 'nullable|integer|min:0|max:1000000',
            'parent_ids' => 'nullable|array',
            'parent_ids.*' => 'integer|exists:categories,id',
        ]);

        $parentIds = collect($request->input('parent_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $row = null;

        DB::transaction(function () use ($data, $parentIds, &$row) {
            $row = CategoryChild::query()->create([
                'name_ar' => trim((string) $data['name_ar']),
                'name_en' => trim((string) ($data['name_en'] ?? '')) ?: null,
                'reorder' => (int) ($data['reorder'] ?? 0),
            ]);

            if (! empty($parentIds)) {
                $row->parents()->sync($parentIds);
            }
        });

        $redirectParentId = ! empty($parentIds) ? (int) $parentIds[0] : 0;

        return redirect()
            ->route('admin.category-children.index', $redirectParentId > 0 ? ['parent_id' => $redirectParentId] : [])
            ->with('success', 'تم إضافة القسم الفرعي بنجاح.');
    }

    public function categoryChildrenEdit($categoryChild): View
    {
        $row = CategoryChild::query()
            ->with([
                'parents:id,name_ar,name_en',
                'options:id,name_ar,name_en',
            ])
            ->findOrFail($categoryChild);

        $parents = Category::query()
            ->where('parent_id', 0)
            ->orderByRaw('COALESCE(reorder, 999999) ASC')
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get(['id', 'name_ar', 'name_en']);

        $selectedParentIds = $row->parents
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return view('admin-v2.category-children.edit', compact(
            'row',
            'parents',
            'selectedParentIds'
        ));
    }

    public function categoryChildrenUpdate(Request $request, $categoryChild): RedirectResponse
    {
        $row = CategoryChild::query()->findOrFail($categoryChild);

        $data = $request->validate([
            'name_ar' => 'required|string|max:191',
            'name_en' => 'nullable|string|max:191',
            'reorder' => 'nullable|integer|min:0|max:1000000',
            'parent_ids' => 'nullable|array',
            'parent_ids.*' => 'integer|exists:categories,id',
        ]);

        $parentIds = collect($request->input('parent_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        DB::transaction(function () use ($row, $data, $parentIds) {
            $row->update([
                'name_ar' => trim((string) $data['name_ar']),
                'name_en' => trim((string) ($data['name_en'] ?? '')) ?: null,
                'reorder' => (int) ($data['reorder'] ?? 0),
            ]);

            $row->parents()->sync($parentIds);
        });

        $redirectParentId = (int) ($request->input('parent_id', 0));
        if ($redirectParentId <= 0 && ! empty($parentIds)) {
            $redirectParentId = (int) $parentIds[0];
        }

        return redirect()
            ->route('admin.category-children.index', $redirectParentId > 0 ? ['parent_id' => $redirectParentId] : [])
            ->with('success', 'تم تحديث القسم الفرعي بنجاح.');
    }

    public function categoryChildrenDestroy(Request $request, $categoryChild): RedirectResponse
    {
        $row = CategoryChild::query()
            ->withCount('options')
            ->findOrFail($categoryChild);

        if ((int) ($row->options_count ?? 0) > 0) {
            return back()->withErrors([
                'error' => 'لا يمكن حذف القسم الفرعي لأنه مرتبط بخيارات. قم بفصل الخيارات أولاً.',
            ]);
        }

        DB::transaction(function () use ($row) {
            $row->parents()->detach();
            $row->delete();
        });

        $parentId = (int) $request->input('parent_id', 0);

        return redirect()
            ->route('admin.category-children.index', $parentId > 0 ? ['parent_id' => $parentId] : [])
            ->with('success', 'تم حذف القسم الفرعي بنجاح.');
    }

    public function detachChildParent($categoryChild, $parent): RedirectResponse
    {
        $row = CategoryChild::query()->findOrFail($categoryChild);
        $parentId = (int) $parent;

        $row->parents()->detach([$parentId]);

        return redirect()
            ->route('admin.category-children.index', ['parent_id' => $parentId])
            ->with('success', 'تم فصل ربط القسم الفرعي من القسم الرئيسي بنجاح.');
    }
    private function normalizeLegacyChildName(?string $value): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    return mb_strtolower(preg_replace('/\s+/u', ' ', $value));
}

private function findMatchingCategoryChild(?string $nameAr, ?string $nameEn): ?CategoryChild
{
    $nameArNorm = $this->normalizeLegacyChildName($nameAr);
    $nameEnNorm = $this->normalizeLegacyChildName($nameEn);

    $all = CategoryChild::query()
        ->select(['id', 'name_ar', 'name_en', 'reorder'])
        ->get();

    return $all->first(function ($child) use ($nameArNorm, $nameEnNorm) {
        $childAr = $this->normalizeLegacyChildName($child->name_ar);
        $childEn = $this->normalizeLegacyChildName($child->name_en);

        if ($nameArNorm !== '' && ($childAr === $nameArNorm || $childEn === $nameArNorm)) {
            return true;
        }

        if ($nameEnNorm !== '' && ($childAr === $nameEnNorm || $childEn === $nameEnNorm)) {
            return true;
        }

        return false;
    });
}

public function legacyChildrenReview(Request $request): View
{
    $q = trim((string) $request->get('q', ''));
    $perPage = $this->normalizePerPage($request->get('per_page', 50));
    $parentId = (int) $request->get('parent_id', 0); // 👈 جديد

    $query = Category::query()
        ->where('parent_id', '>', 0)
        ->with([
            'parent:id,name_ar,name_en',
        ]);

    // 👇 فلتر بالقسم الرئيسي
    if ($parentId > 0) {
        $query->where('parent_id', $parentId);
    }

    $rows = $query
        ->when($q !== '', function ($query) use ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('name_ar', 'like', "%{$q}%")
                  ->orWhere('name_en', 'like', "%{$q}%");
            });
        })
        ->select([
            'id',
            'parent_id',
            'name_ar',
            'name_en',
            'reorder',
            'is_active',
        ])
        ->orderBy('parent_id', 'asc')
        ->orderByRaw('COALESCE(reorder, 999999) ASC')
        ->orderBy('id', 'asc')
        ->paginate($perPage)
        ->withQueryString();

    $items = collect($rows->items())->map(function ($legacy) {
        $matched = $this->findMatchingCategoryChild($legacy->name_ar, $legacy->name_en);

        return [
            'legacy' => $legacy,
            'matched_child' => $matched,
            'is_matched' => $matched !== null,
        ];
    });

    // 👇 مهم جدًا: قائمة الأقسام الرئيسية
    $parents = Category::query()
        ->where('parent_id', 0)
        ->orderBy('name_ar')
        ->get(['id', 'name_ar', 'name_en']);

    $perPageOptions = self::PER_PAGE_ALLOWED;

    return view('admin-v2.category-children.legacy-review', [
        'rows' => $rows,
        'items' => $items,
        'q' => $q,
        'parentId' => $parentId, // 👈 جديد
        'parents' => $parents,   // 👈 جديد
        'perPage' => $perPage,
        'perPageOptions' => $perPageOptions,
    ]);
}
public function bulkUpdateChildrenReorder(Request $request): RedirectResponse
{
    $data = $request->validate([
        'parent_id' => 'nullable|integer|min:0',
        'child_reorders' => 'required|array',
        'child_reorders.*' => 'nullable|integer|min:0|max:999999',
        'save_one_id' => 'nullable|integer|exists:category_children_master,id',
    ]);

    $parentId = (int) ($data['parent_id'] ?? 0);
    $saveOneId = (int) ($data['save_one_id'] ?? 0);

    $childReorders = collect($data['child_reorders'] ?? [])
        ->mapWithKeys(function ($value, $key) {
            return [(int) $key => (int) $value];
        })
        ->filter(fn ($value, $key) => $key > 0);

    if ($childReorders->isEmpty()) {
        return back()->withErrors([
            'error' => 'لا توجد قيم reorder صالحة للحفظ.',
        ]);
    }

    $query = CategoryChild::query()->whereIn('id', $childReorders->keys()->all());

    if ($parentId > 0) {
        $query->whereHas('parents', function ($q) use ($parentId) {
            $q->where('categories.id', $parentId);
        });
    }

    $rows = $query->get(['id', 'reorder']);

    DB::transaction(function () use ($rows, $childReorders, $saveOneId) {
        foreach ($rows as $row) {
            if ($saveOneId > 0 && (int) $row->id !== $saveOneId) {
                continue;
            }

            $newReorder = (int) ($childReorders[$row->id] ?? $row->reorder ?? 0);

            if ((int) $row->reorder !== $newReorder) {
                $row->update([
                    'reorder' => $newReorder,
                ]);
            }
        }
    });

    return redirect()
        ->route('admin.categories.index', $parentId > 0 ? ['root_id' => $parentId] : [])
        ->with('success', $saveOneId > 0
            ? 'تم تحديث ترتيب القسم الفرعي بنجاح.'
            : 'تم تحديث ترتيب الأقسام الفرعية بنجاح.');
}

public function importLegacyChildren(Request $request): RedirectResponse
{
    $data = $request->validate([
        'legacy_ids' => 'required|array|min:1',
        'legacy_ids.*' => 'integer|exists:categories,id',
    ]);

    $legacyIds = collect($data['legacy_ids'])
        ->map(fn ($id) => (int) $id)
        ->filter(fn ($id) => $id > 0)
        ->unique()
        ->values()
        ->all();

    $legacyRows = Category::query()
        ->whereIn('id', $legacyIds)
        ->where('parent_id', '>', 0)
        ->get([
            'id',
            'parent_id',
            'name_ar',
            'name_en',
            'reorder',
            'is_active',
        ]);

    $importedCount = 0;
    $attachedCount = 0;

    DB::transaction(function () use ($legacyRows, &$importedCount, &$attachedCount) {
        foreach ($legacyRows as $legacy) {
            $child = $this->findMatchingCategoryChild($legacy->name_ar, $legacy->name_en);

            if (! $child) {
                $child = CategoryChild::query()->create([
                    'name_ar' => trim((string) $legacy->name_ar),
                    'name_en' => trim((string) ($legacy->name_en ?? '')) ?: null,
                    'reorder' => (int) ($legacy->reorder ?? 0),
                ]);

                $importedCount++;
            } else {
                if (
                    (int) ($child->reorder ?? 0) === 0 &&
                    (int) ($legacy->reorder ?? 0) > 0
                ) {
                    $child->update([
                        'reorder' => (int) $legacy->reorder,
                    ]);
                }
            }

            $before = $child->parents()->where('categories.id', (int) $legacy->parent_id)->exists();

            $child->parents()->syncWithoutDetaching([
                (int) $legacy->parent_id,
            ]);

            if (! $before) {
                $attachedCount++;
            }
        }
    });

    return redirect()
        ->route('admin.category-children.legacy-review')
        ->with('success', "تمت مراجعة الاستيراد بنجاح. تم إنشاء {$importedCount} قسم فرعي جديد، وتم إنشاء {$attachedCount} ربط جديد.");
}
}