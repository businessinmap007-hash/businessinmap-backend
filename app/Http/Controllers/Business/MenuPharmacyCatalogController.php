<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\Medicine;
use App\Models\MenuItem;
use App\Support\SaleUnits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «الصيدلية لها قائمة بكل الادوية والاسعار اسمها قاموس الادوية قم بعمل المنيو
 *  الخاص بها» — المالك، 2026-08-26.
 *
 * Not a bulk table like «تعبئة الرفوف»: the dictionary is 25,065 rows, found
 * by SEARCH — {@see \App\Models\Medicine::scopeSearch()}, the one definition
 * the doctor's own typeahead and the admin preview already share. A pharmacist
 * searches, adds what he stocks with his own price, and every other screen
 * (الأصناف، التعديل) already knows how to edit an ordinary `MenuItem` — this
 * controller's whole job is turning a dictionary row into one.
 *
 * Scoped to child #215 «صيدلية» by id: unlike `menu_market`, there is no
 * platform-wide axis yet naming «trades priced from the medicine dictionary» —
 * this is the one child that carries `medicines`, not a class of them.
 */
class MenuPharmacyCatalogController extends Controller
{
    use ResolvesOwnerCatalog {
        businessId as protected ownerBusinessId;
    }

    private const PHARMACY_CHILD_ID = 215;

    protected function businessId(): int
    {
        return (int) (Auth::id() ?: $this->ownerBusinessId());
    }

    private function assertPharmacy(): void
    {
        abort_unless($this->childId() === self::PHARMACY_CHILD_ID, 403, 'هذه الشاشة مخصّصة للصيدليات.');
    }

    public function index(): View
    {
        $this->assertPharmacy();

        $items = MenuItem::query()
            ->where('business_id', $this->businessId())
            ->whereNotNull('medicine_id')
            ->with('medicine')
            ->orderByDesc('is_active')
            ->orderBy('name_en')
            ->get();

        return view('business.menu.pharmacy-catalog', [
            'items' => $items,
            // «عبوة او شريط او قطعة لا يوجد لتر وجرام وكيلو» — a drug is never
            // weighed out.
            'saleUnits' => SaleUnits::pharmacyOptions(),
        ]);
    }

    /** GET /business/menu/pharmacy/search?q= — the same typeahead a doctor gets. */
    public function search(Request $request): JsonResponse
    {
        $this->assertPharmacy();

        $term = (string) $request->get('q', '');

        $rows = Medicine::query()->search($term)->limit(20)
            ->get(['id', 'name', 'scientific_name', 'manufacturer', 'price_egp']);

        $added = MenuItem::query()
            ->where('business_id', $this->businessId())
            ->whereIn('medicine_id', $rows->pluck('id'))
            ->pluck('base_price', 'medicine_id');

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn (Medicine $m) => [
                'id' => (int) $m->id,
                'name' => $m->name,
                'scientific_name' => $m->scientific_name,
                'manufacturer' => $m->manufacturer,
                'price_egp' => $m->price_egp !== null ? (float) $m->price_egp : null,
                'already_added' => $added->has($m->id),
                'current_price' => $added->has($m->id) ? (float) $added->get($m->id) : null,
            ])->values(),
        ]);
    }

    /** Add a drug to this pharmacy's menu, or update it if it is already there. */
    public function store(Request $request): RedirectResponse
    {
        $this->assertPharmacy();

        $data = $request->validate([
            'medicine_id' => ['required', 'integer', 'exists:medicines,id'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'supply_price' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['nullable', 'integer', 'min:0'],
            'sale_unit' => ['nullable', Rule::in(SaleUnits::pharmacyCodes())],
            'brand_name' => ['nullable', 'string', 'max:191'],
        ]);

        $medicine = Medicine::query()->findOrFail($data['medicine_id']);
        $businessId = $this->businessId();

        $item = MenuItem::query()
            ->where('business_id', $businessId)
            ->where('medicine_id', $medicine->id)
            ->first() ?? new MenuItem(['business_id' => $businessId]);

        /*
         * `name_ar` gets the SAME commercial name as `name_en`, never the
         * dictionary's own `name_ar` — that column is a phonetic matching
         * key, not a registered name, and `Medicine`'s own docblock refuses
         * to let it reach anywhere a customer could read it. `menu_items`
         * has no nullable name column to fall back to, and a pharmacy
         * customer reads a drug's printed commercial name regardless of
         * locale anyway — there is no separate Arabic brand to prefer.
         */
        $item->fill([
            'business_id' => $businessId,
            'medicine_id' => $medicine->id,
            'name_ar' => $medicine->name,
            'name_en' => $medicine->name,
            'base_price' => round((float) $data['base_price'], 2),
            'supply_price' => isset($data['supply_price']) && $data['supply_price'] !== ''
                ? round((float) $data['supply_price'], 2)
                : null,
            'sale_unit' => in_array($data['sale_unit'] ?? null, SaleUnits::pharmacyCodes(), true) ? $data['sale_unit'] : null,
            'brand_name' => trim((string) ($data['brand_name'] ?? '')) ?: $medicine->manufacturer,
            'available_quantity' => ($data['quantity'] ?? '') !== '' ? max(0, (int) $data['quantity']) : null,
            'is_active' => true,
        ]);
        $item->save();

        return back()->with('success', __(':name أُضيف إلى منيوك.', ['name' => $medicine->name]));
    }
}
