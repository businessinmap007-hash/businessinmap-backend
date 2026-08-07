<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryChild;
use App\Models\CategoryChildServiceFee;
use App\Models\CategoryPlatformService;
use App\Models\CategoryServiceConfig;
use App\Models\PlatformService;
use App\Models\PlatformServiceItemGroup;
use App\Models\PlatformServiceItemType;
use Illuminate\Http\RedirectResponse;
use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CategoryServiceBulkController extends Controller
{
    public function __construct(private readonly ChildServiceWriter $writer)
    {
    }

    private function toBool($value, bool $default = false): bool
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
            ->filter(fn ($v) => $v !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeIntArray($value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Branch (item_group) ids the admin ticked for one service in the bulk form.
     */
    private function selectedGroupIds(Request $request, int $serviceId): array
    {
        return $this->normalizeIntArray($request->input("item_groups.{$serviceId}", []));
    }

    /**
     * Did the admin actually work the type picker in THIS save?
     *
     * The picker is filled from the first selected child, so what comes back in
     * `allowed_item_types` is only a proposal until a human moves a checkbox.
     * The blade raises this flag on the first change event — see the note on
     * resolveAllowedItemTypes for what it protects.
     */
    private function typesTouched(Request $request, int $serviceId): bool
    {
        return $this->toBool($request->input("types_touched.{$serviceId}"));
    }

    /**
     * The allowed item-type KEYS for a (service) selection: the union of every
     * type reachable through the ticked branches PLUS any individually ticked
     * (ungrouped / fine-tuned) types. This is what the owner panel later filters
     * its pickable types against (CategoryServiceConfig.config.allowed_item_types).
     *
     * @param  array<string,mixed>  $stored  the child's own config, as it stands
     */
    private function resolveAllowedItemTypes(Request $request, int $serviceId, array $stored = []): array
    {
        /*
         * One picker, many children — so an untouched picker keeps its hands off.
         *
         * The screen shows a SINGLE type picker for the whole batch and fills it
         * from the FIRST selected child; when the children disagree it says so in
         * a «mixed» warning and carries on. Saving then wrote that one list to
         * every selected child, so ticking five health children to turn a service
         * on flattened them onto one vocabulary: on 2026-08-07 22:04 مستشفى،
         * عيادة، مركز طبي، نادي صحي and معمل تحاليل all ended the save carrying
         * the same five kinds — a laboratory offering إجراء طبي and a health club
         * offering كشف, while the lab lost سحب عينة بالمنزل, the one kind it
         * exists for.
         *
         * The admin had said nothing about vocabulary; they had said «these
         * children may sell bookings». So a list is written to the batch only
         * when it was chosen in this save. Otherwise each child keeps its own,
         * which is the whole point of the per-child assignment in
         * service_kinds.php and in the child workbench.
         */
        if (! $this->typesTouched($request, $serviceId)) {
            $kept = $this->normalizeArray($stored['allowed_item_types'] ?? []);

            if (! empty($kept)) {
                return $kept;
            }
        }

        $explicit = $this->normalizeArray($request->input("allowed_item_types.{$serviceId}", []));

        /*
         * The ticked TYPES win, and a branch is expanded to all its types only
         * when none inside it were ticked individually.
         *
         * Before the kinds collapse a branch was a coarse group and ticking it
         * to mean "all of these" was right. Now a single branch — «أنواع
         * الحجز» — holds 11 kinds a child chooses AMONG (a clinic takes كشف،
         * متابعة، زيارة منزلية, not إجراء طبي or حجز طاولة). Unioning the whole
         * branch overwrote that four-kind choice with all eleven on every save,
         * silently undoing what was set in the child workbench.
         */
        if (! empty($explicit)) {
            return $explicit;
        }

        $groupIds = $this->selectedGroupIds($request, $serviceId);

        if (empty($groupIds)) {
            return [];
        }

        return DB::table('platform_service_item_group_type as gt')
            ->join('platform_service_item_types as t', 't.id', '=', 'gt.item_type_id')
            ->whereIn('gt.group_id', $groupIds)
            ->where('t.platform_service_id', $serviceId)
            ->where('t.is_active', 1)
            ->pluck('t.key')
            ->map(fn ($k) => trim((string) $k))
            ->filter(fn ($k) => $k !== '')
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

    private function normalizeFeeType($value): ?string
    {
        $type = strtolower(trim((string) $value));

        return in_array($type, [
            CategoryChildServiceFee::CALC_TYPE_FIXED,
            CategoryChildServiceFee::CALC_TYPE_PERCENT,
        ], true) ? $type : null;
    }

    private function bookingConfigPayload(Request $request, array $stored = []): array
    {
        return [
            'booking_modes' => $this->normalizeArray($request->input('booking_modes')),
            'item_family' => trim((string) $request->input('item_family', '')) ?: null,
            // Falls back to the STORED value, not a blanket true: the
            // direct-booking classification turns this off per child, and a
            // default of true would silently re-arm the unit list on any save.
            'requires_bookable_item' => $this->toBool(
                $request->input('requires_bookable_item'),
                (bool) ($stored['requires_bookable_item'] ?? true)
            ),
            'requires_start_end' => $this->toBool($request->input('requires_start_end'), true),
            'supports_quantity' => $this->toBool($request->input('supports_quantity')),
            'supports_guest_count' => $this->toBool($request->input('supports_guest_count')),
            'supports_extras' => $this->toBool($request->input('supports_extras')),
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
            'max_radius_km' => max(0, (int) $request->input('delivery_max_radius_km', 0)),
            'supports_scheduled_delivery' => $this->toBool($request->input('delivery_supports_scheduled')),
        ];
    }

    private function retailConfigPayload(Request $request): array
    {
        // Retail behaviour is minimal — price/stock live on business_catalog_listings,
        // not in the config. The branch picker (item_groups / allowed_item_types) is
        // appended generically for every service in serviceConfigPayload().
        return [
            'supports_stock' => $this->toBool($request->input('retail_supports_stock'), true),
        ];
    }

    private function serviceConfigPayload(Request $request, PlatformService $service, ?int $rootId = null, ?int $childId = null): array
    {
        // What is stored now, so a flag the admin did not submit keeps its
        // value instead of snapping back to a blanket default — see
        // bookingConfigPayload's requires_bookable_item.
        $stored = [];

        if ($rootId && $childId) {
            $existing = CategoryServiceConfig::query()
                ->where('category_id', $rootId)
                ->where('child_id', $childId)
                ->where('platform_service_id', $service->id)
                ->value('config');

            $stored = is_array($existing) ? $existing : (json_decode((string) $existing, true) ?: []);
        }

        $config = match ((string) $service->key) {
            PlatformService::KEY_BOOKING => $this->bookingConfigPayload($request, $stored),
            PlatformService::KEY_MENU => $this->menuConfigPayload($request),
            PlatformService::KEY_DELIVERY => $this->deliveryConfigPayload($request),
            PlatformService::KEY_RETAIL => $this->retailConfigPayload($request),
            default => [],
        };

        /*
        |--------------------------------------------------------------------------
        | Branch-driven allowed item types (services-bulk §4)
        |--------------------------------------------------------------------------
        | The admin picks item_groups (branches) appropriate to the child; we keep
        | the ticked group ids for round-tripping the UI, and store the expanded
        | union of their item-type keys as `allowed_item_types` — the single field
        | the owner panel / booking read paths already consume. Applies to every
        | service, not only booking.
        |--------------------------------------------------------------------------
        */
        $groups = $this->selectedGroupIds($request, (int) $service->id);

        // The branch ticks are the same one-picker-many-children proposal the
        // types are, and they answer the same way.
        if (! $this->typesTouched($request, (int) $service->id) && ! empty($stored['item_groups'])) {
            $groups = $this->normalizeIntArray($stored['item_groups']);
        }

        $config['item_groups'] = $groups;
        $config['allowed_item_types'] = $this->resolveAllowedItemTypes($request, (int) $service->id, $stored);

        return $config;
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

        $businessFeeType = $this->normalizeFeeType($serviceFeeInput['business_fee_type'] ?? null)
            ?: CategoryChildServiceFee::CALC_TYPE_FIXED;

        $clientFeeType = $this->normalizeFeeType($serviceFeeInput['client_fee_type'] ?? null)
            ?: CategoryChildServiceFee::CALC_TYPE_FIXED;

        $businessFeeAmount = $this->money($serviceFeeInput['business_fee_amount'] ?? 0);
        $clientFeeAmount = $this->money($serviceFeeInput['client_fee_amount'] ?? 0);

        if (! $businessFeeEnabled || $businessFeeAmount <= 0) {
            $businessFeeEnabled = false;
            $businessFeeType = null;
            $businessFeeAmount = 0.00;
        }

        if (! $clientFeeEnabled || $clientFeeAmount <= 0) {
            $clientFeeEnabled = false;
            $clientFeeType = null;
            $clientFeeAmount = 0.00;
        }

        return [
            'business_fee_enabled' => $businessFeeEnabled ? 1 : 0,
            'business_fee_type' => $businessFeeType,
            'business_fee_amount' => $businessFeeAmount,

            'client_fee_enabled' => $clientFeeEnabled ? 1 : 0,
            'client_fee_type' => $clientFeeType,
            'client_fee_amount' => $clientFeeAmount,

            'currency' => $this->currency($serviceFeeInput['currency'] ?? CategoryChildServiceFee::DEFAULT_CURRENCY),
            'is_active' => ($businessFeeEnabled || $clientFeeEnabled) ? 1 : 0,
            'sort_order' => max(1, $sortOrder),
            'notes' => trim((string) ($serviceFeeInput['fee_notes'] ?? '')) ?: null,
        ];
    }

    public function index(Request $request): View
    {
        $rootId = (int) $request->get('root_id', 0);

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

        $activeRoot = $rootId > 0
            ? $roots->firstWhere('id', $rootId)
            : $roots->first();

        if (! $activeRoot) {
            $activeRoot = $roots->first();
        }

        $activeRootId = (int) optional($activeRoot)->id;
        $activeRootChildren = $activeRoot?->children ?? collect();

        $activeChildIds = collect($activeRootChildren)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        $activeChildrenCount = count($activeChildIds);

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
            ]);

        $activeServiceCounts = $this->activeServiceCountsForRoot(
            rootId: $activeRootId,
            childIds: $activeChildIds
        );

        $feeMatrix = $this->feeMatrixForRootChildren(
            rootId: $activeRootId,
            childIds: $activeChildIds
        );

        $serviceBranches = $this->serviceBranches(
            services: $services,
            inUse: $this->branchesUsedByRoot($activeRootId, $activeChildIds)
        );

        $configMatrix = $this->configMatrixForRootChildren(
            rootId: $activeRootId,
            childIds: $activeChildIds
        );

        return view('admin-v2.categories.services-bulk', [
            'roots' => $roots,
            'services' => $services,
            'rootId' => $activeRootId,
            'activeServiceCounts' => $activeServiceCounts,
            'activeChildrenCount' => $activeChildrenCount,
            'feeMatrix' => $feeMatrix,
            'serviceBranches' => $serviceBranches,
            'configMatrix' => $configMatrix,
        ]);
    }

    /**
     * Per-service branch tree for the "allowed types" picker:
     * [serviceId => ['branches' => [['id','name','types'=>[['id','key','name']]]], 'ungrouped' => [...]]].
     * A type can appear under several branches (many-to-many); types with no
     * branch fall into `ungrouped` so they stay reachable.
     */
    /**
     * The branch ids the CURRENT root's children actually use.
     *
     * The picker used to show every branch of a service whatever root was
     * selected — 21 of them, most belonging to trades the root has nothing to
     * do with. That is not merely noise: a screen that lists everything invites
     * ticking everything, and a single save then writes that whole list over
     * what the root had been given carefully.
     *
     * @param  array<int,int>  $childIds
     * @return array<int,true>
     */
    private function branchesUsedByRoot(int $rootId, array $childIds): array
    {
        if ($rootId <= 0 || empty($childIds)) {
            return [];
        }

        $used = [];

        foreach (
            CategoryServiceConfig::query()
                ->where('category_id', $rootId)
                ->whereIn('child_id', $childIds)
                ->where('is_active', 1)
                ->pluck('config') as $config
        ) {
            $config = is_array($config) ? $config : (json_decode((string) $config, true) ?: []);

            foreach ($this->normalizeIntArray($config['item_groups'] ?? []) as $id) {
                $used[$id] = true;
            }
        }

        return $used;
    }

    /**
     * @param  array<int,true>  $inUse  branch ids this root's children already use
     */
    private function serviceBranches($services, array $inUse = []): array
    {
        $serviceIds = collect($services)->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (empty($serviceIds)) {
            return [];
        }

        $typeRows = PlatformServiceItemType::query()
            ->whereIn('platform_service_id', $serviceIds)
            ->where('is_active', 1)
            ->with('groups:id')
            ->ordered()
            ->get(['id', 'platform_service_id', 'key', 'name_ar', 'name_en']);

        $groupNames = PlatformServiceItemGroup::query()
            ->ordered()
            ->get(['id', 'name_ar', 'name_en'])
            ->keyBy('id');

        $result = [];

        foreach ($services as $service) {
            $serviceId = (int) $service->id;
            $branchMap = [];
            $ungrouped = [];

            foreach ($typeRows->where('platform_service_id', $serviceId) as $type) {
                $typeArr = [
                    'id' => (int) $type->id,
                    'key' => (string) $type->key,
                    'name' => $type->displayName('ar'),
                ];

                $groupIds = $type->groups->pluck('id')->map(fn ($id) => (int) $id)->all();

                if (empty($groupIds)) {
                    $ungrouped[] = $typeArr;
                    continue;
                }

                foreach ($groupIds as $groupId) {
                    $group = $groupNames->get($groupId);

                    if (! $group) {
                        continue;
                    }

                    if (! isset($branchMap[$groupId])) {
                        $branchMap[$groupId] = [
                            'id' => $groupId,
                            'name' => $group->displayName('ar'),
                            // Tagged, never filtered out: a branch this root has
                            // not used yet is exactly what you need visible to
                            // start using it. The screen shows the relevant ones
                            // and folds the rest behind «أظهر كل الفروع».
                            'in_use' => isset($inUse[$groupId]),
                            'types' => [],
                        ];
                    }

                    $branchMap[$groupId]['types'][] = $typeArr;
                }
            }

            // Relevant first, so the ones this root works with are what the eye
            // lands on; ties keep the branch ordering the seeders set.
            uasort($branchMap, fn ($a, $b) => ($b['in_use'] <=> $a['in_use']) ?: ($a['id'] <=> $b['id']));

            $result[$serviceId] = [
                'branches' => array_values($branchMap),
                'ungrouped' => $ungrouped,
                'in_use_count' => count(array_filter($branchMap, fn ($b) => $b['in_use'])),
            ];
        }

        return $result;
    }

    /**
     * Existing allowed-types selection per child+service, for pre-filling the
     * picker: [childId => [serviceId => ['item_groups'=>[], 'allowed_item_types'=>[]]]].
     */
    private function configMatrixForRootChildren(int $rootId, array $childIds): array
    {
        if ($rootId <= 0 || empty($childIds)) {
            return [];
        }

        $matrix = [];

        $rows = CategoryServiceConfig::query()
            ->where('category_id', $rootId)
            ->whereIn('child_id', $childIds)
            ->where('is_active', 1)
            ->get(['child_id', 'platform_service_id', 'config']);

        foreach ($rows as $row) {
            $childId = (int) $row->child_id;
            $serviceId = (int) $row->platform_service_id;

            if ($childId <= 0 || $serviceId <= 0) {
                continue;
            }

            $config = is_array($row->config) ? $row->config : [];

            $matrix[$childId][$serviceId] = [
                'item_groups' => $this->normalizeIntArray($config['item_groups'] ?? []),
                'allowed_item_types' => $this->normalizeArray($config['allowed_item_types'] ?? []),
            ];
        }

        return $matrix;
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

    private function feeMatrixForRootChildren(int $rootId, array $childIds): array
    {
        if ($rootId <= 0 || empty($childIds)) {
            return [];
        }

        $matrix = [];

        $feeRows = CategoryChildServiceFee::query()
            ->where('category_id', $rootId)
            ->whereIn('child_id', $childIds)
            ->get([
                'category_id',
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
                'category_id' => (int) $feeRow->category_id,

                'business_fee_enabled' => (bool) $feeRow->business_fee_enabled,
                'business_fee_type' => $feeRow->business_fee_type ?: CategoryChildServiceFee::CALC_TYPE_FIXED,
                'business_fee_amount' => round((float) $feeRow->business_fee_amount, 2),

                'client_fee_enabled' => (bool) $feeRow->client_fee_enabled,
                'client_fee_type' => $feeRow->client_fee_type ?: CategoryChildServiceFee::CALC_TYPE_FIXED,
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
            |--------------------------------------------------------------------------
            */
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', 'exists:category_children_master,id'],

            'platform_service_ids' => ['required', 'array', 'min:1'],
            'platform_service_ids.*' => ['integer', 'exists:platform_services,id'],

            'mode' => ['required', 'in:append,replace,remove'],

            'service_fees' => ['nullable', 'array'],

            'service_fees.*.business_fee_enabled' => ['nullable'],
            'service_fees.*.business_fee_type' => ['nullable', 'in:fixed,percent'],
            'service_fees.*.business_fee_amount' => ['nullable', 'numeric', 'min:0'],

            'service_fees.*.client_fee_enabled' => ['nullable'],
            'service_fees.*.client_fee_type' => ['nullable', 'in:fixed,percent'],
            'service_fees.*.client_fee_amount' => ['nullable', 'numeric', 'min:0'],

            'service_fees.*.currency' => ['nullable', 'string', 'max:10'],
            'service_fees.*.fee_notes' => ['nullable', 'string', 'max:1000'],

            // Branch (item_group) selection per service — services-bulk §4.
            'item_groups' => ['nullable', 'array'],
            'item_groups.*' => ['nullable', 'array'],
            'item_groups.*.*' => ['integer', 'exists:platform_service_item_groups,id'],

            'allowed_item_types' => ['nullable', 'array'],
            'allowed_item_types.*' => ['nullable', 'array'],
            'allowed_item_types.*.*' => ['string', 'max:191'],

            // Raised by the blade the first time a human moves a branch/type
            // checkbox, per service — see typesTouched().
            'types_touched' => ['nullable', 'array'],
            'types_touched.*' => ['nullable'],
        ], [], [
            'root_id' => __('القسم الرئيسي'),
            'category_ids' => __('الأقسام الفرعية'),
            'platform_service_ids' => __('الخدمات'),
            'mode' => __('طريقة التطبيق'),
            'service_fees.*.business_fee_type' => __('نوع رسوم البزنس'),
            'service_fees.*.business_fee_amount' => __('قيمة رسوم البزنس'),
            'service_fees.*.client_fee_type' => __('نوع رسوم العميل'),
            'service_fees.*.client_fee_amount' => __('قيمة رسوم العميل'),
            'service_fees.*.currency' => __('العملة'),
            'service_fees.*.fee_notes' => __('ملاحظات الرسوم'),
        ]);

        $root = Category::query()
            ->where('id', (int) $data['root_id'])
            ->where('parent_id', 0)
            ->first();

        if (! $root) {
            return back()
                ->withErrors(['root_id' => __('القسم الرئيسي غير صحيح.')])
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
                $query->where('categories.id', (int) $root->id);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($validChildIds)) {
            return back()
                ->withErrors(['category_ids' => __('اختر قسمًا فرعيًا واحدًا على الأقل مرتبطًا بنفس القسم الرئيسي.')])
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
                ->withErrors(['platform_service_ids' => __('اختر خدمة مفعلة واحدة على الأقل.')])
                ->withInput();
        }

        $activeServiceIds = $services
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $inactiveSelectedIds = array_values(array_diff($serviceIds, $activeServiceIds));

        if (! empty($inactiveSelectedIds)) {
            return back()
                ->withErrors(['platform_service_ids' => __('يوجد خدمات مختارة غير مفعلة. اختر خدمات مفعلة فقط.')])
                ->withInput();
        }

        $mode = (string) $data['mode'];

        DB::transaction(function () use ($validChildIds, $serviceIds, $services, $mode, $request, $root) {
            foreach ($validChildIds as $childId) {
                $childId = (int) $childId;

                if ($mode === 'replace') {
                    $this->replaceChildServices(
                        rootId: (int) $root->id,
                        childId: $childId,
                        serviceIds: $serviceIds,
                        services: $services,
                        request: $request
                    );

                    continue;
                }

                if ($mode === 'append') {
                    $this->appendChildServices(
                        rootId: (int) $root->id,
                        childId: $childId,
                        serviceIds: $serviceIds,
                        services: $services,
                        request: $request
                    );

                    continue;
                }

                if ($mode === 'remove') {
                    $this->removeChildServices(
                        rootId: (int) $root->id,
                        childId: $childId,
                        serviceIds: $serviceIds
                    );
                }
            }
        });

        return redirect()
            ->route('admin.categories.services-bulk.index', ['root_id' => (int) $root->id])
            ->with('success', __('تم تطبيق الخدمات وإعداداتها ورسومها على الأقسام الفرعية المختارة بنجاح.'));
    }

    private function replaceChildServices(
        int $rootId,
        int $childId,
        array $serviceIds,
        $services,
        Request $request
    ): void {
        /*
        |--------------------------------------------------------------------------
        | IMPORTANT
        |--------------------------------------------------------------------------
        | بعد BIM-2.3.1 أصبحت مفاتيح الربط والـ config والرسوم:
        | category_id + child_id + platform_service_id
        |--------------------------------------------------------------------------
        | لذلك لا نستخدم delete ثم create، بل updateOrCreate بنفس مفاتيح الـ unique.
        |--------------------------------------------------------------------------
        */

        CategoryPlatformService::query()
            ->where('category_id', $rootId)
            ->where('child_id', $childId)
            ->whereNotIn('platform_service_id', $serviceIds)
            ->update([
                'is_active' => 0,
                'updated_at' => now(),
            ]);

        CategoryServiceConfig::query()
            ->where('category_id', $rootId)
            ->where('child_id', $childId)
            ->whereNotIn('platform_service_id', $serviceIds)
            ->update([
                'is_active' => 0,
                'updated_at' => now(),
            ]);

        CategoryChildServiceFee::query()
            ->where('category_id', $rootId)
            ->where('child_id', $childId)
            ->whereNotIn('platform_service_id', $serviceIds)
            ->update([
                'is_active' => 0,
                'business_fee_enabled' => 0,
                'business_fee_type' => null,
                'business_fee_amount' => 0,
                'client_fee_enabled' => 0,
                'client_fee_type' => null,
                'client_fee_amount' => 0,
                'updated_at' => now(),
            ]);

        $sortOrder = 1;

        foreach ($serviceIds as $serviceId) {
            $serviceId = (int) $serviceId;
            $service = $services->get($serviceId);

            if (! $service) {
                continue;
            }

            // Through the one writer: the config is MERGED over what is
            // stored, so this screen stops dropping keys it never heard of —
            // «notes» and the matrix's provenance survived on 20 of 1,055
            // configs because this used to rebuild the whole thing. `meta` is
            // left alone for the same reason; passing null wiped it.
            $this->writer->enable(
                rootId: $rootId,
                childId: $childId,
                serviceId: $serviceId,
                configPatch: $this->serviceConfigPayload($request, $service, $rootId, $childId),
                sortOrder: $sortOrder,
                source: 'services_bulk'
            );

           CategoryChildServiceFee::query()->updateOrCreate(
                    [
                        'category_id' => $rootId,
                        'child_id' => $childId,
                        'platform_service_id' => $serviceId,
                    ],
                    $this->serviceFeePayload($request, $sortOrder, $serviceId)
                );

            $sortOrder++;
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
            $serviceId = (int) $serviceId;
            $service = $services->get($serviceId);

            if (! $service) {
                continue;
            }

            // Appending keeps whatever place the service already had in the
            // order; only a service arriving for the first time takes the next.
            $existingSort = (int) CategoryPlatformService::query()
                ->where('category_id', $rootId)
                ->where('child_id', $childId)
                ->where('platform_service_id', $serviceId)
                ->value('sort_order');

            $sortOrder = $existingSort ?: $nextSort;

            if (! $existingSort) {
                $nextSort++;
            }

            $this->writer->enable(
                rootId: $rootId,
                childId: $childId,
                serviceId: $serviceId,
                configPatch: $this->serviceConfigPayload($request, $service, $rootId, $childId),
                sortOrder: $sortOrder,
                source: 'services_bulk'
            );

            CategoryChildServiceFee::query()->updateOrCreate(
                [
                    'category_id' => $rootId,
                    'child_id' => $childId,
                    'platform_service_id' => $serviceId,
                ],
                $this->serviceFeePayload($request, $sortOrder, $serviceId)
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
            ->where('category_id', $rootId)
            ->where('child_id', $childId)
            ->whereIn('platform_service_id', $serviceIds)
            ->update([
                'is_active' => 0,
                'business_fee_enabled' => 0,
                'business_fee_type' => null,
                'business_fee_amount' => 0,
                'client_fee_enabled' => 0,
                'client_fee_type' => null,
                'client_fee_amount' => 0,
                'updated_at' => now(),
            ]);
    }
}