<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Services\Menu\BusinessAddons;
use Illuminate\Http\Request;

/**
 * «خدمات المحل» — see {@see BusinessAddons}: priced once, per unit of what is bought, added on top.
 */
final class BusinessAddonsController extends Controller
{
    use ResolvesOwnerCatalog;

    public function __construct(private readonly BusinessAddons $addons)
    {
    }

    /** GET /api/v2/business/menu/addons — the services this trade offers, with the shop's price for each. */
    public function index(Request $request)
    {
        return response()->json(['success' => true, 'data' => ['addons' => $this->addons->forMerchant($this->businessId())]]);
    }

    /** PUT /api/v2/business/menu/addons — `{"prices": {"<option id>": 50, "<option id>": 0}}`; 0 or empty = not offered. */
    public function update(Request $request)
    {
        $data = $request->validate([
            'prices' => ['required', 'array'],
            'prices.*' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ]);

        return response()->json(['success' => true, 'data' => ['addons' => $this->addons->save($this->businessId(), $data['prices'])]]);
    }
}
