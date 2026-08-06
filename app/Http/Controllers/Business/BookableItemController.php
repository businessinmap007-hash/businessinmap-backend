<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    use ResolvesOwnerCatalog;

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
            ->with(['service:id,key,name_ar,name_en', 'lineOption:id,name_ar,name_en'])
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
            'lineOptions' => $this->lineOptionsForUnits(),
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
            'lineOptions' => $this->lineOptionsForUnits(),
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
            'line_option_id' => ['nullable', 'integer'],
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

        // Guard the posted select values against the owner's own catalog.
        $this->assertAllowed($serviceId, $itemType);

        return [
            'service_id' => $serviceId,
            'item_type' => $itemType,
            // Which kind this unit is — «جناح» rather than just «حجز إقامة» —
            // and therefore which of the merchant's priced rows is its own.
            'line_option_id' => $this->sanitizeLineOption($data['line_option_id'] ?? null),
            'code' => trim((string) $data['code']),
            'title' => trim((string) ($data['title'] ?? '')) ?: null,
            'capacity' => ! empty($data['capacity']) ? (int) $data['capacity'] : null,
            'quantity' => max(1, (int) ($data['quantity'] ?? 1)),
            'is_active' => (int) $request->boolean('is_active'),
        ];
    }
}
