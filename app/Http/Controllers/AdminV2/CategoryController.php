<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Category;
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

    $allowedSorts = ['reorder', 'name_ar', 'name_en', 'id'];
    if (! in_array($sort, $allowedSorts, true)) {
        $sort = 'reorder';
    }

    $roots = Category::query()
        ->where('parent_id', 0)
        ->orderByRaw('COALESCE(reorder, 999999) ASC')
        ->orderBy('id', 'asc')
        ->get([
            'id',
            'name_ar',
            'name_en',
            'image',
            'per_month',
            'per_year',
            'slug',
            'is_active',
        ]);

    $root = null;
    if ($rootId > 0) {
        $root = Category::query()
            ->where('parent_id', 0)
            ->find($rootId);
    }

    $children = null;

    if ($rootId > 0) {
        $children = Category::query()
            ->withCount(['categoryPlatformServices as services_count'])
            ->where('parent_id', $rootId)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('name_ar', 'like', "%{$q}%")
                        ->orWhere('name_en', 'like', "%{$q}%")
                        ->orWhere('slug', 'like', "%{$q}%");
                });
            })
            ->when($active !== null && $active !== '', function ($query) use ($active) {
                $query->where('is_active', (int) $active);
            })
            ->when(true, function ($query) use ($sort, $dir) {
                if ($sort === 'reorder') {
                    $query->orderByRaw('COALESCE(reorder, 999999) ' . $dir)
                        ->orderBy('id', 'asc');
                } else {
                    $query->orderBy($sort, $dir)
                        ->orderBy('id', 'asc');
                }
            })
            ->paginate($perPage)
            ->withQueryString();
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

        $data['is_active'] = (int) ($data['is_active'] ?? 1);
        $data['parent_id'] = (int) ($data['parent_id'] ?? 0);
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

        $rootId = (int) $request->input('root_id', 0);

        return redirect()
            ->route('admin.categories.index', $rootId > 0 ? ['root_id' => $rootId] : [])
            ->with('success', 'تم إضافة القسم بنجاح');
    }

    public function edit(Category $category): View
    {
        $row = $category->load([
            'categoryPlatformServices:id,category_id,platform_service_id,is_active,sort_order,meta',
            'serviceConfigs:id,category_id,platform_service_id,config,is_active,sort_order',
            'categoryOptions:id,name_ar,name_en',
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

        if ($pid === (int) $category->id) {
            return back()
                ->withErrors(['parent_id' => 'لا يمكن جعل القسم تابعًا لنفسه.'])
                ->withInput();
        }

        if ((int) $category->parent_id === 0 && $pid > 0) {
            $childIds = Category::query()
                ->where('parent_id', $category->id)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (in_array($pid, $childIds, true)) {
                return back()
                    ->withErrors(['parent_id' => 'لا يمكن جعل القسم الرئيسي تابعًا لأحد أقسامه الفرعية.'])
                    ->withInput();
            }
        }

        $data['is_active'] = (int) ($data['is_active'] ?? $category->is_active);
        $data['parent_id'] = $pid;
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

        $returnRootId = (int) $request->get('root_id', ($pid > 0 ? $pid : 0));

        return redirect()
            ->route('admin.categories.index', $returnRootId > 0 ? ['root_id' => $returnRootId] : [])
            ->with('success', 'تم تحديث القسم بنجاح');
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $rootId = (int) $request->get('root_id', $category->parent_id ?: 0);

        if ($category->children()->exists()) {
            return back()->withErrors([
                'error' => 'لا يمكن حذف قسم لديه أقسام فرعية. احذف الأقسام الفرعية أولاً.',
            ]);
        }

        $this->deleteImageIfExists($category->image);

        $category->delete();

        return $this->redirectToIndex($rootId)
            ->with('success', 'تم حذف القسم بنجاح');
    }

    public function toggleActive(Category $category)
    {
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
}