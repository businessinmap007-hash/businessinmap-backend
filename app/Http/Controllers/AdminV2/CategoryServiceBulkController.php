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

    public function apply(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'root_id' => ['required', 'integer', 'exists:categories,id'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'platform_service_ids' => ['required', 'array', 'min:1'],
            'platform_service_ids.*' => ['integer', 'exists:platform_services,id'],
            'mode' => ['required', 'in:append,replace,remove'],
        ]);

        $root = Category::query()
            ->where('id', (int) $data['root_id'])
            ->where('parent_id', 0)
            ->first();

        if (! $root) {
            return back()->withErrors(['root_id' => 'القسم الرئيسي غير صحيح.'])->withInput();
        }

        $categoryIds = collect($data['category_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $validCategoryIds = Category::query()
            ->where('parent_id', $root->id)
            ->whereIn('id', $categoryIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($validCategoryIds)) {
            return back()->withErrors(['category_ids' => 'اختر تصنيفًا فرعيًا واحدًا على الأقل من نفس القسم الرئيسي.'])->withInput();
        }

        $serviceIds = collect($data['platform_service_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $services = PlatformService::query()
            ->whereIn('id', $serviceIds)
            ->get()
            ->keyBy('id');

        $mode = (string) $data['mode'];

        DB::transaction(function () use ($validCategoryIds, $serviceIds, $services, $mode, $request) {
            foreach ($validCategoryIds as $categoryId) {
                if ($mode === 'replace') {
                    CategoryPlatformService::query()->where('category_id', $categoryId)->delete();
                    CategoryServiceConfig::query()->where('category_id', $categoryId)->delete();

                    $order = 1;
                    foreach ($serviceIds as $serviceId) {
                        CategoryPlatformService::create([
                            'category_id' => $categoryId,
                            'platform_service_id' => $serviceId,
                            'is_active' => true,
                            'sort_order' => $order,
                            'meta' => null,
                        ]);

                        $service = $services->get($serviceId);
                        if ($service) {
                            CategoryServiceConfig::create([
                                'category_id' => $categoryId,
                                'platform_service_id' => $serviceId,
                                'config' => $this->serviceConfigPayload($request, $service),
                                'is_active' => true,
                                'sort_order' => $order,
                            ]);
                        }

                        $order++;
                    }
                }

                if ($mode === 'append') {
                    $existingServiceIds = CategoryPlatformService::query()
                        ->where('category_id', $categoryId)
                        ->pluck('platform_service_id')
                        ->map(fn ($id) => (int) $id)
                        ->all();

                    $currentMaxSort = (int) CategoryPlatformService::query()
                        ->where('category_id', $categoryId)
                        ->max('sort_order');

                    foreach ($serviceIds as $serviceId) {
                        if (in_array($serviceId, $existingServiceIds, true)) {
                            continue;
                        }

                        $currentMaxSort++;

                        CategoryPlatformService::create([
                            'category_id' => $categoryId,
                            'platform_service_id' => $serviceId,
                            'is_active' => true,
                            'sort_order' => $currentMaxSort,
                            'meta' => null,
                        ]);

                        $service = $services->get($serviceId);
                        if ($service) {
                            CategoryServiceConfig::updateOrCreate(
                                [
                                    'category_id' => $categoryId,
                                    'platform_service_id' => $serviceId,
                                ],
                                [
                                    'config' => $this->serviceConfigPayload($request, $service),
                                    'is_active' => true,
                                    'sort_order' => $currentMaxSort,
                                ]
                            );
                        }
                    }
                }

                if ($mode === 'remove') {
                    CategoryPlatformService::query()
                        ->where('category_id', $categoryId)
                        ->whereIn('platform_service_id', $serviceIds)
                        ->delete();

                    CategoryServiceConfig::query()
                        ->where('category_id', $categoryId)
                        ->whereIn('platform_service_id', $serviceIds)
                        ->delete();
                }
            }
        });

        return redirect()
            ->route('admin.categories.index', ['root_id' => $data['root_id']])
            ->with('success', 'تم تطبيق الخدمات على التصنيفات المختارة بنجاح.');
    }
}