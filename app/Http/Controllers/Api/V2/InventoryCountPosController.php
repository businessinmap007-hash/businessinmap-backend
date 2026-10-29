<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\BusinessCatalogListing;
use App\Models\Inventory\Item;
use App\Models\Inventory\PosSale;
use App\Models\Inventory\PosShift;
use App\Models\Inventory\RecipeLine;
use App\Models\Inventory\Stocktake;
use App\Models\MenuItem;
use App\Services\Inventory\AvailabilitySync;
use App\Services\Inventory\InventoryAccess;
use App\Services\Inventory\PosService;
use App\Services\Inventory\RecipeService;
use App\Services\Inventory\StocktakeService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** «الجرد» and «الكاشير» — the two counter-side screens of the desktop suite. */
final class InventoryCountPosController extends Controller
{
    public function __construct(
        private readonly StocktakeService $counts,
        private readonly PosService $pos,
        private readonly InventoryAccess $access,
        private readonly AvailabilitySync $availability,
    ) {}

    // ── stocktake ────────────────────────────────────────────────────────────────────────────────────────────────────

    /** GET /business/inventory/stocktakes */
    public function stocktakes(Request $request)
    {
        $rows = Stocktake::query()->where('business_id', BusinessContext::id($request))->latest('id')->limit(50)->get()
            ->map(fn ($s) => collect($this->counts->payload($s->load('lines')))->except('lines')->all());

        return $this->ok(['stocktakes' => $rows->values()]);
    }

    /** POST /business/inventory/stocktakes — {uuid?, warehouse_id?, title?, blind?, item_ids?, group_name?} */
    public function startStocktake(Request $request)
    {
        $data = $request->validate([
            'uuid' => ['nullable', 'uuid'], 'warehouse_id' => ['nullable', 'integer'], 'title' => ['nullable', 'string', 'max:120'],
            'blind' => ['sometimes', 'boolean'], 'item_ids' => ['nullable', 'array'], 'item_ids.*' => ['integer'], 'group_name' => ['nullable', 'string', 'max:120'],
        ]);

        $count = $this->counts->start(BusinessContext::id($request), (int) $request->user()->id, $data);

        return $this->ok(['stocktake' => $this->counts->payload($count)], 201);
    }

    /** GET /business/inventory/stocktakes/{stocktake} — computed figures hidden while a blind count runs */
    public function showStocktake(Request $request, int $stocktake)
    {
        return $this->ok(['stocktake' => $this->counts->payload($this->ownCount($request, $stocktake))]);
    }

    /** POST /business/inventory/stocktakes/{stocktake}/count — {lines:[{item_id, lot_id?, counted_qty, reason?}]} */
    public function countLines(Request $request, int $stocktake)
    {
        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer'],
            'lines.*.lot_id' => ['nullable', 'integer'],
            'lines.*.counted_qty' => ['present', 'nullable', 'numeric', 'min:0'],
            'lines.*.reason' => ['nullable', 'string', 'max:180'],
        ]);

        return $this->ok(['stocktake' => $this->counts->payload($this->counts->count($this->ownCount($request, $stocktake), $data['lines']))]);
    }

    /** POST /business/inventory/stocktakes/{stocktake}/finish — the count ends; the differences show for review */
    public function finishStocktake(Request $request, int $stocktake)
    {
        return $this->ok(['stocktake' => $this->counts->payload($this->counts->finish($this->ownCount($request, $stocktake)))]);
    }

    /** POST /business/inventory/stocktakes/{stocktake}/apply — every difference becomes one adjustment move */
    public function applyStocktake(Request $request, int $stocktake)
    {
        return $this->ok(['stocktake' => $this->counts->payload($this->counts->apply($this->ownCount($request, $stocktake), (int) $request->user()->id))]);
    }

    /** POST /business/inventory/stocktakes/{stocktake}/cancel */
    public function cancelStocktake(Request $request, int $stocktake)
    {
        $count = $this->ownCount($request, $stocktake);

        if ($count->status === Stocktake::APPLIED) {
            abort(response()->json(['success' => false, 'message' => __('اعتُمد هذا الجرد ولا يُلغى؛ صحّحه بجرد جديد.')], 422));
        }

        $count->update(['status' => Stocktake::CANCELLED]);

        return $this->ok(['stocktake' => $this->counts->payload($count->fresh('lines'))]);
    }

    // ── cashier ──────────────────────────────────────────────────────────────────────────────────────────────────────

    /** GET /business/inventory/pos/catalog — what the counter sells, priced by the business, with what the stock can make */
    public function catalog(Request $request)
    {
        $this->posFeature($request);
        $businessId = BusinessContext::id($request);
        $recipes = RecipeLine::query()->where('business_id', $businessId)->get()->groupBy(fn ($l) => $l->owner_type . ':' . $l->owner_id);
        $barcodes = Item::query()->where('business_id', $businessId)->with('units')->get()->keyBy('id');

        $barcodeOf = function (string $type, int $id) use ($recipes, $barcodes): array {
            $lines = $recipes->get("$type:$id");
            if (! $lines || $lines->count() !== 1) {
                return [];
            }
            $item = $barcodes->get($lines->first()->item_id);

            return array_values(array_filter(array_merge([optional($item)->barcode], optional($item)->units?->pluck('barcode')->all() ?? [])));
        };

        $menu = MenuItem::query()->where('business_id', $businessId)->where('is_active', 1)->with(['variants', 'extras' => fn ($q) => $q->where('is_active', 1)])
            ->orderBy('menu_section_id')->orderBy('sort_order')->get()
            ->map(fn (MenuItem $m) => [
                'owner_type' => RecipeService::MENU_ITEM,
                'owner_id' => $m->id,
                'name' => $m->name_ar ?: $m->name_en,
                'section_id' => $m->menu_section_id,
                'price' => (float) $m->base_price,
                'available' => $recipes->has(RecipeService::MENU_ITEM . ':' . $m->id) ? $this->availability->canMake(RecipeService::MENU_ITEM, $m->id) : null,
                'barcodes' => $barcodeOf(RecipeService::MENU_ITEM, $m->id),
                'variants' => $m->variants->map(fn ($v) => ['id' => $v->id, 'name' => $v->display_name, 'price' => $v->resolvePrice((float) $m->base_price)])->values(),
                'extras' => $m->extras->map(fn ($e) => ['id' => $e->id, 'name' => $e->name_ar ?: $e->name_en, 'price' => (float) $e->price])->values(),
            ]);

        $retail = BusinessCatalogListing::query()->where('business_id', $businessId)->where('is_active', 1)->get()
            ->map(fn (BusinessCatalogListing $l) => [
                'owner_type' => RecipeService::RETAIL_LISTING,
                'owner_id' => $l->id,
                'name' => $l->offeringLabel('#' . $l->id),
                'price' => (float) $l->price,
                'available' => $recipes->has(RecipeService::RETAIL_LISTING . ':' . $l->id) ? $this->availability->canMake(RecipeService::RETAIL_LISTING, $l->id) : null,
                'barcodes' => $barcodeOf(RecipeService::RETAIL_LISTING, $l->id),
            ]);

        return $this->ok(['products' => $menu->concat($retail)->values()]);
    }

    /** POST /business/inventory/pos/shifts — {uuid?, device_uuid?, opening_cash} */
    public function openShift(Request $request)
    {
        $this->posFeature($request);
        $data = $request->validate(['uuid' => ['nullable', 'uuid'], 'device_uuid' => ['nullable', 'uuid'], 'opening_cash' => ['nullable', 'numeric', 'min:0'], 'opened_at' => ['nullable', 'date']]);

        return $this->ok(['shift' => $this->pos->openShift(BusinessContext::id($request), (int) $request->user()->id, $data)], 201);
    }

    /** GET /business/inventory/pos/shifts/{shift} — the drawer's summary so far */
    public function showShift(Request $request, int $shift)
    {
        return $this->ok($this->pos->shiftSummary($this->ownShift($request, $shift)));
    }

    /** POST /business/inventory/pos/shifts/{shift}/close — {counted_cash}: the drawer counted against the sales */
    public function closeShift(Request $request, int $shift)
    {
        $data = $request->validate(['counted_cash' => ['required', 'numeric', 'min:0']]);

        return $this->ok($this->pos->closeShift($this->ownShift($request, $shift), (float) $data['counted_cash']));
    }

    /** GET /business/inventory/pos/sales?shift_id= */
    public function sales(Request $request)
    {
        $rows = PosSale::query()->where('business_id', BusinessContext::id($request))
            ->when($request->query('shift_id'), fn ($q, $s) => $q->where('shift_id', (int) $s))
            ->with('lines')->latest('id')->limit(200)->get();

        return $this->ok(['sales' => $rows]);
    }

    /** POST /business/inventory/pos/sales */
    public function sell(Request $request)
    {
        $this->posFeature($request);
        $data = $request->validate([
            'uuid' => ['nullable', 'uuid'],
            'shift_id' => ['nullable', 'integer'],
            'device_uuid' => ['nullable', 'uuid'],
            'warehouse_id' => ['nullable', 'integer'],
            'sold_at' => ['nullable', 'date'],
            'paid' => ['nullable', 'numeric', 'min:0'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.owner_type' => ['required', Rule::in([RecipeService::MENU_ITEM, RecipeService::RETAIL_LISTING])],
            'lines.*.owner_id' => ['required', 'integer'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.variant_id' => ['nullable', 'integer'],
            'lines.*.extras' => ['nullable', 'array'],
            'lines.*.extras.*.id' => ['required', 'integer'],
            'lines.*.extras.*.qty' => ['nullable', 'integer', 'min:1'],
        ]);

        $sale = $this->pos->sell(BusinessContext::id($request), (int) $request->user()->id, $data);

        return $this->ok(['sale' => $sale, 'change' => round((float) $sale->paid - (float) $sale->total, 2)], 201);
    }

    /** POST /business/inventory/pos/sales/{sale}/return */
    public function returnSale(Request $request, int $sale)
    {
        $model = PosSale::query()->where('business_id', BusinessContext::id($request))->findOrFail($sale);

        return $this->ok(['sale' => $this->pos->returnSale($model, (int) $request->user()->id)]);
    }

    // ── helpers ──────────────────────────────────────────────────────────────────────────────────────────────────────

    private function posFeature(Request $request): void
    {
        $this->access->assertFeature(BusinessContext::business($request), InventoryAccess::POS);
    }

    private function ownCount(Request $request, int $id): Stocktake
    {
        return Stocktake::query()->where('business_id', BusinessContext::id($request))->with('lines')->findOrFail($id);
    }

    private function ownShift(Request $request, int $id): PosShift
    {
        return PosShift::query()->where('business_id', BusinessContext::id($request))->findOrFail($id);
    }

    private function ok(array $data, int $status = 200)
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }
}
