<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Http\Resources\V2\BookableItemResource;
use App\Models\BookableItem;
use Illuminate\Http\Request;

/**
 * v2 business bookable units — the business role manages the physical units it
 * owns (room 101, table 5) from the app (mirrors the web
 * Business\BookableItemController, which had no API). Pure inventory: prices and
 * deposits live in BusinessServicePrice, not here. Every row is scoped to
 * business_id = the authenticated owner, and the (service, item type) must be
 * one the owner's subcategory offers (assertAllowed). The business-only gate is
 * the `business` middleware.
 */
final class BusinessBookableItemController extends Controller
{
    use ResolvesOwnerCatalog;

    /** GET /api/v2/business/bookable-items */
    public function index(Request $request)
    {
        $data = $request->validate([
            'service_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $q = trim((string) ($data['q'] ?? ''));

        $rows = BookableItem::query()
            ->with(['service:id,key,name_ar,name_en'])
            ->where('business_id', $this->businessId())
            ->when($data['service_id'] ?? null, fn ($query, $s) => $query->where('service_id', $s))
            ->when($q !== '', function ($query) use ($q) {
                $term = '%' . mb_strtolower($q) . '%';
                $query->where(function ($sub) use ($term) {
                    $sub->whereRaw('LOWER(code) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(title) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(item_type) LIKE ?', [$term]);
                });
            })
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 50)
            ->withQueryString();

        return BookableItemResource::collection($rows)->additional(['success' => true]);
    }

    /**
     * GET /api/v2/business/bookable-items/options
     * The services the owner offers and, per service, the item types it may
     * stock — everything the app needs to build the create form.
     */
    public function options()
    {
        $services = $this->servicesForChild();
        $allowed = $this->allowedTypesByService($services);

        return response()->json([
            'success' => true,
            'data' => [
                'services' => $services->map(fn ($s) => [
                    'id' => (int) $s->id,
                    'key' => $s->key,
                    'name' => $this->localizeService($s),
                    'item_types' => array_values($allowed[(int) $s->id] ?? []),
                ])->values(),
            ],
        ]);
    }

    /** GET /api/v2/business/bookable-items/{item} */
    public function show(int $item)
    {
        $row = $this->scopedItem($item)->load('service:id,key,name_ar,name_en');

        return (new BookableItemResource($row))->additional(['success' => true]);
    }

    /** POST /api/v2/business/bookable-items */
    public function store(Request $request)
    {
        $row = BookableItem::create($this->validatedData($request) + ['business_id' => $this->businessId()]);

        return (new BookableItemResource($row->load('service:id,key,name_ar,name_en')))
            ->additional(['success' => true])->response()->setStatusCode(201);
    }

    /** PUT/PATCH /api/v2/business/bookable-items/{item} */
    public function update(Request $request, int $item)
    {
        $row = $this->scopedItem($item);
        $row->update($this->validatedData($request));

        return (new BookableItemResource($row->fresh()->load('service:id,key,name_ar,name_en')))
            ->additional(['success' => true]);
    }

    /** DELETE /api/v2/business/bookable-items/{item} */
    public function destroy(int $item)
    {
        $this->scopedItem($item)->delete();

        return response()->json(['success' => true]);
    }

    // ─────────────────────────── Helpers ───────────────────────────

    private function scopedItem(int $id): BookableItem
    {
        return BookableItem::query()
            ->where('business_id', $this->businessId())
            ->findOrFail($id);
    }

    /** Mirror of the web controller's validateData (same rules + assertAllowed). */
    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer'],
            'item_type' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:191'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $serviceId = (int) $data['service_id'];
        $itemType = trim((string) $data['item_type']);

        // Rejects a (service, item type) the owner's subcategory doesn't offer.
        $this->assertAllowed($serviceId, $itemType);

        return [
            'service_id' => $serviceId,
            'item_type' => $itemType,
            'code' => trim((string) $data['code']),
            'title' => trim((string) ($data['title'] ?? '')) ?: null,
            'capacity' => ! empty($data['capacity']) ? (int) $data['capacity'] : null,
            'quantity' => max(1, (int) ($data['quantity'] ?? 1)),
            'is_active' => (int) $request->boolean('is_active', true),
        ];
    }

    private function localizeService($service): ?string
    {
        $primary = app()->getLocale() === 'en' ? $service->name_en : $service->name_ar;

        return ($primary !== null && $primary !== '') ? $primary : (($service->name_ar ?: $service->name_en) ?: null);
    }
}
