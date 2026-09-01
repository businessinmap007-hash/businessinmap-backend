<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Http\Resources\V2\BusinessServicePriceResource;
use App\Models\BusinessServicePrice;
use App\Models\OfferingOption;
use App\Services\MerchantOfferingVocabulary;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * v2 business pricing — the business role manages its own price rows from the
 * app (mirrors the web Business\BusinessServicePriceController, which had no
 * API). One row per (service, item type, line); business_id and child_id are
 * forced from the authenticated owner, never chosen, and the (service, item
 * type) must be one the owner's subcategory actually offers (assertAllowed).
 * The business-only gate is the `business` middleware.
 *
 * The line/modifier vocabulary sync (syncOfferingOptions) was missing here
 * entirely at first — the web controller has always had it, so a price
 * created through THIS endpoint could carry a service and an item type but
 * never say which line it actually sells («غرفة مفردة» vs «غرفة مزدوجة»),
 * which is the one thing that makes two rows on the same (service, item
 * type) mean anything different at all.
 */
final class BusinessServicePriceController extends Controller
{
    use ResolvesOwnerCatalog;

    public function __construct(private readonly MerchantOfferingVocabulary $vocabulary)
    {
    }

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
     * The services the owner offers, the item types it may price per
     * service, and the line/modifier vocabulary it may sell in — everything
     * the app needs to build the create form. See MerchantOfferingVocabulary
     * for why this is narrowed to what THIS merchant may say, not the
     * platform's full option catalogue.
     */
    public function options()
    {
        $services = $this->servicesForChild();
        $allowed = $this->allowedTypesByService($services);
        $vocabulary = $this->vocabulary->for($this->businessId(), $this->childId(), $this->rootId());

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
                'lines' => $this->vocabularyGroups($vocabulary['lines']),
                'modifiers' => $this->vocabularyGroups($vocabulary['modifiers']),
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
        [$line, $modifiers, $adjustments] = $this->chosenVocabulary($request);

        if ($this->duplicateExists($data, $line, null)) {
            throw ValidationException::withMessages([
                'bookable_item_type' => [__('يوجد سعر بالفعل لهذا النوع مع هذه الخدمة. عدّله بدل إضافة سعر جديد.')],
            ]);
        }

        $row = BusinessServicePrice::create($data + [
            'business_id' => $this->businessId(),
            'child_id' => $this->childId(),
        ]);
        $row->syncOfferingOptions($line, $modifiers, $adjustments);

        return (new BusinessServicePriceResource($row->load('service:id,key,name_ar,name_en')))
            ->additional(['success' => true])->response()->setStatusCode(201);
    }

    /** PUT/PATCH /api/v2/business/prices/{price} */
    public function update(Request $request, int $price)
    {
        $row = $this->scopedRow($price);
        $data = $this->validatedData($request);
        [$line, $modifiers, $adjustments] = $this->chosenVocabulary($request);

        if ($this->duplicateExists($data, $line, $row->id)) {
            throw ValidationException::withMessages([
                'bookable_item_type' => [__('يوجد سعر آخر لنفس النوع والخدمة بنفس السطر.')],
            ]);
        }

        $row->update($data);
        $row->syncOfferingOptions($line, $modifiers, $adjustments);

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

    /**
     * A price is unique per (service, item type) AND the line it sells —
     * without the line this could hold one «كشف» and no more, so a hospital
     * charging 300 for عظام and 250 for باطنة would have nowhere to say the
     * second. Mirrors the web controller's own duplicateExists exactly.
     *
     * @param array<string,mixed> $data
     */
    private function duplicateExists(array $data, ?int $lineId, ?int $ignoreId = null): bool
    {
        $candidates = BusinessServicePrice::query()
            ->where('business_id', $this->businessId())
            ->where('child_id', $this->childId())
            ->where('service_id', $data['service_id'])
            ->where('bookable_item_type', $data['bookable_item_type'])
            ->when($ignoreId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->pluck('id');

        if ($candidates->isEmpty()) {
            return false;
        }

        $taken = \Illuminate\Support\Facades\DB::table('offering_options')
            ->where('offering_type', (new BusinessServicePrice)->getMorphClass())
            ->whereIn('offering_id', $candidates)
            ->where('role', 'line')
            ->pluck('option_id', 'offering_id');

        // Rows that name no line at all collide only with another nameless row.
        return $candidates->contains(fn ($id) => (int) ($taken[$id] ?? 0) === (int) $lineId);
    }

    /**
     * The line and modifiers this merchant chose, filtered to what he is
     * actually allowed to say — an option he never claimed about himself, or
     * one from a descriptive group, is dropped rather than trusted. Mirrors
     * the web controller's chosenVocabulary exactly.
     *
     * @return array{0:?int,1:array<int,int>,2:array<int,array{type:string,value:float}>}
     */
    private function chosenVocabulary(Request $request): array
    {
        $picks = $this->vocabulary->pickableIds($this->businessId(), $this->childId(), $this->rootId());

        $line = (int) $request->input('line_option_id', 0);
        $line = $picks['lines']->contains($line) ? $line : null;

        $modifiers = collect($request->input('modifier_option_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $picks['modifiers']->contains($id))
            ->values()
            ->all();

        $values = (array) $request->input('modifier_adjust', []);
        $types = (array) $request->input('modifier_adjust_type', []);

        $adjustments = [];
        foreach ($modifiers as $id) {
            $value = $values[$id] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $adjustments[$id] = [
                'type' => (string) ($types[$id] ?? OfferingOption::ADJUST_AMOUNT),
                'value' => round((float) $value, 2),
            ];
        }

        return [$line, $modifiers, $adjustments];
    }

    /** @return array<int,array{group:string,options:array<int,array{id:int,name:?string}>}> */
    private function vocabularyGroups(Collection $groups): array
    {
        return $groups->map(fn ($options, $groupName) => [
            'group' => $groupName,
            'options' => collect($options)->map(fn ($o) => [
                'id' => (int) $o->id,
                'name' => app()->getLocale() === 'en' ? ($o->name_en ?: $o->name_ar) : ($o->name_ar ?: $o->name_en),
            ])->values(),
        ])->values()->all();
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
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
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
            // «الكشف ٣٠ دقيقة والاستشارة ٢٠»: blank means «no fixed length» —
            // a hotel room has none.
            'duration_minutes' => ($m = (int) ($data['duration_minutes'] ?? 0)) > 0 ? $m : null,
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
