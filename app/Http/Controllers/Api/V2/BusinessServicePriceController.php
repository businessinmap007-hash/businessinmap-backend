<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Http\Resources\V2\BusinessServicePriceResource;
use App\Models\BusinessServicePrice;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * v2 business pricing — the business role manages its own price rows from the
 * app (mirrors the web Business\BusinessServicePriceController, which had no
 * API). One row per (service, item type); business_id and child_id are forced
 * from the authenticated owner, never chosen, and the (service, item type) must
 * be one the owner's subcategory actually offers (assertAllowed). The
 * business-only gate is the `business` middleware.
 */
final class BusinessServicePriceController extends Controller
{
    use ResolvesOwnerCatalog;

    /** GET /api/v2/business/prices */
    public function index(Request $request)
    {
        $data = $request->validate([
            'service_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $rows = BusinessServicePrice::query()
            ->with(['service:id,key,name_ar,name_en'])
            ->where('business_id', $this->businessId())
            ->when($data['service_id'] ?? null, fn ($q, $s) => $q->where('service_id', $s))
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 50)
            ->withQueryString();

        return BusinessServicePriceResource::collection($rows)->additional(['success' => true]);
    }

    /**
     * GET /api/v2/business/prices/options
     * The services the owner offers and, per service, the item types it may
     * price — everything the app needs to build the create form.
     */
    public function options()
    {
        $services = $this->servicesForChild();
        $allowed = $this->allowedTypesByService($services);

        return response()->json([
            'success' => true,
            'data' => [
                'charge_modes' => BusinessServicePrice::CHARGE_MODES,
                'services' => $services->map(fn ($s) => [
                    'id' => (int) $s->id,
                    'key' => $s->key,
                    'name' => $this->localizeService($s),
                    'item_types' => array_values($allowed[(int) $s->id] ?? []),
                ])->values(),
            ],
        ]);
    }

    /** GET /api/v2/business/prices/{price} */
    public function show(int $price)
    {
        $row = $this->scopedRow($price)->load('service:id,key,name_ar,name_en');

        return (new BusinessServicePriceResource($row))->additional(['success' => true]);
    }

    /** POST /api/v2/business/prices */
    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        if ($this->duplicateExists($data, null)) {
            throw ValidationException::withMessages([
                'bookable_item_type' => [__('يوجد سعر بالفعل لهذا النوع مع هذه الخدمة. عدّله بدل إضافة سعر جديد.')],
            ]);
        }

        $row = BusinessServicePrice::create($data + [
            'business_id' => $this->businessId(),
            'child_id' => $this->childId(),
        ]);

        return (new BusinessServicePriceResource($row->load('service:id,key,name_ar,name_en')))
            ->additional(['success' => true])->response()->setStatusCode(201);
    }

    /** PUT/PATCH /api/v2/business/prices/{price} */
    public function update(Request $request, int $price)
    {
        $row = $this->scopedRow($price);
        $data = $this->validatedData($request);

        if ($this->duplicateExists($data, $row->id)) {
            throw ValidationException::withMessages([
                'bookable_item_type' => [__('يوجد سعر آخر لنفس النوع والخدمة.')],
            ]);
        }

        $row->update($data);

        return (new BusinessServicePriceResource($row->fresh()->load('service:id,key,name_ar,name_en')))
            ->additional(['success' => true]);
    }

    /** DELETE /api/v2/business/prices/{price} */
    public function destroy(int $price)
    {
        $this->scopedRow($price)->delete();

        return response()->json(['success' => true]);
    }

    // ─────────────────────────── Helpers ───────────────────────────

    private function scopedRow(int $id): BusinessServicePrice
    {
        return BusinessServicePrice::query()
            ->where('business_id', $this->businessId())
            ->findOrFail($id);
    }

    /** @param array<string,mixed> $data */
    private function duplicateExists(array $data, ?int $ignoreId): bool
    {
        return BusinessServicePrice::query()
            ->where('business_id', $this->businessId())
            ->where('child_id', $this->childId())
            ->where('service_id', $data['service_id'])
            ->where('bookable_item_type', $data['bookable_item_type'])
            ->when($ignoreId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->exists();
    }

    /** Mirror of the web controller's validateData (same rules + assertAllowed). */
    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer'],
            'bookable_item_type' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'charge_mode' => ['nullable', 'in:standard,free,reservation_fee,minimum_charge'],
            'charge_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
            'discount_enabled' => ['nullable', 'boolean'],
            'discount_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $serviceId = (int) $data['service_id'];
        $itemType = trim((string) $data['bookable_item_type']);

        // Rejects a (service, item type) the owner's subcategory doesn't offer,
        // and a service the owner doesn't have — a 403 crafted-id can't slip in.
        $this->assertAllowed($serviceId, $itemType);

        $discountEnabled = (int) $request->boolean('discount_enabled');

        $chargeMode = (string) ($data['charge_mode'] ?? BusinessServicePrice::CHARGE_STANDARD);
        if (! in_array($chargeMode, BusinessServicePrice::CHARGE_MODES, true)) {
            $chargeMode = BusinessServicePrice::CHARGE_STANDARD;
        }
        $chargeAmount = in_array($chargeMode, [BusinessServicePrice::CHARGE_RESERVATION_FEE, BusinessServicePrice::CHARGE_MINIMUM], true)
            ? round((float) ($data['charge_amount'] ?? 0), 2)
            : 0.00;

        return [
            'service_id' => $serviceId,
            'bookable_item_type' => $itemType,
            'price' => round((float) $data['price'], 2),
            'charge_mode' => $chargeMode,
            'charge_amount' => $chargeAmount,
            'currency' => strtoupper(trim((string) ($data['currency'] ?? 'EGP'))) ?: 'EGP',
            'is_active' => (int) $request->boolean('is_active', true),
            'discount_enabled' => $discountEnabled,
            'discount_percent' => $discountEnabled ? (int) ($data['discount_percent'] ?? 0) : 0,
        ];
    }

    private function localizeService($service): ?string
    {
        $primary = app()->getLocale() === 'en' ? $service->name_en : $service->name_ar;

        return ($primary !== null && $primary !== '') ? $primary : (($service->name_ar ?: $service->name_en) ?: null);
    }
}
