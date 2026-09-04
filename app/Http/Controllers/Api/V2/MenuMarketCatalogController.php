<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\BusinessMenuSetting;
use App\Services\Menu\MenuMarketCatalogService;
use App\Support\BusinessContext;
use App\Support\MarketCatalogChildren;
use App\Support\SaleUnits;
use Illuminate\Http\Request;

/**
 * bim_app counterpart to the web business panel's «تعبئة الرفوف» screen — the
 * same {@see MenuMarketCatalogService} both surfaces share, so the business
 * rules (default-margin auto-price, a blank price clears the row, vocabulary
 * scoping) cannot drift between them.
 */
final class MenuMarketCatalogController extends Controller
{
    public function __construct(private readonly MenuMarketCatalogService $catalog)
    {
    }

    /** GET /api/v2/business/menu/market-catalog */
    public function index(Request $request)
    {
        $business = $this->assertMarket($request);

        return response()->json([
            'success' => true,
            'data' => [
                'groups' => $this->catalog->groups(
                    (int) $business->id,
                    (int) $business->category_child_id,
                    (int) $business->category_id
                ),
                'sale_units' => collect(SaleUnits::options())
                    ->map(fn ($label, $code) => ['code' => $code, 'label' => $label])
                    ->values(),
                'default_margin_percent' => BusinessMenuSetting::query()
                    ->where('business_id', $business->id)
                    ->value('default_margin_percent'),
            ],
        ]);
    }

    /** POST /api/v2/business/menu/market-catalog */
    public function save(Request $request)
    {
        $business = $this->assertMarket($request);

        $data = $request->validate([
            'rows' => ['required', 'array'],
        ]);

        $result = $this->catalog->save(
            (int) $business->id,
            (int) $business->category_child_id,
            (int) $business->category_id,
            $data['rows']
        );

        return response()->json(['success' => true, 'data' => $result]);
    }

    private function assertMarket(Request $request): \App\Models\User
    {
        $business = BusinessContext::business($request);

        abort_unless(
            $business && MarketCatalogChildren::includes($business),
            403,
            'هذه الشاشة مخصّصة لتجار السلع الجاهزة.'
        );

        return $business;
    }
}
