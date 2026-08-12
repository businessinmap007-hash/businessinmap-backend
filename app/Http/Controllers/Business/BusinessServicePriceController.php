<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\BusinessServicePrice;
use App\Services\MerchantOfferingVocabulary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * "My prices" for the business owner.
 *
 * One price row per (service, item type) the owner offers. business_id and
 * child_id are forced from the logged-in owner — never chosen — so a price can
 * only ever belong to the owner's own business and subcategory. Price, deposit
 * and discount live here (per the services blueprint), not on the unit.
 */
class BusinessServicePriceController extends Controller
{
    use ResolvesOwnerCatalog;

    public function __construct(private readonly MerchantOfferingVocabulary $vocabulary)
    {
    }

    /**
     * What this merchant may say a price IS. A hospital that practises four
     * specialties picks from four, not from the platform's forty-one.
     */
    private function vocabulary(): array
    {
        return $this->vocabulary->for($this->businessId(), $this->childId(), $this->rootId());
    }

    private function scopedRow(int $id): BusinessServicePrice
    {
        return BusinessServicePrice::query()
            ->where('business_id', $this->businessId())
            ->findOrFail($id);
    }

    public function index(Request $request): View
    {
        $serviceId = (int) $request->get('service_id', 0);
        $services = $this->servicesForChild();

        $rows = BusinessServicePrice::query()
            ->with(['service:id,key,name_ar,name_en'])
            ->where('business_id', $this->businessId())
            ->when($serviceId > 0, fn ($query) => $query->where('service_id', $serviceId))
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('business.prices.index', [
            'rows' => $rows,
            'services' => $services,
            'serviceId' => $serviceId,
            'childId' => $this->childId(),
        ]);
    }

    public function create(): View
    {
        $services = $this->servicesForChild();

        return view('business.prices.create', [
            'row' => new BusinessServicePrice([
                'is_active' => 1,
                'currency' => 'EGP',
                'price' => 0,
                'discount_enabled' => 0,
                'discount_percent' => 0,
            ]),
            'services' => $services,
            'allowedTypesByService' => $this->allowedTypesByService($services),
            'vocabulary' => $this->vocabulary(),
            'lineId' => null,
            'modifierIds' => collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        [$line, $modifiers] = $this->chosenVocabulary($request);

        if ($this->duplicateExists($data, $line)) {
            throw ValidationException::withMessages([
                'bookable_item_type' => 'يوجد سعر بالفعل لهذا النوع مع هذه الخدمة. عدّله بدل إضافة سعر جديد.',
            ]);
        }

        $row = BusinessServicePrice::create($data + [
            'business_id' => $this->businessId(),
            'child_id' => $this->childId(),
        ]);

        $row->syncOfferingOptions($line, $modifiers);

        return redirect()
            ->route('business.prices.index', ['service_id' => $data['service_id']])
            ->with('success', 'تم حفظ السعر بنجاح.');
    }

    public function edit(int $id): View
    {
        $row = $this->scopedRow($id);
        $services = $this->servicesForChild();

        return view('business.prices.edit', [
            'row' => $row,
            'services' => $services,
            'allowedTypesByService' => $this->allowedTypesByService($services),
            'vocabulary' => $this->vocabulary(),
            'lineId' => $row->lineOption()?->id,
            'modifierIds' => $row->modifierOptions()->pluck('id'),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $row = $this->scopedRow($id);

        $data = $this->validateData($request);
        [$line, $modifiers] = $this->chosenVocabulary($request);

        if ($this->duplicateExists($data, $line, $row->id)) {
            throw ValidationException::withMessages([
                'bookable_item_type' => 'يوجد سعر آخر لنفس النوع والخدمة.',
            ]);
        }

        $row->update($data);
        $row->syncOfferingOptions($line, $modifiers);

        return back()->with('success', 'تم تحديث السعر بنجاح.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->scopedRow($id)->delete();

        return redirect()
            ->route('business.prices.index')
            ->with('success', 'تم حذف السعر بنجاح.');
    }

    /**
     * The line and modifiers this merchant chose, filtered to what he is
     * actually allowed to say — an option he never claimed about himself, or
     * one from a descriptive group, is dropped rather than trusted.
     *
     * @return array{0:?int,1:array<int,int>}
     */
    private function chosenVocabulary(Request $request): array
    {
        // Which slot an option may fill is a per-child question — a wood species
        // is a modifier on a furniture workshop and the product itself in a
        // timber yard — so it is asked of the vocabulary, not of the group.
        $picks = $this->vocabulary->pickableIds($this->businessId(), $this->childId(), $this->rootId());

        $line = (int) $request->input('line_option_id', 0);
        $line = $picks['lines']->contains($line) ? $line : null;

        $modifiers = collect($request->input('modifier_option_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $picks['modifiers']->contains($id))
            ->values()
            ->all();

        return [$line, $modifiers];
    }

    /**
     * A price is unique per (service, item type) AND the line it sells.
     *
     * Without the line this screen could hold one «كشف» and no more, so a
     * hospital charging 300 for عظام and 250 for باطنة had nowhere to say the
     * second — the gap that made priced options necessary at all.
     */
    private function duplicateExists(array $data, ?int $lineId, ?int $exceptId = null): bool
    {
        $candidates = BusinessServicePrice::query()
            ->where('business_id', $this->businessId())
            ->where('child_id', $this->childId())
            ->where('service_id', $data['service_id'])
            ->where('bookable_item_type', $data['bookable_item_type'])
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->pluck('id');

        if ($candidates->isEmpty()) {
            return false;
        }

        $taken = \Illuminate\Support\Facades\DB::table('offering_options')
            ->where('offering_type', (new BusinessServicePrice)->getMorphClass())
            ->whereIn('offering_id', $candidates)
            ->where('role', 'line')
            ->pluck('option_id', 'offering_id');

        // rows that name no line at all collide only with another nameless row
        return $candidates->contains(fn ($id) => (int) ($taken[$id] ?? 0) === (int) $lineId);
    }

    protected function validateData(Request $request): array
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer'],
            'bookable_item_type' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'charge_mode' => ['nullable', 'in:standard,free,reservation_fee,minimum_charge'],
            'charge_amount' => ['nullable', 'numeric', 'min:0'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'currency' => ['nullable', 'string', 'max:10'],
            'is_active' => ['nullable'],
            'discount_enabled' => ['nullable'],
            'discount_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ], [], [
            'service_id' => 'الخدمة',
            'bookable_item_type' => 'نوع العنصر',
            'price' => 'السعر',
            'charge_mode' => 'طريقة احتساب الوحدة',
            'charge_amount' => 'قيمة الرسوم/الحد الأدنى',
            'duration_minutes' => 'مدة الموعد',
        ]);

        $serviceId = (int) $data['service_id'];
        $itemType = trim((string) $data['bookable_item_type']);

        $this->assertAllowed($serviceId, $itemType);

        $discountEnabled = (int) $request->boolean('discount_enabled');

        $chargeMode = (string) ($data['charge_mode'] ?? BusinessServicePrice::CHARGE_STANDARD);
        if (! in_array($chargeMode, BusinessServicePrice::CHARGE_MODES, true)) {
            $chargeMode = BusinessServicePrice::CHARGE_STANDARD;
        }
        // Only the fee/minimum modes carry an amount.
        $chargeAmount = in_array($chargeMode, [BusinessServicePrice::CHARGE_RESERVATION_FEE, BusinessServicePrice::CHARGE_MINIMUM], true)
            ? round((float) ($data['charge_amount'] ?? 0), 2)
            : 0.00;

        return [
            'service_id' => $serviceId,
            'bookable_item_type' => $itemType,
            'price' => round((float) $data['price'], 2),
            'charge_mode' => $chargeMode,
            'charge_amount' => $chargeAmount,
            // «الكشف ٣٠ دقيقة والاستشارة ٢٠»: said once, here, beside the price
            // it already belongs to. Blank means «no fixed length» — a hotel
            // room has none — and the appointment falls back to 30.
            'duration_minutes' => ($m = (int) ($data['duration_minutes'] ?? 0)) > 0 ? $m : null,
            'currency' => strtoupper(trim((string) ($data['currency'] ?? 'EGP'))) ?: 'EGP',
            'is_active' => (int) $request->boolean('is_active'),
            'discount_enabled' => $discountEnabled,
            'discount_percent' => $discountEnabled ? (int) ($data['discount_percent'] ?? 0) : 0,
        ];
    }
}
