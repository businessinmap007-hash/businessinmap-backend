<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use App\Models\CategoryPlatformService;
use App\Models\CategoryServiceConfig;
use App\Models\PlatformService;
use App\Models\PlatformServiceItemType;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookableItemController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $serviceId = (int) $request->get('service_id', 0);
        $businessId = (int) $request->get('business_id', 0);
        $isActive = $request->get('is_active', '');
        $itemType = trim((string) $request->get('item_type', ''));

        $services = $this->services();
        $businesses = $this->businesses();

        $rows = BookableItem::query()
            ->with(['service:id,key,name_ar,name_en', 'business:id,name,type,category_id,category_child_id'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('title', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%")
                        ->orWhere('item_type', 'like', "%{$q}%");
                });
            })
            ->when($serviceId > 0, fn ($query) => $query->where('service_id', $serviceId))
            ->when($businessId > 0, fn ($query) => $query->where('business_id', $businessId))
            ->when($itemType !== '', fn ($query) => $query->where('item_type', $itemType))
            ->when($isActive !== '' && $isActive !== null, fn ($query) => $query->where('is_active', (int) $isActive))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin-v2.bookable-items.index', compact('rows', 'services', 'businesses', 'q', 'serviceId', 'businessId', 'isActive', 'itemType'));
    }

    public function create(Request $request)
    {
        $row = new BookableItem([
            'quantity' => 1,
            'is_active' => 1,
            'business_id' => (int) $request->get('business_id', 0) ?: null,
            'service_id' => (int) $request->get('service_id', 0) ?: null,
        ]);

        $services = $this->services();
        $allowedItemTypes = $row->business_id && $row->service_id
            ? $this->allowedItemTypesFor((int) $row->business_id, (int) $row->service_id)
            : [];

        // Prefer old('business_id') so a re-selected business survives a
        // validation failure redirect (bulk items table), falling back to
        // the query-string default used when landing on this page fresh.
        $selectedBusinessId = (int) old('business_id', $row->business_id ?? 0);

        return view('admin-v2.bookable-items.create', [
            'row' => $row,
            'services' => $services,
            'selectedBusiness' => $selectedBusinessId ? $this->businessOption($selectedBusinessId) : null,
            'allowedItemTypes' => $allowedItemTypes,
            'itemTypeLabels' => $this->itemTypeLabelsFor($allowedItemTypes),
        ]);
    }

    /**
     * Item types allowed for one business+service pair, fetched on demand.
     *
     * create()/edit() used to precompute this for every business x service
     * combination (itemTypesByBusinessServiceForForm) - with ~1750 businesses
     * x 5 services that was ~8,750 iterations each running multiple queries,
     * making the page take extremely long to load. This replaces it with a
     * single lookup for the pair actually selected in the form.
     */
    public function itemTypesLookup(Request $request): JsonResponse
    {
        $businessId = (int) $request->get('business_id', 0);
        $serviceId = (int) $request->get('service_id', 0);

        $types = $this->allowedItemTypesFor($businessId, $serviceId);
        $labels = $this->itemTypeLabelsFor($types);

        return response()->json([
            'ok' => true,
            'items' => collect($types)
                ->map(fn (string $key) => ['key' => $key, 'label' => $labels[$key] ?? $key])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Search-as-you-type business lookup for the form's business select.
     *
     * The form used to embed every business (~1,750) as static <option>
     * tags on every page load. Now only the currently selected business
     * (if any) is preloaded server-side; everything else is searched here.
     */
    public function businessLookup(Request $request): JsonResponse
    {
        $term = trim((string) $request->get('q', ''));

        $businesses = User::query()
            ->select(['id', 'name'])
            ->where('type', 'business')
            ->when($term !== '', fn ($query) => $query->where('name', 'like', "%{$term}%"))
            ->orderBy('name')
            ->limit(30)
            ->get();

        return response()->json([
            'ok' => true,
            'businesses' => $businesses,
        ]);
    }

    protected function businessOption(int $businessId): ?User
    {
        return User::query()
            ->select(['id', 'name'])
            ->where('type', 'business')
            ->where('id', $businessId)
            ->first();
    }

    public function store(Request $request)
    {
        if ($request->has('items')) {
            $items = $this->validateBulkItems($request);

            $created = DB::transaction(function () use ($items) {
                return collect($items)->map(fn (array $data) => BookableItem::create($data));
            });

            return redirect()
                ->route('admin.bookable-items.index', [
                    'business_id' => (int) $request->input('business_id'),
                    'service_id' => (int) $request->input('service_id'),
                ])
                ->with('success', 'تم إنشاء ' . $created->count() . ' عنصر قابل للحجز بنجاح.');
        }

        $data = $this->validateData($request);
        $row = BookableItem::create($data);

        return redirect()->route('admin.bookable-items.edit', $row)->with('success', 'تم إنشاء العنصر القابل للحجز بنجاح.');
    }

    public function edit(BookableItem $bookableItem)
    {
        $row = $bookableItem->load(['service:id,key,name_ar,name_en,supports_deposit', 'business:id,name,type,category_id,category_child_id']);
        $services = $this->services();
        $allowedItemTypes = $this->allowedItemTypesFor((int) $row->business_id, (int) $row->service_id);
        $selectedBusinessId = (int) old('business_id', $row->business_id ?? 0);

        return view('admin-v2.bookable-items.edit', [
            'row' => $row,
            'services' => $services,
            'selectedBusiness' => $selectedBusinessId ? $this->businessOption($selectedBusinessId) : null,
            'allowedItemTypes' => $allowedItemTypes,
            'itemTypeLabels' => $this->itemTypeLabelsFor($allowedItemTypes),
        ]);
    }

    public function update(Request $request, BookableItem $bookableItem)
    {
        $data = $this->validateData($request, $bookableItem->id);
        $bookableItem->update($data);

        return back()->with('success', 'تم تحديث العنصر القابل للحجز بنجاح.');
    }

    public function destroy(BookableItem $bookableItem)
    {
        $bookableItem->delete();

        return redirect()->route('admin.bookable-items.index')->with('success', 'تم حذف العنصر بنجاح.');
    }

    protected function validateBulkItems(Request $request): array
    {
        $request->validate([
            'business_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('type', 'business'))],
            'service_id' => ['required', 'integer', 'exists:platform_services,id'],
            'meta' => ['nullable', 'string'],
            'items' => ['required', 'array'],
        ]);

        $businessId = (int) $request->input('business_id');
        $serviceId = (int) $request->input('service_id');
        $meta = $this->parseMetaJson($request->input('meta'));

        [$business, $categoryId, $childId] = $this->resolveBusinessContext($businessId);
        if (! $business) throw ValidationException::withMessages(['business_id' => 'البزنس غير موجود أو ليس من نوع business.']);
        if (! $categoryId && ! $childId) throw ValidationException::withMessages(['business_id' => 'هذا البزنس غير مرتبط بأي category أو category child حتى الآن.']);

        $service = PlatformService::query()->select(['id', 'supports_deposit'])->find($serviceId);
        if (! $service) throw ValidationException::withMessages(['service_id' => 'الخدمة غير موجودة.']);

        $this->ensureServiceEnabledForBusiness($businessId, $serviceId);
        $allowedItemTypes = $this->allowedItemTypesFor($businessId, $serviceId);
        if ($allowedItemTypes === []) throw ValidationException::withMessages(['items' => 'لا توجد أنواع عناصر مفعلة لهذه الخدمة. أضفها أولًا من Platform Service Item Types ثم اضبطها من Service Catalog Matrix.']);

        $items = [];

        foreach ((array) $request->input('items', []) as $index => $raw) {
            $type = trim((string) ($raw['item_type'] ?? ''));
            $code = trim((string) ($raw['code'] ?? ''));

            if ($type === '' && $code === '') {
                continue;
            }

            if ($type === '' || ! in_array($type, $allowedItemTypes, true)) {
                throw ValidationException::withMessages(["items.{$index}.item_type" => 'نوع العنصر غير مسموح لهذه الخدمة أو غير مفعل لهذا القسم الفرعي.']);
            }

            if ($code === '') {
                throw ValidationException::withMessages(["items.{$index}.code" => 'الكود أو رقم الغرفة مطلوب لكل عنصر يتم إنشاؤه.']);
            }

            $title = $this->displayTitleFromCode($type, $code);

            $duplicate = BookableItem::query()
                ->where('business_id', $businessId)
                ->where('service_id', $serviceId)
                ->where('item_type', $type)
                ->where('code', $code)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages(["items.{$index}.code" => 'يوجد عنصر آخر بنفس الكود أو رقم الغرفة: ' . $code]);
            }

            $items[] = [
                'business_id' => $businessId,
                'service_id' => $serviceId,
                'item_type' => $type,
                'title' => $title,
                'code' => $code,
                // Units are inventory only: price/deposit live in
                // business_service_prices per type. See services-blueprint.md.
                'capacity' => ! empty($raw['capacity']) ? (int) $raw['capacity'] : null,
                'quantity' => max((int) ($raw['quantity'] ?? 1), 1),
                'is_active' => ! empty($raw['is_active']) ? 1 : 0,
                'meta' => $meta,
            ];
        }

        if ($items === []) throw ValidationException::withMessages(['items' => 'أدخل عنصرًا واحدًا على الأقل.']);

        return $items;
    }

    protected function validateData(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'business_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('type', 'business'))],
            'service_id' => ['required', 'integer', 'exists:platform_services,id'],
            'item_type' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable'],
            'meta' => ['nullable', 'string'],
        ], [], [
            'business_id' => 'البزنس', 'service_id' => 'الخدمة', 'item_type' => 'نوع العنصر', 'code' => 'الكود أو رقم الغرفة', 'capacity' => 'السعة', 'quantity' => 'الكمية', 'meta' => 'البيانات الإضافية',
        ]);

        $data['item_type'] = trim((string) ($data['item_type'] ?? ''));
        $data['code'] = trim((string) ($data['code'] ?? ''));
        $data['title'] = $this->displayTitleFromCode($data['item_type'], $data['code']);
        $data['is_active'] = (int) $request->boolean('is_active');
        $data['quantity'] = (int) ($data['quantity'] ?? 1);

        [$business, $categoryId, $childId] = $this->resolveBusinessContext((int) $data['business_id']);
        if (! $business) throw ValidationException::withMessages(['business_id' => 'البزنس غير موجود أو ليس من نوع business.']);
        if (! $categoryId && ! $childId) throw ValidationException::withMessages(['business_id' => 'هذا البزنس غير مرتبط بأي category أو category child حتى الآن.']);

        $service = PlatformService::query()->select(['id', 'supports_deposit'])->find($data['service_id']);
        if (! $service) throw ValidationException::withMessages(['service_id' => 'الخدمة غير موجودة.']);

        $this->ensureServiceEnabledForBusiness((int) $business->id, (int) $service->id);

        $allowedItemTypes = $this->allowedItemTypesFor((int) $business->id, (int) $service->id);
        if ($allowedItemTypes === []) throw ValidationException::withMessages(['item_type' => 'لا توجد أنواع عناصر مفعلة لهذه الخدمة. أضفها أولًا من Platform Service Item Types ثم اضبطها من Service Catalog Matrix.']);
        if (! in_array($data['item_type'], $allowedItemTypes, true)) throw ValidationException::withMessages(['item_type' => 'نوع العنصر غير مسموح لهذه الخدمة أو غير مفعل لهذا القسم الفرعي.']);

        $duplicateQuery = BookableItem::query()
            ->where('business_id', $data['business_id'])
            ->where('service_id', $data['service_id'])
            ->where('item_type', $data['item_type'])
            ->where('code', $data['code']);

        if ($ignoreId) $duplicateQuery->where('id', '!=', $ignoreId);
        if ($duplicateQuery->exists()) throw ValidationException::withMessages(['code' => 'يوجد عنصر آخر بنفس البزنس والخدمة ونوع العنصر والكود.']);

        // Units are inventory only: price/deposit are authored in
        // business_service_prices per type, not on the unit. See blueprint.
        $data['meta'] = $this->parseMetaJson($request->input('meta'));

        return $data;
    }

    protected function displayTitleFromCode(string $type, string $code): string
    {
        $type = trim($type);
        $code = trim($code);

        return trim(($type !== '' ? $type . ' ' : '') . $code);
    }

    protected function ensureServiceEnabledForBusiness(int $businessId, int $serviceId): void
    {
        [$business, $categoryId, $childId] = $this->resolveBusinessContext($businessId);

        $enabled = false;
        if ($childId > 0) $enabled = CategoryPlatformService::query()->forChild($childId)->forService($serviceId)->active()->exists();
        if (! $enabled && $categoryId > 0) $enabled = CategoryPlatformService::query()->forCategory($categoryId)->forService($serviceId)->active()->exists();

        if (! $enabled) throw ValidationException::withMessages(['service_id' => 'هذه الخدمة غير مفعلة أو غير مسموحة لهذا البزنس.']);
    }

    protected function parseMetaJson(?string $value): ?array
    {
        $value = trim((string) $value);
        if ($value === '') return null;

        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) throw ValidationException::withMessages(['meta' => 'حقل Meta يجب أن يكون JSON صحيحًا.']);
        if (! is_array($decoded)) throw ValidationException::withMessages(['meta' => 'حقل Meta يجب أن يكون JSON object أو array.']);

        return $decoded;
    }

    protected function services()
    {
        return PlatformService::query()->select(['id', 'key', 'name_ar', 'name_en', 'supports_deposit'])->where('is_active', 1)->orderBy('name_ar')->orderBy('id')->get();
    }

    protected function businesses()
    {
        return User::query()->select(['id', 'name', 'category_id', 'category_child_id'])->where('type', 'business')->orderBy('name')->orderBy('id')->get();
    }

    protected function allActiveItemTypes(): array
    {
        return PlatformServiceItemType::query()
            ->where('is_active', 1)
            ->orderByRaw('COALESCE(sort_order, 999999) ASC')
            ->orderBy('id')
            ->pluck('key')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function allowedItemTypesFor(int $businessId, int $serviceId): array
    {
        if (! $businessId || ! $serviceId) return [];

        [$business, $categoryId, $childId] = $this->resolveBusinessContext($businessId);
        if (! $business) return [];

        $service = PlatformService::query()->find($serviceId, ['id', 'key']);
        if (! $service) return [];

        $baseTypes = PlatformServiceItemType::query()
            ->where('platform_service_id', (int) $service->id)
            ->where('is_active', 1)
            ->orderByRaw('COALESCE(sort_order, 999999) ASC')
            ->orderBy('id')
            ->get(['key'])
            ->pluck('key')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($baseTypes === []) return [];

        $serviceConfig = null;
        if ($childId > 0) $serviceConfig = CategoryServiceConfig::query()->forChild($childId)->forService((int) $service->id)->active()->first();
        if (! $serviceConfig && $categoryId > 0) $serviceConfig = CategoryServiceConfig::query()->forCategory($categoryId)->forService((int) $service->id)->active()->first();
        if (! $serviceConfig) return $baseTypes;

        $config = is_array($serviceConfig->config) ? $serviceConfig->config : [];
        $restrictedTypes = $config['allowed_item_types'] ?? [];
        if (! is_array($restrictedTypes) || $restrictedTypes === []) return $baseTypes;

        $restrictedTypes = collect($restrictedTypes)->map(fn ($value) => trim((string) $value))->filter()->unique()->values()->all();

        return collect($baseTypes)->filter(fn ($type) => in_array($type, $restrictedTypes, true))->values()->all();
    }

    protected function itemTypeLabelsFor(array $keys): array
    {
        if ($keys === []) return [];

        return PlatformServiceItemType::query()
            ->whereIn('key', $keys)
            ->where('is_active', 1)
            ->ordered()
            ->get(['key', 'name_ar', 'name_en'])
            ->mapWithKeys(fn (PlatformServiceItemType $row) => [
                (string) $row->key => $row->displayName('ar'),
            ])
            ->all();
    }

    protected function resolveBusinessContext(int $businessId): array
    {
        if (! $businessId) return [null, 0, 0];

        $business = User::query()->where('id', $businessId)->where('type', 'business')->first();
        if (! $business) return [null, 0, 0];

        $categoryId = (int) ($business->getAttribute('category_id') ?? 0);
        $childId = (int) ($business->getAttribute('category_child_id') ?? 0);

        return [$business, $categoryId, $childId];
    }
}
