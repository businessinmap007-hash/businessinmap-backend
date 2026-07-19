<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryChild;
use App\Models\CategoryPlatformService;
use App\Models\CategoryServiceConfig;
use App\Models\PlatformService;
use App\Models\PlatformServiceItemType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ServiceCatalogMatrixController extends Controller
{
    public function index(Request $request): View
    {
        $rootId = (int) $request->get('root_id', 0);

        $roots = $this->roots();
        $activeRoot = $rootId > 0 ? $roots->firstWhere('id', $rootId) : $roots->first();
        $activeRootId = (int) optional($activeRoot)->id;
        $children = collect($activeRoot?->children ?? [])->values();
        $childIds = $children->pluck('id')->map(fn ($id) => (int) $id)->filter()->values()->all();

        // Every active service, each carrying its own item types, so the page
        // can show all services at once as expandable cards (multi-select).
        $services = $this->servicesWithItemTypes();
        $serviceUsageCounts = $this->serviceUsageCounts($activeRootId, $childIds);
        $childActiveServices = $this->childActiveServices($activeRootId, $childIds);

        return view('admin-v2.service-catalog-matrix.index', [
            'roots' => $roots,
            'services' => $services,
            'children' => $children,
            'childCount' => $children->count(),
            'serviceUsageCounts' => $serviceUsageCounts,
            'childActiveServices' => $childActiveServices,
            'activeRootId' => $activeRootId,
            'activeRoot' => $activeRoot,
        ]);
    }

    public function apply(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'root_id' => ['required', 'integer', 'exists:categories,id'],
            'services' => ['required', 'array', 'min:1'],
            'services.*' => ['integer', 'exists:platform_services,id'],
            'child_ids' => ['required', 'array', 'min:1'],
            'child_ids.*' => ['integer', 'exists:category_children_master,id'],
            // item_types is keyed by service id: item_types[<serviceId>][] = key
            'item_types' => ['nullable', 'array'],
            'item_types.*' => ['nullable', 'array'],
            'item_types.*.*' => ['string', 'max:100'],
            'mode' => ['required', 'in:replace,append,remove,disable_service'],
            'requires_bookable_item' => ['nullable'],
            'supports_quantity' => ['nullable'],
            'supports_guest_count' => ['nullable'],
            'supports_extras' => ['nullable'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'root_id' => __('القسم الرئيسي'),
            'services' => __('الخدمات'),
            'child_ids' => __('الأقسام الفرعية'),
            'item_types' => __('اختيارات الخدمة'),
            'mode' => __('طريقة التطبيق'),
        ]);

        $root = Category::query()->where('id', (int) $data['root_id'])->where('parent_id', 0)->firstOrFail();

        $selectedChildIds = collect($data['child_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $validChildIds = CategoryChild::query()
            ->whereIn('id', $selectedChildIds)
            ->whereHas('parents', fn ($query) => $query->where('categories.id', (int) $root->id))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($validChildIds)) {
            return back()->withErrors(['child_ids' => __('اختر قسمًا فرعيًا مرتبطًا بهذا القسم الرئيسي.')])->withInput();
        }

        $serviceIds = collect($data['services'])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $services = PlatformService::query()
            ->whereIn('id', $serviceIds)
            ->where('is_active', 1)
            ->get()
            ->keyBy('id');

        if ($services->isEmpty()) {
            return back()->withErrors(['services' => __('اختر خدمة واحدة على الأقل.')])->withInput();
        }

        $itemTypesByService = is_array($data['item_types'] ?? null) ? $data['item_types'] : [];
        $mode = (string) $data['mode'];

        DB::transaction(function () use ($services, $validChildIds, $root, $itemTypesByService, $mode, $request) {
            foreach ($services as $service) {
                $allowed = $this->itemTypesForService((int) $service->id)
                    ->pluck('key')
                    ->map(fn ($key) => (string) $key)
                    ->all();

                $selectedItemTypes = collect($itemTypesByService[(int) $service->id] ?? [])
                    ->map(fn ($key) => trim((string) $key))
                    ->filter(fn ($key) => $key !== '' && in_array($key, $allowed, true))
                    ->unique()
                    ->values()
                    ->all();

                foreach ($validChildIds as $index => $childId) {
                    $childId = (int) $childId;

                    if ($mode === 'disable_service') {
                        $this->disablePair((int) $root->id, $childId, (int) $service->id);
                        continue;
                    }

                    $this->applyServiceToChild(
                        (int) $root->id,
                        $childId,
                        $service,
                        $selectedItemTypes,
                        $index + 1,
                        $mode,
                        $request
                    );
                }
            }
        });

        return redirect()
            ->route('admin.service-catalog-matrix.index', ['root_id' => (int) $root->id])
            ->with('success', __('تم تحديث كتالوج الخدمات للأقسام الفرعية المختارة بنجاح.'));
    }

    /** Write one (root, child, service) pair: link + config, honoring the mode. */
    protected function applyServiceToChild(int $rootId, int $childId, PlatformService $service, array $selectedItemTypes, int $sortOrder, string $mode, Request $request): void
    {
        $config = CategoryServiceConfig::query()
            ->where('category_id', $rootId)
            ->where('child_id', $childId)
            ->where('platform_service_id', (int) $service->id)
            ->first();

        $current = is_array($config?->config ?? null) ? $config->config : [];
        $currentTypes = collect($current['allowed_item_types'] ?? [])
            ->map(fn ($key) => trim((string) $key))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $nextTypes = match ($mode) {
            'append' => collect($currentTypes)->merge($selectedItemTypes)->unique()->values()->all(),
            'remove' => collect($currentTypes)->reject(fn ($key) => in_array($key, $selectedItemTypes, true))->values()->all(),
            default => $selectedItemTypes,
        };

        CategoryPlatformService::query()->updateOrCreate(
            [
                'category_id' => $rootId,
                'child_id' => $childId,
                'platform_service_id' => (int) $service->id,
            ],
            [
                'is_active' => 1,
                'sort_order' => $sortOrder,
                'meta' => [
                    'source' => 'service_catalog_matrix',
                    'last_catalog_sync_at' => now()->toDateTimeString(),
                ],
                'updated_at' => now(),
            ]
        );

        CategoryServiceConfig::query()->updateOrCreate(
            [
                'category_id' => $rootId,
                'child_id' => $childId,
                'platform_service_id' => (int) $service->id,
            ],
            [
                'config' => $this->mergeServiceConfig($current, $nextTypes, $request, $service),
                'is_active' => 1,
                'sort_order' => $sortOrder,
                'updated_at' => now(),
            ]
        );
    }

    protected function roots()
    {
        return Category::query()
            ->where('parent_id', 0)
            ->with(['children' => function ($query) {
                $query->select(['category_children_master.id', 'category_children_master.name_ar', 'category_children_master.name_en', 'category_children_master.reorder'])
                    ->orderByRaw('COALESCE(category_children_master.reorder, 999999) ASC')
                    ->orderBy('category_children_master.name_ar')
                    ->orderBy('category_children_master.id');
            }])
            ->orderByRaw('COALESCE(reorder, 999999) ASC')
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get(['id', 'name_ar', 'name_en', 'reorder'])
            ->filter(fn ($root) => $root->children->isNotEmpty())
            ->values();
    }

    protected function services()
    {
        return PlatformService::query()
            ->select(['id', 'key', 'name_ar', 'name_en', 'is_active'])
            ->where('is_active', 1)
            ->orderBy('name_ar')
            ->orderBy('name_en')
            ->orderBy('id')
            ->get();
    }

    protected function itemTypesForService(int $serviceId)
    {
        if ($serviceId <= 0) {
            return collect();
        }

        return PlatformServiceItemType::query()
            ->where('platform_service_id', $serviceId)
            ->where('is_active', 1)
            ->orderByRaw('COALESCE(sort_order, 999999) ASC')
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get(['id', 'platform_service_id', 'key', 'name_ar', 'name_en', 'sort_order']);
    }

    /** Active services, each with an `item_types` collection attached. */
    protected function servicesWithItemTypes()
    {
        $services = $this->services();

        $itemTypesByService = PlatformServiceItemType::query()
            ->whereIn('platform_service_id', $services->pluck('id')->all())
            ->where('is_active', 1)
            ->orderByRaw('COALESCE(sort_order, 999999) ASC')
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get(['id', 'platform_service_id', 'key', 'name_ar', 'name_en', 'sort_order'])
            ->groupBy('platform_service_id');

        return $services->map(function ($service) use ($itemTypesByService) {
            $service->item_types = collect($itemTypesByService->get($service->id, collect()))->values();

            return $service;
        });
    }

    /** [childId => [serviceId, ...]] for the root's currently-active service links. */
    protected function childActiveServices(int $rootId, array $childIds): array
    {
        if ($rootId <= 0 || empty($childIds)) {
            return [];
        }

        return CategoryPlatformService::query()
            ->where('category_id', $rootId)
            ->whereIn('child_id', $childIds)
            ->where('is_active', 1)
            ->get(['child_id', 'platform_service_id'])
            ->groupBy('child_id')
            ->map(fn ($rows) => $rows->pluck('platform_service_id')->map(fn ($id) => (int) $id)->unique()->values()->all())
            ->all();
    }

    protected function serviceUsageCounts(int $rootId, array $childIds): array
    {
        if ($rootId <= 0 || empty($childIds)) {
            return [];
        }

        return CategoryPlatformService::query()
            ->where('category_id', $rootId)
            ->whereIn('child_id', $childIds)
            ->where('is_active', 1)
            ->select('platform_service_id', DB::raw('COUNT(DISTINCT child_id) as total'))
            ->groupBy('platform_service_id')
            ->pluck('total', 'platform_service_id')
            ->mapWithKeys(fn ($total, $serviceId) => [(int) $serviceId => (int) $total])
            ->all();
    }

    protected function mergeServiceConfig(array $current, array $itemTypes, Request $request, PlatformService $service): array
    {
        $current['allowed_item_types'] = array_values($itemTypes);
        $current['requires_bookable_item'] = $request->boolean('requires_bookable_item', (string) $service->key === PlatformService::KEY_BOOKING);
        $current['supports_quantity'] = $request->boolean('supports_quantity', true);
        $current['supports_guest_count'] = $request->boolean('supports_guest_count', false);
        $current['supports_extras'] = $request->boolean('supports_extras', false);
        $current['notes'] = trim((string) $request->input('notes', '')) ?: null;
        $current['catalog_source'] = 'service_catalog_matrix';
        $current['catalog_synced_at'] = now()->toDateTimeString();

        return $current;
    }

    protected function disablePair(int $rootId, int $childId, int $serviceId): void
    {
        CategoryPlatformService::query()
            ->where('category_id', $rootId)
            ->where('child_id', $childId)
            ->where('platform_service_id', $serviceId)
            ->update(['is_active' => 0, 'updated_at' => now()]);

        CategoryServiceConfig::query()
            ->where('category_id', $rootId)
            ->where('child_id', $childId)
            ->where('platform_service_id', $serviceId)
            ->update(['is_active' => 0, 'updated_at' => now()]);
    }
}
