<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryChild;
use App\Models\CategoryChildServiceFee;
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

    private function money($value): float
    {
        return round(max((float) $value, 0), 2);
    }

    private function currency($value): string
    {
        $currency = strtoupper(trim((string) $value));

        return $currency !== ''
            ? mb_substr($currency, 0, 3)
            : CategoryChildServiceFee::DEFAULT_CURRENCY;
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
            PlatformService::KEY_BOOKING => $this->bookingConfigPayload($request),
            PlatformService::KEY_MENU => $this->menuConfigPayload($request),
            PlatformService::KEY_DELIVERY => $this->deliveryConfigPayload($request),
            default => [],
        };
    }

    private function serviceFeePayload(Request $request, int $sortOrder = 1): array
    {
        $businessFeeEnabled = $this->toBool($request->input('business_fee_enabled'));
        $clientFeeEnabled = $this->toBool($request->input('client_fee_enabled'));

        $businessFeeAmount = $businessFeeEnabled
            ? $this->money($request->input('business_fee_amount', 0))
            : 0.00;

        $clientFeeAmount = $clientFeeEnabled
            ? $this->money($request->input('client_fee_amount', 0))
            : 0.00;

        if ($businessFeeAmount <= 0) {
            $businessFeeEnabled = false;
            $businessFeeAmount = 0.00;
        }

        if ($clientFeeAmount <= 0) {
            $clientFeeEnabled = false;
            $clientFeeAmount = 0.00;
        }

        $isActive = $businessFeeEnabled || $clientFeeEnabled;

        return [
            'business_fee_enabled' => $businessFeeEnabled ? 1 : 0,
            'business_fee_amount' => $businessFeeAmount,
            'client_fee_enabled' => $clientFeeEnabled ? 1 : 0,
            'client_fee_amount' => $clientFeeAmount,
            'currency' => $this->currency($request->input('currency', CategoryChildServiceFee::DEFAULT_CURRENCY)),
            'is_active' => $isActive ? 1 : 0,
            'sort_order' => $sortOrder,
            'notes' => trim((string) $request->input('fee_notes', '')) ?: null,
        ];
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

            /*
            |--------------------------------------------------------------------------
            | الاسم category_ids كما هو لتوافق الـ UI الحالي
            |--------------------------------------------------------------------------
            | المقصود هنا هو category_children_master ids.
            */
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', 'exists:category_children_master,id'],

            'platform_service_ids' => ['required', 'array', 'min:1'],
            'platform_service_ids.*' => ['integer', 'exists:platform_services,id'],

            'mode' => ['required', 'in:append,replace,remove'],

            /*
            |--------------------------------------------------------------------------
            | Default Service Fees applied with the selected services
            |--------------------------------------------------------------------------
            */
            'business_fee_enabled' => ['nullable'],
            'business_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'client_fee_enabled' => ['nullable'],
            'client_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'fee_notes' => ['nullable', 'string', 'max:1000'],
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
            ->where('is_active', 1)
            ->get()
            ->keyBy('id');

        if ($services->isEmpty()) {
            return back()
                ->withErrors(['platform_service_ids' => 'اختر خدمة مفعلة واحدة على الأقل.'])
                ->withInput();
        }

        $mode = (string) $data['mode'];

        DB::transaction(function () use ($validChildIds, $serviceIds, $services, $mode, $request, $root) {
            foreach ($validChildIds as $childId) {
                if ($mode === 'replace') {
                    $this->replaceChildServices(
                        rootId: (int) $root->id,
                        childId: (int) $childId,
                        serviceIds: $serviceIds,
                        services: $services,
                        request: $request
                    );

                    continue;
                }

                if ($mode === 'append') {
                    $this->appendChildServices(
                        rootId: (int) $root->id,
                        childId: (int) $childId,
                        serviceIds: $serviceIds,
                        services: $services,
                        request: $request
                    );

                    continue;
                }

                if ($mode === 'remove') {
                    $this->removeChildServices(
                        rootId: (int) $root->id,
                        childId: (int) $childId,
                        serviceIds: $serviceIds
                    );
                }
            }
        });

        return redirect()
            ->route('admin.categories.index', ['root_id' => $root->id])
            ->with('success', 'تم تطبيق الخدمات وإعداداتها ورسومها على الأقسام الفرعية المختارة بنجاح.');
    }

    private function replaceChildServices(
        int $rootId,
        int $childId,
        array $serviceIds,
        $services,
        Request $request
    ): void {
        CategoryPlatformService::query()
            ->where('category_id', $rootId)
            ->where('child_id', $childId)
            ->delete();

        CategoryServiceConfig::query()
            ->where('category_id', $rootId)
            ->where('child_id', $childId)
            ->delete();

        /*
        | لا نحذف كل رسوم الطفل لأنها لا تحتوي root_id.
        | نعطل فقط رسوم الخدمات التي لم تعد موجودة ضمن replace الحالي.
        */
        CategoryChildServiceFee::query()
            ->where('child_id', $childId)
            ->whereNotIn('platform_service_id', $serviceIds)
            ->update([
                'is_active' => 0,
                'business_fee_enabled' => 0,
                'business_fee_amount' => 0,
                'client_fee_enabled' => 0,
                'client_fee_amount' => 0,
                'updated_at' => now(),
            ]);

        $order = 1;

        foreach ($serviceIds as $serviceId) {
            $service = $services->get($serviceId);

            if (! $service) {
                continue;
            }

            CategoryPlatformService::query()->create([
                'category_id' => $rootId,
                'child_id' => $childId,
                'platform_service_id' => $serviceId,
                'is_active' => true,
                'sort_order' => $order,
                'meta' => null,
            ]);

            CategoryServiceConfig::query()->create([
                'category_id' => $rootId,
                'child_id' => $childId,
                'platform_service_id' => $serviceId,
                'config' => $this->serviceConfigPayload($request, $service),
                'is_active' => true,
                'sort_order' => $order,
            ]);

            CategoryChildServiceFee::query()->updateOrCreate(
                [
                    'child_id' => $childId,
                    'platform_service_id' => $serviceId,
                ],
                $this->serviceFeePayload($request, $order)
            );

            $order++;
        }
    }

    private function appendChildServices(
        int $rootId,
        int $childId,
        array $serviceIds,
        $services,
        Request $request
    ): void {
        $currentMaxSort = (int) CategoryPlatformService::query()
            ->where('category_id', $rootId)
            ->where('child_id', $childId)
            ->max('sort_order');

        $nextSort = $currentMaxSort > 0 ? $currentMaxSort + 1 : 1;

        foreach ($serviceIds as $serviceId) {
            $service = $services->get($serviceId);

            if (! $service) {
                continue;
            }

            $link = CategoryPlatformService::query()
                ->where('category_id', $rootId)
                ->where('child_id', $childId)
                ->where('platform_service_id', $serviceId)
                ->first();

            if ($link) {
                $sortOrder = (int) ($link->sort_order ?: $nextSort);

                $link->update([
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                    'meta' => $link->meta,
                ]);
            } else {
                $sortOrder = $nextSort;

                CategoryPlatformService::query()->create([
                    'category_id' => $rootId,
                    'child_id' => $childId,
                    'platform_service_id' => $serviceId,
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                    'meta' => null,
                ]);

                $nextSort++;
            }

            CategoryServiceConfig::query()->updateOrCreate(
                [
                    'category_id' => $rootId,
                    'child_id' => $childId,
                    'platform_service_id' => $serviceId,
                ],
                [
                    'config' => $this->serviceConfigPayload($request, $service),
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                ]
            );

            CategoryChildServiceFee::query()->updateOrCreate(
                [
                    'child_id' => $childId,
                    'platform_service_id' => $serviceId,
                ],
                $this->serviceFeePayload($request, $sortOrder)
            );
        }
    }

    private function removeChildServices(int $rootId, int $childId, array $serviceIds): void
    {
        CategoryPlatformService::query()
            ->where('category_id', $rootId)
            ->where('child_id', $childId)
            ->whereIn('platform_service_id', $serviceIds)
            ->update([
                'is_active' => 0,
                'updated_at' => now(),
            ]);

        CategoryServiceConfig::query()
            ->where('category_id', $rootId)
            ->where('child_id', $childId)
            ->whereIn('platform_service_id', $serviceIds)
            ->update([
                'is_active' => 0,
                'updated_at' => now(),
            ]);

        CategoryChildServiceFee::query()
            ->where('child_id', $childId)
            ->whereIn('platform_service_id', $serviceIds)
            ->update([
                'is_active' => 0,
                'business_fee_enabled' => 0,
                'business_fee_amount' => 0,
                'client_fee_enabled' => 0,
                'client_fee_amount' => 0,
                'updated_at' => now(),
            ]);
    }
}