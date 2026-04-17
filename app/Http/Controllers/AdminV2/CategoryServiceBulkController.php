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

class CategoryServiceBulkController extends Controller
{
    private function toBool($value, $default = false): bool
    {
        if ($value === null) {
            return $default;
        }

        return in_array($value, [1, '1', true, 'true', 'on', 'yes'], true);
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

    public function index(Request $request)
    {
        $rootId = (int) $request->get('root_id', 0);

        return redirect()->route('admin.categories.index', $rootId > 0 ? ['root_id' => $rootId] : []);
    }

    public function apply(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'root_id' => ['required', 'integer', 'exists:categories,id'],

            // الاسم كما هو لتوافق الـ UI الحالي
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', 'exists:category_children_master,id'],

            'platform_service_ids' => ['required', 'array', 'min:1'],
            'platform_service_ids.*' => ['integer', 'exists:platform_services,id'],

            'mode' => ['required', 'in:append,replace,remove'],
        ]);

        $root = Category::query()
            ->where('id', (int) $data['root_id'])
            ->where('parent_id', 0)
            ->first();

        if (! $root) {
            return back()
                ->withErrors(['root_id' => 'القسم الرئيسي غير صحيح.'])
                ->withInput();
        }

        $childIds = collect($data['category_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $validChildIds = CategoryChild::query()
            ->whereIn('id', $childIds->all())
            ->whereHas('parents', function ($query) use ($root) {
                $query->where('categories.id', $root->id);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($validChildIds)) {
            return back()
                ->withErrors(['category_ids' => 'اختر قسمًا فرعيًا واحدًا على الأقل مرتبطًا بنفس القسم الرئيسي.'])
                ->withInput();
        }

        $serviceIds = collect($data['platform_service_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $services = PlatformService::query()
            ->whereIn('id', $serviceIds)
            ->get()
            ->keyBy('id');

        $mode = (string) $data['mode'];

        DB::transaction(function () use ($validChildIds, $serviceIds, $services, $mode, $request, $root) {
            foreach ($validChildIds as $childId) {
                if ($mode === 'replace') {
                    CategoryPlatformService::query()
                        ->where('child_id', $childId)
                        ->delete();

                    CategoryServiceConfig::query()
                        ->where('child_id', $childId)
                        ->delete();

                    $order = 1;

                    foreach ($serviceIds as $serviceId) {
                        CategoryPlatformService::query()->create([
                            'category_id' => $root->id,
                            'child_id' => $childId,
                            'platform_service_id' => $serviceId,
                            'is_active' => true,
                            'sort_order' => $order,
                            'meta' => null,
                        ]);

                        $service = $services->get($serviceId);
                        if ($service) {
                            CategoryServiceConfig::query()->create([
                                'category_id' => $root->id,
                                'child_id' => $childId,
                                'platform_service_id' => $serviceId,
                                'config' => $this->serviceConfigPayload($request, $service),
                                'is_active' => true,
                                'sort_order' => $order,
                            ]);
                        }

                        $order++;
                    }

                    continue;
                }

                if ($mode === 'append') {
                    $existingServiceIds = CategoryPlatformService::query()
                        ->where('child_id', $childId)
                        ->pluck('platform_service_id')
                        ->map(fn ($id) => (int) $id)
                        ->all();

                    $currentMaxSort = (int) CategoryPlatformService::query()
                        ->where('child_id', $childId)
                        ->max('sort_order');

                    foreach ($serviceIds as $serviceId) {
                        if (! in_array($serviceId, $existingServiceIds, true)) {
                            $currentMaxSort++;

                            CategoryPlatformService::query()->create([
                                'category_id' => $root->id,
                                'child_id' => $childId,
                                'platform_service_id' => $serviceId,
                                'is_active' => true,
                                'sort_order' => $currentMaxSort,
                                'meta' => null,
                            ]);
                        }

                        $service = $services->get($serviceId);
                        if ($service) {
                            CategoryServiceConfig::query()->updateOrCreate(
                                [
                                    'child_id' => $childId,
                                    'platform_service_id' => $serviceId,
                                ],
                                [
                                    'category_id' => $root->id,
                                    'config' => $this->serviceConfigPayload($request, $service),
                                    'is_active' => true,
                                    'sort_order' => max($currentMaxSort, 1),
                                ]
                            );
                        }
                    }

                    continue;
                }

                if ($mode === 'remove') {
                    CategoryPlatformService::query()
                        ->where('child_id', $childId)
                        ->whereIn('platform_service_id', $serviceIds)
                        ->delete();

                    CategoryServiceConfig::query()
                        ->where('child_id', $childId)
                        ->whereIn('platform_service_id', $serviceIds)
                        ->delete();
                }
            }
        });

        return redirect()
            ->route('admin.categories.index', ['root_id' => $root->id])
            ->with('success', 'تم تطبيق الخدمات وإعداداتها على الأقسام الفرعية المختارة بنجاح.');
    }
}