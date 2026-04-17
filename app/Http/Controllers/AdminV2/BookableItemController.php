<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use App\Models\CategoryPlatformService;
use App\Models\CategoryServiceConfig;
use App\Models\PlatformService;
use App\Models\User;
use Illuminate\Http\Request;
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
            ->with([
                'service:id,key,name_ar,name_en',
                'business:id,name,type,category_id,category_child_id',
            ])
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

        return view('admin-v2.bookable-items.index', compact(
            'rows',
            'services',
            'businesses',
            'q',
            'serviceId',
            'businessId',
            'isActive',
            'itemType'
        ));
    }

    public function create(Request $request)
    {
        $row = new BookableItem([
            'price' => 0,
            'quantity' => 1,
            'is_active' => 1,
            'deposit_enabled' => 0,
            'deposit_percent' => 0,
            'business_id' => (int) $request->get('business_id', 0) ?: null,
            'service_id' => (int) $request->get('service_id', 0) ?: null,
        ]);

        $allowedItemTypes = [];
        if ($row->business_id && $row->service_id) {
            $allowedItemTypes = $this->allowedItemTypesFor(
                (int) $row->business_id,
                (int) $row->service_id
            );
        }

        return view('admin-v2.bookable-items.create', [
            'row' => $row,
            'services' => $this->services(),
            'businesses' => $this->businesses(),
            'allowedItemTypes' => $allowedItemTypes,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $row = BookableItem::create($data);

        return redirect()
            ->route('admin.bookable-items.edit', $row)
            ->with('success', 'تم إنشاء العنصر القابل للحجز بنجاح.');
    }

    public function edit(BookableItem $bookableItem)
    {
        $row = $bookableItem->load([
            'service:id,key,name_ar,name_en,supports_deposit,max_deposit_percent',
            'business:id,name,type,category_id,category_child_id',
        ]);

        return view('admin-v2.bookable-items.edit', [
            'row' => $row,
            'services' => $this->services(),
            'businesses' => $this->businesses(),
            'allowedItemTypes' => $this->allowedItemTypesFor(
                (int) $row->business_id,
                (int) $row->service_id
            ),
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

        return redirect()
            ->route('admin.bookable-items.index')
            ->with('success', 'تم حذف العنصر بنجاح.');
    }

    protected function validateData(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'business_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) {
                    return $query->where('type', 'business');
                }),
            ],
            'service_id' => ['required', 'integer', 'exists:platform_services,id'],
            'item_type' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:191'],
            'code' => ['nullable', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable'],
            'deposit_enabled' => ['nullable'],
            'deposit_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'meta' => ['nullable', 'string'],
        ], [], [
            'business_id' => 'البزنس',
            'service_id' => 'الخدمة',
            'item_type' => 'نوع العنصر',
            'title' => 'العنوان',
            'code' => 'الكود',
            'price' => 'السعر',
            'capacity' => 'السعة',
            'quantity' => 'الكمية',
            'deposit_enabled' => 'تفعيل الديبوزت',
            'deposit_percent' => 'نسبة الديبوزت',
            'meta' => 'البيانات الإضافية',
        ]);

        $data['item_type'] = trim((string) ($data['item_type'] ?? ''));
        $data['title'] = trim((string) ($data['title'] ?? ''));
        $data['code'] = trim((string) ($data['code'] ?? ''));
        $data['is_active'] = (int) $request->boolean('is_active');
        $data['deposit_enabled'] = (int) $request->boolean('deposit_enabled');
        $data['quantity'] = (int) ($data['quantity'] ?? 1);
        $data['deposit_percent'] = (int) ($data['deposit_percent'] ?? 0);

        [$business, $categoryId, $childId] = $this->resolveBusinessContext((int) $data['business_id']);

        if (! $business) {
            throw ValidationException::withMessages([
                'business_id' => 'البزنس غير موجود أو ليس من نوع business.',
            ]);
        }

        if (! $categoryId && ! $childId) {
            throw ValidationException::withMessages([
                'business_id' => 'هذا البزنس غير مرتبط بأي category أو category child حتى الآن.',
            ]);
        }

        $service = PlatformService::query()
            ->select(['id', 'supports_deposit', 'max_deposit_percent'])
            ->find($data['service_id']);

        if (! $service) {
            throw ValidationException::withMessages([
                'service_id' => 'الخدمة غير موجودة.',
            ]);
        }

        $serviceEnabledForBusiness = false;

        if ($childId > 0) {
            $serviceEnabledForBusiness = CategoryPlatformService::query()
                ->forChild($childId)
                ->forService((int) $service->id)
                ->active()
                ->exists();
        }

        if (! $serviceEnabledForBusiness && $categoryId > 0) {
            $serviceEnabledForBusiness = CategoryPlatformService::query()
                ->forCategory($categoryId)
                ->forService((int) $service->id)
                ->active()
                ->exists();
        }

        if (! $serviceEnabledForBusiness) {
            throw ValidationException::withMessages([
                'service_id' => 'هذه الخدمة غير مفعلة أو غير مسموحة لهذا البزنس.',
            ]);
        }

        $allowedItemTypes = $this->allowedItemTypesFor((int) $business->id, (int) $service->id);

        if (! empty($allowedItemTypes) && ! in_array($data['item_type'], $allowedItemTypes, true)) {
            throw ValidationException::withMessages([
                'item_type' => 'نوع العنصر غير مسموح لهذا التصنيف مع هذه الخدمة.',
            ]);
        }

        $duplicateQuery = BookableItem::query()
            ->where('business_id', $data['business_id'])
            ->where('service_id', $data['service_id'])
            ->where('item_type', $data['item_type'])
            ->where('title', $data['title']);

        if ($ignoreId) {
            $duplicateQuery->where('id', '!=', $ignoreId);
        }

        if ($duplicateQuery->exists()) {
            throw ValidationException::withMessages([
                'title' => 'يوجد عنصر آخر بنفس البزنس والخدمة ونوع العنصر والعنوان.',
            ]);
        }

        if (! (bool) $service->supports_deposit) {
            $data['deposit_enabled'] = 0;
            $data['deposit_percent'] = 0;
        } else {
            if (! $data['deposit_enabled']) {
                $data['deposit_percent'] = 0;
            } else {
                $maxAllowed = (int) ($service->max_deposit_percent ?? 0);

                if ($data['deposit_percent'] > $maxAllowed) {
                    throw ValidationException::withMessages([
                        'deposit_percent' => "نسبة الديبوزت تتجاوز الحد المسموح للخدمة ({$maxAllowed}%).",
                    ]);
                }
            }
        }

        $data['meta'] = $this->parseMetaJson($request->input('meta'));

        return $data;
    }

    protected function parseMetaJson(?string $value): ?array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw ValidationException::withMessages([
                'meta' => 'حقل Meta يجب أن يكون JSON صحيحًا.',
            ]);
        }

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'meta' => 'حقل Meta يجب أن يكون JSON object أو array.',
            ]);
        }

        return $decoded;
    }

    protected function services()
    {
        return PlatformService::query()
            ->select(['id', 'key', 'name_ar', 'name_en', 'supports_deposit', 'max_deposit_percent'])
            ->where('is_active', 1)
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get();
    }

    protected function businesses()
    {
        return User::query()
            ->select(['id', 'name', 'category_id', 'category_child_id'])
            ->where('type', 'business')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    protected function allowedItemTypesFor(int $businessId, int $serviceId): array
    {
        if (! $businessId || ! $serviceId) {
            return [];
        }

        [$business, $categoryId, $childId] = $this->resolveBusinessContext($businessId);

        if (! $business) {
            return [];
        }

        $service = PlatformService::query()
            ->find($serviceId, ['id', 'key']);

        if (! $service) {
            return [];
        }

        $serviceConfig = null;

        if ($childId > 0) {
            $serviceConfig = CategoryServiceConfig::query()
                ->forChild($childId)
                ->forService((int) $service->id)
                ->active()
                ->first();
        }

        // fallback مؤقت للبيانات القديمة
        if (! $serviceConfig && $categoryId > 0) {
            $serviceConfig = CategoryServiceConfig::query()
                ->forCategory($categoryId)
                ->forService((int) $service->id)
                ->active()
                ->first();
        }

        if (! $serviceConfig) {
            return [];
        }

        $config = is_array($serviceConfig->config)
            ? $serviceConfig->config
            : [];

        $allowedItemTypes = $config['allowed_item_types'] ?? [];

        if (! is_array($allowedItemTypes)) {
            return [];
        }

        return collect($allowedItemTypes)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function resolveBusinessContext(int $businessId): array
    {
        if (! $businessId) {
            return [null, 0, 0];
        }

        $business = User::query()
            ->where('id', $businessId)
            ->where('type', 'business')
            ->first();

        if (! $business) {
            return [null, 0, 0];
        }

        $categoryId = (int) ($business->getAttribute('category_id') ?? 0);
        $childId = (int) ($business->getAttribute('category_child_id') ?? 0);

        return [$business, $categoryId, $childId];
    }
}