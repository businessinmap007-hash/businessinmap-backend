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

   private function serviceFeePayload(Request $request, int $sortOrder = 1, ?int $serviceId = null): array
    {
        $serviceFeeInput = [];

        if ($serviceId) {
            $serviceFeeInput = $request->input("service_fees.{$serviceId}", []);
        }

        if (! is_array($serviceFeeInput)) {
            $serviceFeeInput = [];
        }

        $businessFeeEnabled = $this->toBool($serviceFeeInput['business_fee_enabled'] ?? null);
        $clientFeeEnabled = $this->toBool($serviceFeeInput['client_fee_enabled'] ?? null);

        $businessFeeType = PlatformService::normalizeFeeType($serviceFeeInput['business_fee_type'] ?? null)
            ?: PlatformService::FEE_TYPE_FIXED;

        $clientFeeType = PlatformService::normalizeFeeType($serviceFeeInput['client_fee_type'] ?? null)
            ?: PlatformService::FEE_TYPE_FIXED;

        $businessFeeAmount = $businessFeeEnabled
            ? $this->money($serviceFeeInput['business_fee_amount'] ?? 0)
            : 0.00;

        $clientFeeAmount = $clientFeeEnabled
            ? $this->money($serviceFeeInput['client_fee_amount'] ?? 0)
            : 0.00;

        if ($businessFeeAmount <= 0) {
            $businessFeeEnabled = false;
            $businessFeeAmount = 0.00;
            $businessFeeType = null;
        }

        if ($clientFeeAmount <= 0) {
            $clientFeeEnabled = false;
            $clientFeeAmount = 0.00;
            $clientFeeType = null;
        }

        $isActive = $businessFeeEnabled || $clientFeeEnabled;

        return [
            'business_fee_enabled' => $businessFeeEnabled ? 1 : 0,
            'business_fee_type' => $businessFeeType,
            'business_fee_amount' => $businessFeeAmount,

            'client_fee_enabled' => $clientFeeEnabled ? 1 : 0,
            'client_fee_type' => $clientFeeType,
            'client_fee_amount' => $clientFeeAmount,

            'currency' => $this->currency($serviceFeeInput['currency'] ?? CategoryChildServiceFee::DEFAULT_CURRENCY),
            'is_active' => $isActive ? 1 : 0,
            'sort_order' => $sortOrder,
            'notes' => trim((string) ($serviceFeeInput['fee_notes'] ?? '')) ?: null,
        ];
    }

    public function index(Request $request)
    {
        $rootId = (int) $request->get('root_id', 0);

        /*
        |--------------------------------------------------------------------------
        | Root Categories + Children
        |--------------------------------------------------------------------------
        */
        $roots = Category::query()
            ->where('parent_id', 0)
            ->with([
                'children' => function ($query) {
                    $query
                        ->select([
                            'category_children_master.id',
                            'category_children_master.name_ar',
                            'category_children_master.name_en',
                            'category_children_master.reorder',
                        ])
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

        $activeRootId = $rootId > 0
            ? $rootId
            : (int) optional($roots->first())->id;

        $activeRootChildren = $roots
            ->firstWhere('id', $activeRootId)?->children ?? collect();

        $activeChildIds = collect($activeRootChildren)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        $activeChildrenCount = count($activeChildIds);

        /*
        |--------------------------------------------------------------------------
        | Active Platform Services
        |--------------------------------------------------------------------------
        */
        $services = PlatformService::query()
            ->where('is_active', 1)
            ->orderBy('name_ar')
            ->orderBy('name_en')
            ->orderBy('id')
            ->get([
                'id',
                'key',
                'name_ar',
                'name_en',
                'supports_deposit',
                'max_deposit_percent',

                'business_fee_enabled',
                'business_fee_type',
                'business_fee_value',

                'client_fee_enabled',
                'client_fee_type',
                'client_fee_value',

                'fee_currency',
                'fee_notes',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Active Service Counts Per Root
        |--------------------------------------------------------------------------
        | لمعرفة كل خدمة مفعلة في كم فرع داخل الروت الحالي.
        |--------------------------------------------------------------------------
        */
        $activeServiceCounts = $this->activeServiceCountsForRoot(
            rootId: $activeRootId,
            childIds: $activeChildIds
        );

        /*
        |--------------------------------------------------------------------------
        | Existing Fee Matrix
        |--------------------------------------------------------------------------
        | تستخدم في الـ Blade / JavaScript لعرض الأسعار المحفوظة سابقًا.
        |--------------------------------------------------------------------------
        */
        $feeMatrix = $this->feeMatrixForChildren($activeChildIds);

        return view('admin-v2.categories.services-bulk', [
            'roots' => $roots,
            'services' => $services,
            'rootId' => $activeRootId,

            'activeServiceCounts' => $activeServiceCounts,
            'activeChildrenCount' => $activeChildrenCount,

            'feeMatrix' => $feeMatrix,
        ]);
    }
    private function activeServiceCountsForRoot(int $rootId, array $childIds): array
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
            ->mapWithKeys(fn ($total, $serviceId) => [
                (int) $serviceId => (int) $total,
            ])
            ->all();
    }
    private function feeMatrixForChildren(array $childIds): array
    {
        if (empty($childIds)) {
            return [];
        }

        $matrix = [];

        $feeRows = CategoryChildServiceFee::query()
            ->whereIn('child_id', $childIds)
            ->get([
                'child_id',
                'platform_service_id',

                'business_fee_enabled',
                'business_fee_type',
                'business_fee_amount',

                'client_fee_enabled',
                'client_fee_type',
                'client_fee_amount',

                'currency',
                'is_active',
                'notes',
            ]);

        foreach ($feeRows as $feeRow) {
            $childId = (int) $feeRow->child_id;
            $serviceId = (int) $feeRow->platform_service_id;

            if ($childId <= 0 || $serviceId <= 0) {
                continue;
            }

            $matrix[$childId][$serviceId] = [
                'business_fee_enabled' => (bool) $feeRow->business_fee_enabled,
                'business_fee_type' => $feeRow->business_fee_type ?: PlatformService::FEE_TYPE_FIXED,
                'business_fee_amount' => round((float) $feeRow->business_fee_amount, 2),

                'client_fee_enabled' => (bool) $feeRow->client_fee_enabled,
                'client_fee_type' => $feeRow->client_fee_type ?: PlatformService::FEE_TYPE_FIXED,
                'client_fee_amount' => round((float) $feeRow->client_fee_amount, 2),

                'currency' => $feeRow->currency ?: CategoryChildServiceFee::DEFAULT_CURRENCY,
                'is_active' => (bool) $feeRow->is_active,
                'fee_notes' => $feeRow->notes,
            ];
        }

        return $matrix;
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
            'service_fees' => ['nullable', 'array'],

            'service_fees.*.business_fee_enabled' => ['nullable'],
            'service_fees.*.business_fee_type' => ['nullable', 'in:fixed,percent'],
            'service_fees.*.business_fee_amount' => ['nullable', 'numeric', 'min:0'],

            'service_fees.*.client_fee_enabled' => ['nullable'],
            'service_fees.*.client_fee_type' => ['nullable', 'in:fixed,percent'],
            'service_fees.*.client_fee_amount' => ['nullable', 'numeric', 'min:0'],

            'service_fees.*.currency' => ['nullable', 'string', 'max:10'],
            'service_fees.*.fee_notes' => ['nullable', 'string', 'max:1000'],
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
                $this->serviceFeePayload($request, $order, (int) $serviceId)
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