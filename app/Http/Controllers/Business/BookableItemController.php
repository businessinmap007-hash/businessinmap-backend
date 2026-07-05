<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use App\Models\CategoryPlatformService;
use App\Models\CategoryServiceConfig;
use App\Models\PlatformService;
use App\Models\PlatformServiceItemType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * "My bookable units" for the business owner.
 *
 * Pure inventory: the owner adds the physical units they actually own
 * (room 101, table 5) picking from the item types allowed for their own
 * category_child. Prices/deposits live in BusinessServicePrice (per type),
 * not here, per the services blueprint. Every query is scoped to the owner.
 */
class BookableItemController extends Controller
{
    private function businessId(): int
    {
        return (int) Auth::id();
    }

    private function childId(): int
    {
        return (int) (Auth::user()->category_child_id ?? 0);
    }

    /**
     * Services actually offered by the owner's category_child (active links).
     */
    private function servicesForChild(): \Illuminate\Support\Collection
    {
        $childId = $this->childId();

        if ($childId <= 0) {
            return collect();
        }

        $serviceIds = CategoryPlatformService::query()
            ->where('child_id', $childId)
            ->where('is_active', 1)
            ->pluck('platform_service_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        if (empty($serviceIds)) {
            return collect();
        }

        return PlatformService::query()
            ->whereIn('id', $serviceIds)
            ->where('is_active', 1)
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get(['id', 'key', 'name_ar', 'name_en']);
    }

    /**
     * Item types the owner may use, keyed by service:
     * [serviceId => [['key','label'], ...]]. Restricted to the owner's child
     * via CategoryServiceConfig.allowed_item_types when configured.
     */
    private function allowedTypesByService(\Illuminate\Support\Collection $services): array
    {
        $childId = $this->childId();
        $map = [];

        foreach ($services as $service) {
            $serviceId = (int) $service->id;

            $baseTypes = PlatformServiceItemType::query()
                ->where('platform_service_id', $serviceId)
                ->where('is_active', 1)
                ->ordered()
                ->get(['key', 'name_ar', 'name_en']);

            $restricted = CategoryServiceConfig::query()
                ->where('child_id', $childId)
                ->where('platform_service_id', $serviceId)
                ->where('is_active', 1)
                ->get()
                ->flatMap(function (CategoryServiceConfig $config) {
                    $data = is_array($config->config) ? $config->config : [];
                    return $data['allowed_item_types'] ?? [];
                })
                ->map(fn ($t) => trim((string) $t))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $map[$serviceId] = $baseTypes
                ->when(! empty($restricted), fn ($rows) => $rows->filter(fn ($r) => in_array((string) $r->key, $restricted, true)))
                ->map(fn (PlatformServiceItemType $r) => [
                    'key' => (string) $r->key,
                    'label' => $r->displayName('ar'),
                ])
                ->values()
                ->all();
        }

        return $map;
    }

    private function scopedItem(int $id): BookableItem
    {
        return BookableItem::query()
            ->where('business_id', $this->businessId())
            ->findOrFail($id);
    }

    public function index(Request $request): View
    {
        $serviceId = (int) $request->get('service_id', 0);
        $q = trim((string) $request->get('q', ''));

        $services = $this->servicesForChild();

        $rows = BookableItem::query()
            ->with(['service:id,key,name_ar,name_en'])
            ->where('business_id', $this->businessId())
            ->when($serviceId > 0, fn ($query) => $query->where('service_id', $serviceId))
            ->when($q !== '', function ($query) use ($q) {
                $term = '%' . mb_strtolower($q) . '%';
                $query->where(function ($sub) use ($term) {
                    $sub->whereRaw('LOWER(code) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(title) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(item_type) LIKE ?', [$term]);
                });
            })
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('business.bookable-items.index', [
            'rows' => $rows,
            'services' => $services,
            'serviceId' => $serviceId,
            'q' => $q,
            'childId' => $this->childId(),
        ]);
    }

    public function create(): View
    {
        $services = $this->servicesForChild();

        return view('business.bookable-items.create', [
            'row' => new BookableItem(['is_active' => 1, 'quantity' => 1]),
            'services' => $services,
            'allowedTypesByService' => $this->allowedTypesByService($services),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        BookableItem::create($data + [
            'business_id' => $this->businessId(),
        ]);

        return redirect()
            ->route('business.bookable-items.index', ['service_id' => $data['service_id']])
            ->with('success', 'تمت إضافة الوحدة بنجاح.');
    }

    public function edit(int $id): View
    {
        $row = $this->scopedItem($id);
        $services = $this->servicesForChild();

        return view('business.bookable-items.edit', [
            'row' => $row,
            'services' => $services,
            'allowedTypesByService' => $this->allowedTypesByService($services),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $row = $this->scopedItem($id);

        $data = $this->validateData($request);

        $row->update($data);

        return back()->with('success', 'تم تحديث الوحدة بنجاح.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $row = $this->scopedItem($id);
        $row->delete();

        return redirect()
            ->route('business.bookable-items.index')
            ->with('success', 'تم حذف الوحدة بنجاح.');
    }

    protected function validateData(Request $request): array
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer'],
            'item_type' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:191'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable'],
        ], [], [
            'service_id' => 'الخدمة',
            'item_type' => 'نوع العنصر',
            'code' => 'الكود',
        ]);

        $serviceId = (int) $data['service_id'];
        $itemType = trim((string) $data['item_type']);

        // The service must be one this owner's child actually offers, and the
        // type must be allowed for that (child, service). Guards against
        // tampering with the posted select values.
        $services = $this->servicesForChild();
        if (! $services->contains('id', $serviceId)) {
            abort(422, 'هذه الخدمة غير متاحة لنشاطك.');
        }

        $allowed = $this->allowedTypesByService($services)[$serviceId] ?? [];
        $allowedKeys = array_column($allowed, 'key');
        if (! in_array($itemType, $allowedKeys, true)) {
            abort(422, 'نوع العنصر غير مسموح لنشاطك مع هذه الخدمة.');
        }

        return [
            'service_id' => $serviceId,
            'item_type' => $itemType,
            'code' => trim((string) $data['code']),
            'title' => trim((string) ($data['title'] ?? '')) ?: null,
            'capacity' => ! empty($data['capacity']) ? (int) $data['capacity'] : null,
            'quantity' => max(1, (int) ($data['quantity'] ?? 1)),
            'is_active' => (int) $request->boolean('is_active'),
        ];
    }
}
