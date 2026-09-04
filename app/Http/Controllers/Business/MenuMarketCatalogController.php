<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\BusinessMenuSetting;
use App\Services\Menu\MenuMarketCatalogService;
use App\Support\MarketCatalogChildren;
use App\Support\SaleUnits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The supermarket/hypermarket/mini-market shelf, filled from the platform's own
 * product vocabulary instead of typed one item at a time.
 *
 * «المنيو الان مثلا للسوبر ماركت يمكن ان ناخد اسم مجموعة الخيارات ويكون هو
 *  القسم وتحته اقسام المجموعة نفسها … الكمية … سعر التوريد اختيارى وسعر البيع
 *  والوحدة … اسم الشركة المنتجة او الماركة اختيارى» — المالك، 2026-08-25.
 *
 * The business rules (default-margin auto-price, "a blank price clears the
 * row", vocabulary scoping) live in {@see MenuMarketCatalogService}, shared
 * with the bim_app API surface — this controller is only the Blade wiring.
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

    public function __construct(private readonly MenuMarketCatalogService $catalog)
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
            'groups' => $this->catalog->rawGroups($this->businessId(), $this->childId(), $this->rootId()),
            'saleUnits' => SaleUnits::options(),
            'defaultMargin' => BusinessMenuSetting::query()
                ->where('business_id', $this->businessId())
                ->value('default_margin_percent'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->assertMarket();

        $result = $this->catalog->save(
            $this->businessId(),
            $this->childId(),
            $this->rootId(),
            (array) $request->input('rows', [])
        );

        return back()->with(
            'success',
            "تم حفظ {$result['saved']} صنفًا" . ($result['cleared'] > 0 ? " وتعطيل {$result['cleared']} صنفًا فُرِّغ." : '.')
        );
    }
}
