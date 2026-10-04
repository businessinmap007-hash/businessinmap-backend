<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Services\Menu\StoreTerms;
use Illuminate\Http\Request;

/**
 * «شروط المتجر» of the signed-in business — see {@see StoreTerms}.
 */
final class BusinessTermsController extends Controller
{
    use ResolvesOwnerCatalog;

    public function __construct(private readonly StoreTerms $terms)
    {
    }

    /** GET /api/v2/business/menu/terms — the store's policies, each option flagged `selected`. */
    public function index(Request $request)
    {
        return response()->json(['success' => true, 'data' => ['terms' => $this->terms->forMerchant($this->businessId($request))]]);
    }

    /**
     * PUT /api/v2/business/menu/terms — `{"groups": {"51": [210, 211], "395": []}}`: the options chosen
     * for each policy. An empty list clears that policy; a policy left out is untouched.
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'groups' => ['required', 'array'],
            'groups.*' => ['nullable', 'array'],
            'groups.*.*' => ['integer'],
        ]);

        return response()->json([
            'success' => true,
            'data' => ['terms' => $this->terms->save($this->businessId($request), $data['groups'])],
        ]);
    }
}
