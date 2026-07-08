<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\BusinessServicePrice;
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        $exists = BusinessServicePrice::query()
            ->where('business_id', $this->businessId())
            ->where('child_id', $this->childId())
            ->where('service_id', $data['service_id'])
            ->where('bookable_item_type', $data['bookable_item_type'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'bookable_item_type' => 'يوجد سعر بالفعل لهذا النوع مع هذه الخدمة. عدّله بدل إضافة سعر جديد.',
            ]);
        }

        BusinessServicePrice::create($data + [
            'business_id' => $this->businessId(),
            'child_id' => $this->childId(),
        ]);

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
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $row = $this->scopedRow($id);

        $data = $this->validateData($request);

        $duplicate = BusinessServicePrice::query()
            ->where('business_id', $this->businessId())
            ->where('child_id', $this->childId())
            ->where('service_id', $data['service_id'])
            ->where('bookable_item_type', $data['bookable_item_type'])
            ->where('id', '!=', $row->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'bookable_item_type' => 'يوجد سعر آخر لنفس النوع والخدمة.',
            ]);
        }

        $row->update($data);

        return back()->with('success', 'تم تحديث السعر بنجاح.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->scopedRow($id)->delete();

        return redirect()
            ->route('business.prices.index')
            ->with('success', 'تم حذف السعر بنجاح.');
    }

    protected function validateData(Request $request): array
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer'],
            'bookable_item_type' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'charge_mode' => ['nullable', 'in:standard,free,reservation_fee,minimum_charge'],
            'charge_amount' => ['nullable', 'numeric', 'min:0'],
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
            'currency' => strtoupper(trim((string) ($data['currency'] ?? 'EGP'))) ?: 'EGP',
            'is_active' => (int) $request->boolean('is_active'),
            'discount_enabled' => $discountEnabled,
            'discount_percent' => $discountEnabled ? (int) ($data['discount_percent'] ?? 0) : 0,
        ];
    }
}
