<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\BusinessServicePrice;
use App\Models\CategoryChild;
use App\Models\CategoryPlatformService;
use App\Models\CategoryServiceConfig;
use App\Models\PlatformService;
use App\Models\PlatformServiceItemType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BusinessServicePriceController extends Controller
{
    public function index(Request $request)
    {
        $serviceId  = (int) $request->get('service_id', 0);
        $businessId = (int) $request->get('business_id', 0);
        $childId    = (int) $request->get('child_id', 0);
        $isActive   = $request->get('is_active', '');
        $qBusiness = trim((string) $request->get('q_business', ''));
        $qService  = trim((string) $request->get('q_service', ''));
        $qChild    = trim((string) $request->get('q_child', ''));
        $qItemType = trim((string) $request->get('q_item_type', ''));

        $services = $this->servicesForForm();
        $businesses = User::query()->select(['id', 'name', 'category_child_id'])->where('type', 'business')->orderBy('name')->orderBy('id')->get();
        $children = CategoryChild::query()->select(['id', 'name_ar', 'name_en', 'reorder'])->orderByRaw('COALESCE(reorder, 999999) ASC')->orderBy('id')->get();

        $baseQuery = BusinessServicePrice::query()
            ->selectRaw("business_service_prices.*, CASE WHEN discount_enabled = 1 THEN ROUND(price * discount_percent / 100, 2) ELSE 0 END as discount_amount, CASE WHEN discount_enabled = 1 THEN ROUND(price - (price * discount_percent / 100), 2) ELSE ROUND(price, 2) END as final_service_price, CASE WHEN deposit_enabled = 1 THEN ROUND((CASE WHEN discount_enabled = 1 THEN price - (price * discount_percent / 100) ELSE price END) * deposit_percent / 100, 2) ELSE 0 END as deposit_hold_amount, ROUND(CASE WHEN discount_enabled = 1 THEN price - (price * discount_percent / 100) ELSE price END, 2) as cash_due_on_execution")
            ->with(['service:id,key,name_ar,name_en,supports_deposit', 'business:id,name,type,category_child_id', 'child:id,name_ar,name_en,reorder'])
            ->when($serviceId > 0, fn ($query) => $query->where('service_id', $serviceId))
            ->when($businessId > 0, fn ($query) => $query->where('business_id', $businessId))
            ->when($childId > 0, fn ($query) => $query->where('child_id', $childId))
            ->when($isActive !== '' && $isActive !== null, fn ($query) => $query->where('is_active', (int) $isActive))
            ->when($qItemType !== '', fn ($query) => $query->whereRaw('LOWER(bookable_item_type) LIKE ?', ['%' . mb_strtolower($qItemType) . '%']))
            ->when($qBusiness !== '', fn ($query) => $query->whereHas('business', fn ($sub) => $sub->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($qBusiness) . '%'])))
            ->when($qService !== '', function ($query) use ($qService) {
                $term = '%' . mb_strtolower($qService) . '%';
                $query->whereHas('service', fn ($sub) => $sub->whereRaw('LOWER(name_ar) LIKE ?', [$term])->orWhereRaw('LOWER(name_en) LIKE ?', [$term])->orWhereRaw('LOWER(`key`) LIKE ?', [$term]));
            })
            ->when($qChild !== '', function ($query) use ($qChild) {
                $term = '%' . mb_strtolower($qChild) . '%';
                $query->whereHas('child', fn ($sub) => $sub->whereRaw('LOWER(name_ar) LIKE ?', [$term])->orWhereRaw('LOWER(name_en) LIKE ?', [$term]));
            });

        $stats = [
            'total_rows' => BusinessServicePrice::count(),
            'active_rows' => BusinessServicePrice::where('is_active', 1)->count(),
            'deposit_rows' => BusinessServicePrice::where('deposit_enabled', 1)->count(),
            'avg_price' => BusinessServicePrice::avg('price'),
            'business_count' => BusinessServicePrice::distinct('business_id')->count(),
            'children_count' => BusinessServicePrice::query()->whereNotNull('child_id')->distinct('child_id')->count('child_id'),
            'services_count' => BusinessServicePrice::distinct('service_id')->count(),
            'item_types_count' => BusinessServicePrice::query()->whereNotNull('bookable_item_type')->where('bookable_item_type', '!=', '')->distinct('bookable_item_type')->count('bookable_item_type'),
            'max_price' => BusinessServicePrice::max('price'),
            'min_price' => BusinessServicePrice::min('price'),
        ];

        $rows = $baseQuery->orderByDesc('id')->paginate(50)->withQueryString();

        return view('admin-v2.business-service-prices.index', compact('rows', 'services', 'businesses', 'children', 'serviceId', 'businessId', 'childId', 'isActive', 'qBusiness', 'qService', 'qChild', 'qItemType', 'stats'));
    }

    public function create()
    {
        $services = $this->servicesForForm();
        $businesses = User::query()->select(['id', 'name', 'category_child_id'])->where('type', 'business')->orderBy('name')->orderBy('id')->get();
        $children = CategoryChild::query()->select(['id', 'name_ar', 'name_en', 'reorder'])->orderByRaw('COALESCE(reorder, 999999) ASC')->orderBy('id')->get();
        $row = new BusinessServicePrice(['is_active' => 1, 'price' => 0, 'currency' => 'EGP', 'deposit_enabled' => 0, 'deposit_percent' => 0, 'discount_enabled' => 0, 'discount_percent' => 0]);

        return view('admin-v2.business-service-prices.create', [
            'row' => $row,
            'services' => $services,
            'businesses' => $businesses,
            'children' => $children,
            'itemTypesByService' => $this->itemTypesByServiceForForm(),
            'itemTypesByChildService' => $this->itemTypesByChildServiceForForm($children, $services),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $lookup = ['business_id' => (int) $data['business_id'], 'child_id' => (int) $data['child_id'], 'service_id' => (int) $data['service_id'], 'bookable_item_type' => trim((string) $data['bookable_item_type'])];
        $data['bookable_item_type'] = $lookup['bookable_item_type'];
        $query = method_exists(BusinessServicePrice::class, 'withTrashed') ? BusinessServicePrice::withTrashed() : BusinessServicePrice::query();
        $row = $query->where($lookup)->first();

        if ($row) {
            if (method_exists($row, 'trashed') && $row->trashed()) $row->restore();
            $row->fill($data)->save();
        } else {
            $row = BusinessServicePrice::create(array_merge($lookup, $data));
        }

        return redirect()->route('admin.business_service_prices.edit', $row)->with('success', 'تم حفظ سعر الخدمة وإعدادات الديبوزت والخصم بنجاح.');
    }

    public function edit(BusinessServicePrice $row)
    {
        $services = $this->servicesForForm();
        $businesses = User::query()->select(['id', 'name', 'category_child_id'])->where('type', 'business')->orderBy('name')->orderBy('id')->get();
        $children = CategoryChild::query()->select(['id', 'name_ar', 'name_en', 'reorder'])->orderByRaw('COALESCE(reorder, 999999) ASC')->orderBy('id')->get();
        $row->load(['service:id,key,name_ar,name_en,supports_deposit', 'business:id,name,type,category_child_id', 'child:id,name_ar,name_en,reorder']);

        return view('admin-v2.business-service-prices.edit', [
            'row' => $row,
            'services' => $services,
            'businesses' => $businesses,
            'children' => $children,
            'itemTypesByService' => $this->itemTypesByServiceForForm(),
            'itemTypesByChildService' => $this->itemTypesByChildServiceForForm($children, $services),
        ]);
    }

    public function update(Request $request, BusinessServicePrice $row)
    {
        $data = $this->validateData($request, $row->id);
        $duplicate = BusinessServicePrice::query()->where('business_id', $data['business_id'])->where('child_id', $data['child_id'])->where('service_id', $data['service_id'])->where('bookable_item_type', $data['bookable_item_type'])->where('id', '!=', $row->id)->exists();
        if ($duplicate) throw ValidationException::withMessages(['bookable_item_type' => 'يوجد سجل آخر لنفس البزنس والقسم الفرعي والخدمة ونوع العنصر.']);
        $row->update($data);

        return back()->with('success', 'تم تحديث السجل بنجاح.');
    }

    public function destroy(BusinessServicePrice $row)
    {
        $row->delete();
        return redirect()->route('admin.business_service_prices.index')->with('success', 'تم حذف السجل بنجاح.');
    }

    protected function validateData(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'business_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('type', 'business'))],
            'child_id' => ['nullable', 'integer', 'exists:category_children_master,id'],
            'service_id' => ['required', 'integer', 'exists:platform_services,id'],
            'bookable_item_type' => ['required', 'string', 'max:100', Rule::unique('business_service_prices', 'bookable_item_type')->where(fn ($query) => $query->where('business_id', $request->input('business_id'))->where('child_id', $request->input('child_id'))->where('service_id', $request->input('service_id')))->ignore($ignoreId)],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'is_active' => ['nullable'],
            'deposit_enabled' => ['nullable'],
            'deposit_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'discount_enabled' => ['nullable'],
            'discount_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ], [], ['business_id' => 'البزنس', 'child_id' => 'القسم الفرعي', 'service_id' => 'الخدمة', 'bookable_item_type' => 'نوع العنصر', 'price' => 'السعر']);

        $data['bookable_item_type'] = trim((string) ($data['bookable_item_type'] ?? ''));
        $data['currency'] = strtoupper(trim((string) ($data['currency'] ?? 'EGP'))) ?: 'EGP';
        $data['price'] = round((float) $data['price'], 2);
        $data['is_active'] = (int) $request->boolean('is_active');
        $data['deposit_enabled'] = (int) $request->boolean('deposit_enabled');
        $data['discount_enabled'] = (int) $request->boolean('discount_enabled');
        $data['deposit_percent'] = (int) ($data['deposit_percent'] ?? 0);
        $data['discount_percent'] = (int) ($data['discount_percent'] ?? 0);

        $business = User::query()->select(['id', 'type', 'category_id', 'category_child_id', 'name'])->where('id', $data['business_id'])->where('type', 'business')->first();
        if (! $business) throw ValidationException::withMessages(['business_id' => 'البزنس غير موجود أو ليس من نوع business.']);

        $businessChildId = (int) ($business->category_child_id ?? 0);
        $submittedChildId = (int) ($data['child_id'] ?? 0);
        if (! $submittedChildId && $businessChildId > 0) {
            $data['child_id'] = $businessChildId;
            $submittedChildId = $businessChildId;
        }
        if (! $submittedChildId) throw ValidationException::withMessages(['child_id' => 'هذا البزنس غير مرتبط بأي category child حتى الآن.']);
        if ($businessChildId > 0 && $submittedChildId !== $businessChildId) throw ValidationException::withMessages(['child_id' => 'القسم الفرعي لا يطابق القسم الفرعي المرتبط بهذا البزنس.']);

        $service = PlatformService::query()->select(['id', 'is_active', 'supports_deposit'])->where('id', $data['service_id'])->first();
        if (! $service) throw ValidationException::withMessages(['service_id' => 'الخدمة غير موجودة.']);
        if (! (bool) $service->is_active) throw ValidationException::withMessages(['service_id' => 'الخدمة غير مفعلة.']);

        $serviceAllowedForChild = CategoryPlatformService::query()->where('child_id', $submittedChildId)->where('platform_service_id', (int) $data['service_id'])->where('is_active', 1)->exists();
        if (! $serviceAllowedForChild) throw ValidationException::withMessages(['service_id' => 'هذه الخدمة غير متاحة لهذا القسم الفرعي. يجب ربط الخدمة بالقسم الفرعي أولًا.']);

        $allowedTypes = $this->allowedItemTypesForChildService($submittedChildId, (int) $data['service_id']);
        if (! in_array((string) $data['bookable_item_type'], $allowedTypes, true)) {
            throw ValidationException::withMessages(['bookable_item_type' => 'نوع العنصر غير متاح لهذا القسم الفرعي داخل هذه الخدمة. اضبطه من Service Catalog Matrix أولًا.']);
        }

        if (! (bool) $service->supports_deposit) {
            $data['deposit_enabled'] = 0;
            $data['deposit_percent'] = 0;
        } elseif (! $data['deposit_enabled']) {
            $data['deposit_percent'] = 0;
        }
        if (! $data['discount_enabled']) $data['discount_percent'] = 0;

        return $data;
    }

    protected function allowedItemTypesForChildService(int $childId, int $serviceId): array
    {
        $serviceAllowedForChild = CategoryPlatformService::query()
            ->where('child_id', $childId)
            ->where('platform_service_id', $serviceId)
            ->where('is_active', 1)
            ->exists();

        if (! $serviceAllowedForChild) return [];

        $baseTypes = PlatformServiceItemType::query()
            ->where('platform_service_id', $serviceId)
            ->where('is_active', 1)
            ->ordered()
            ->pluck('key')
            ->map(fn ($key) => trim((string) $key))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($baseTypes === []) return [];

        $configuredTypes = CategoryServiceConfig::query()
            ->where('child_id', $childId)
            ->where('platform_service_id', $serviceId)
            ->where('is_active', 1)
            ->get()
            ->flatMap(function (CategoryServiceConfig $config) {
                $data = is_array($config->config) ? $config->config : [];
                return $data['allowed_item_types'] ?? [];
            })
            ->map(fn ($type) => trim((string) $type))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($configuredTypes)) return $baseTypes;

        return collect($baseTypes)
            ->filter(fn ($type) => in_array($type, $configuredTypes, true))
            ->values()
            ->all();
    }

    protected function servicesForForm()
    {
        return PlatformService::query()->select(['id', 'key', 'name_ar', 'name_en', 'supports_deposit'])->where('is_active', 1)->orderBy('name_ar')->orderBy('id')->get();
    }

    protected function itemTypesByServiceForForm(): array
    {
        $rows = PlatformServiceItemType::query()->select(['id', 'platform_service_id', 'key', 'name_ar', 'name_en', 'is_default', 'is_active', 'sort_order'])->where('is_active', 1)->ordered()->get();
        $grouped = [];
        foreach ($rows as $row) {
            $serviceId = (int) $row->platform_service_id;
            $grouped[$serviceId] ??= [];
            $grouped[$serviceId][] = ['id' => (int) $row->id, 'key' => (string) $row->key, 'name_ar' => (string) ($row->name_ar ?? ''), 'name_en' => (string) ($row->name_en ?? ''), 'label' => $this->itemTypeLabel($row), 'is_default' => (bool) $row->is_default, 'sort_order' => (int) $row->sort_order];
        }
        return $grouped;
    }

    protected function itemTypesByChildServiceForForm($children, $services): array
    {
        $labelsByKey = PlatformServiceItemType::query()
            ->where('is_active', 1)
            ->ordered()
            ->get(['key', 'name_ar', 'name_en'])
            ->mapWithKeys(fn (PlatformServiceItemType $row) => [(string) $row->key => $this->itemTypeLabel($row)])
            ->all();

        $matrix = [];

        foreach ($children as $child) {
            $childId = (int) $child->id;

            foreach ($services as $service) {
                $serviceId = (int) $service->id;
                $types = $this->allowedItemTypesForChildService($childId, $serviceId);

                $matrix[$childId][$serviceId] = collect($types)
                    ->map(fn (string $key) => [
                        'key' => $key,
                        'label' => $labelsByKey[$key] ?? $key,
                    ])
                    ->values()
                    ->all();
            }
        }

        return $matrix;
    }

    protected function itemTypeLabel(PlatformServiceItemType $row): string
    {
        $ar = trim((string) ($row->name_ar ?? ''));
        $en = trim((string) ($row->name_en ?? ''));
        return $ar !== '' ? $ar : ($en !== '' ? $en : (string) $row->key);
    }
}
