<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Services\Promotions\PromotionCatalog;
use App\Services\Promotions\PromotionPricing;
use App\Services\Promotions\PromotionService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * «العروض والخصومات»: a business writes the discounts its customers are priced by, and a customer reads the ones running on a
 * business. Written under `business/promotions` (the `offers` capability, so a delegate can); read at `businesses/{id}/promotions`.
 */
class PromotionController extends Controller
{
    public function __construct(
        private readonly PromotionService $promotions,
        private readonly PromotionCatalog $catalog,
        private readonly PromotionPricing $pricing
    ) {
    }

    /* ------------------------------------------------------------ the business */

    /** GET business/promotions */
    public function index(Request $request)
    {
        $rows = Promotion::query()->where('business_id', BusinessContext::id($request))->with('targets')->orderByDesc('id')->get();

        return response()->json(['success' => true, 'data' => ['promotions' => $rows->map(fn (Promotion $p) => $this->promotions->serialize($p))->values(), 'rules' => $this->promotions->rules()]]);
    }

    /** GET business/promotions/catalog — the products by section, to pick from. */
    public function catalog(Request $request)
    {
        return response()->json(['success' => true, 'data' => $this->catalog->pickerFor(BusinessContext::id($request))]);
    }

    /** POST business/promotions */
    public function store(Request $request)
    {
        $promotion = $this->promotions->create(BusinessContext::id($request), $this->validated($request));

        return response()->json([
            'success' => true,
            'message' => __('أُنشئ العرض.'),
            'data' => ['promotion' => $this->promotions->serialize($promotion), 'not_yet_discounted' => $this->promotions->recentlyRaised($promotion)],
        ], 201);
    }

    /** PUT business/promotions/{promotion} */
    public function update(Request $request, int $promotion)
    {
        $row = $this->owned($request, $promotion);
        $row = $this->promotions->update($row, $this->validated($request));

        return response()->json([
            'success' => true,
            'message' => __('حُفظ العرض.'),
            'data' => ['promotion' => $this->promotions->serialize($row), 'not_yet_discounted' => $this->promotions->recentlyRaised($row)],
        ]);
    }

    /** POST business/promotions/{promotion}/toggle — pause or resume. */
    public function toggle(Request $request, int $promotion)
    {
        $row = $this->owned($request, $promotion);
        $this->promotions->setActive($row, ! $row->is_active);

        return response()->json(['success' => true, 'data' => ['promotion' => $this->promotions->serialize($row->fresh('targets'))]]);
    }

    /** DELETE business/promotions/{promotion} */
    public function destroy(Request $request, int $promotion)
    {
        $row = $this->owned($request, $promotion);
        $row->targets()->delete();
        $row->delete();

        return response()->json(['success' => true, 'message' => __('حُذف العرض.')]);
    }

    /* ------------------------------------------------------------ the public */

    /** GET businesses/{business}/promotions — what is running there, for the page's offers tab. */
    public function forBusiness(Request $request, int $business)
    {
        $viewer = (int) optional($request->user('sanctum'))->id;
        $rows = $this->pricing->running($business, $viewer);

        return response()->json(['success' => true, 'data' => ['promotions' => $rows->map(fn (Promotion $p) => $this->promotions->serialize($p, true))->values()]]);
    }

    /* ------------------------------------------------------------ helpers */

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'kind' => ['required', Rule::in(Promotion::KINDS)],
            'params' => ['required', 'array'],
            'params.value' => ['nullable', 'numeric', 'min:0'],
            'params.n' => ['nullable', 'integer', 'min:2', 'max:20'],
            'params.pay' => ['nullable', 'integer', 'min:1', 'max:19'],
            'params.same_item' => ['nullable', 'boolean'],
            'params.max_groups' => ['nullable', 'integer', 'min:1', 'max:50'],
            'params.min_total' => ['nullable', 'numeric', 'min:0'],
            'audience' => ['nullable', Rule::in([Promotion::AUDIENCE_ALL, Promotion::AUDIENCE_NEW])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'targets' => ['required', 'array', 'min:1', 'max:200'],
            'targets.*.type' => ['required', Rule::in(Promotion::TARGET_TYPES)],
            'targets.*.id' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    private function owned(Request $request, int $id): Promotion
    {
        return Promotion::query()->where('id', $id)->where('business_id', BusinessContext::id($request))->with('targets')->firstOrFail();
    }
}
