<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\OfferingOption;
use App\Models\Option;
use App\Services\MerchantOfferingVocabulary;
use App\Support\MarketCatalogChildren;
use App\Support\SaleUnits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The supermarket/hypermarket/mini-market shelf, filled from the platform's own
 * product vocabulary instead of typed one item at a time.
 *
 * «المنيو الان مثلا للسوبر ماركت يمكن ان ناخد اسم مجموعة الخيارات ويكون هو
 *  القسم وتحته اقسام المجموعة نفسها … الكمية … سعر التوريد اختيارى وسعر البيع
 *  والوحدة … اسم الشركة المنتجة او الماركة اختيارى» — المالك، 2026-08-25.
 *
 * Nothing new was invented to draw the table: `MerchantOfferingVocabulary`
 * already narrows to what this child's `line` groups allow (and what the
 * merchant himself ticked, if he ticked anything sellable) — the exact
 * source `MenuOutline`'s review screen reads. Every option in it is one row;
 * the group it belongs to is the row's section, both here and later on the
 * customer's screen via `MenuItem::heading()`. A filled row IS a `MenuItem`,
 * carrying its option as an offering LINE the same way a hand-typed item
 * does — {@see \App\Models\Concerns\HasOfferingOptions}.
 *
 * Scoped to `menu_market` children — a ready-made-goods trade, by the
 * platform's own reckoning — not every trade with a `line` group: a
 * furniture showroom's «غرفة نوم» is a category several distinct hand-typed
 * pieces sit under, not one shelf-stable row a single option already names.
 *
 * @see \App\Support\MarketCatalogChildren the one rule this, the nav gate,
 *      and the customer-facing heading all read
 */
class MenuMarketCatalogController extends Controller
{
    use ResolvesOwnerCatalog {
        businessId as protected ownerBusinessId;
    }

    public function __construct(private readonly MerchantOfferingVocabulary $vocabulary)
    {
    }

    protected function businessId(): int
    {
        return (int) (Auth::id() ?: $this->ownerBusinessId());
    }

    private function assertMarket(): void
    {
        abort_unless(
            MarketCatalogChildren::includes($this->actingBusiness()),
            403,
            'هذه الشاشة مخصّصة لتجار السلع الجاهزة.'
        );
    }

    public function index(): View
    {
        $this->assertMarket();

        return view('business.menu.market-catalog', [
            'groups' => $this->groups(),
            'saleUnits' => SaleUnits::options(),
        ]);
    }

    /** @return array<int,array{name:string,rows:array<int,array<string,mixed>>,filled:int}> */
    private function groups(): array
    {
        $lines = $this->vocabulary->for($this->businessId(), $this->childId(), $this->rootId())['lines'];

        $existing = $this->existingByOption();

        $groups = [];

        foreach ($lines as $groupName => $options) {
            $rows = [];

            foreach ($options as $option) {
                $item = $existing->get((int) $option->id);

                $rows[] = [
                    'option_id' => (int) $option->id,
                    'name' => (string) ($option->name_ar ?: $option->name_en),
                    'item' => $item,
                ];
            }

            $groups[] = [
                'name' => (string) $groupName,
                'rows' => $rows,
                'filled' => count(array_filter($rows, fn ($r) => $r['item'] !== null)),
            ];
        }

        return $groups;
    }

    /** This business's existing catalog rows, keyed by the option they price. */
    private function existingByOption()
    {
        $ids = DB::table('offering_options as oo')
            ->join('menu_items as m', 'm.id', '=', 'oo.offering_id')
            ->where('oo.offering_type', (new MenuItem())->getMorphClass())
            ->where('oo.role', OfferingOption::ROLE_LINE)
            ->where('m.business_id', $this->businessId())
            ->pluck('m.id', 'oo.option_id');

        if ($ids->isEmpty()) {
            return collect();
        }

        $items = MenuItem::query()
            ->whereIn('id', $ids->values())
            ->with('offeringOptions.option')
            ->get()
            ->keyBy('id');

        return $ids->map(fn ($menuItemId) => $items->get($menuItemId))->filter();
    }

    public function update(Request $request): RedirectResponse
    {
        $this->assertMarket();

        $picks = $this->vocabulary->pickableIds($this->businessId(), $this->childId(), $this->rootId())['lines'];
        $existing = $this->existingByOption();
        $businessId = $this->businessId();

        $rows = (array) $request->input('rows', []);
        $saved = 0;
        $cleared = 0;

        DB::transaction(function () use ($rows, $picks, $existing, $businessId, &$saved, &$cleared) {
            foreach ($rows as $optionId => $data) {
                $optionId = (int) $optionId;

                // Refuses anything outside this merchant's own vocabulary —
                // same guard MenuItemController::applyVocabulary uses.
                if (! $picks->contains($optionId)) {
                    continue;
                }

                $item = $existing->get($optionId);

                $price = ($data['base_price'] ?? '') !== '' ? round((float) $data['base_price'], 2) : null;
                $supply = ($data['supply_price'] ?? '') !== '' ? round((float) $data['supply_price'], 2) : null;
                $qty = ($data['quantity'] ?? '') !== '' ? max(0, (int) $data['quantity']) : null;
                $unit = trim((string) ($data['sale_unit'] ?? '')) ?: null;
                $brand = trim((string) ($data['brand_name'] ?? '')) ?: null;

                // «السعر» هو ما يجعل الصف صنفًا حيًّا — تمامًا كما فى شاشة
                // الإضافة اليدوية، حيث هو الحقل الوحيد الإلزامى.
                if ($price === null) {
                    if ($item && $item->is_active) {
                        $item->update(['is_active' => false]);
                        $cleared++;
                    }

                    continue;
                }

                if (! $item) {
                    $option = Option::find($optionId);

                    if (! $option) {
                        continue;
                    }

                    $item = new MenuItem([
                        'business_id' => $businessId,
                        'name_ar' => $option->name_ar,
                        'name_en' => $option->name_en,
                        'sort_order' => 0,
                    ]);
                }

                $item->fill([
                    'business_id' => $businessId,
                    'base_price' => $price,
                    'supply_price' => $supply,
                    'sale_unit' => in_array($unit, SaleUnits::codes(), true) ? $unit : null,
                    'brand_name' => $brand,
                    'available_quantity' => $qty,
                    'is_active' => true,
                ]);
                $item->save();
                $item->syncOfferingOptions($optionId, $item->modifierOptions()->pluck('id')->all(), $item->currentOfferingAdjustments());

                $saved++;
            }
        });

        return back()->with(
            'success',
            "تم حفظ {$saved} صنفًا" . ($cleared > 0 ? " وتعطيل {$cleared} صنفًا فُرِّغ." : '.')
        );
    }
}
