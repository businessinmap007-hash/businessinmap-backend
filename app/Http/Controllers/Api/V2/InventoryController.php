<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\BusinessCatalogListing;
use App\Models\Inventory\Device;
use App\Models\Inventory\Item;
use App\Models\Inventory\ItemUnit;
use App\Models\Inventory\Move;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\Supplier;
use App\Models\Inventory\Warehouse;
use App\Models\MenuItem;
use App\Services\Inventory\CoverageService;
use App\Services\Inventory\InventoryAccess;
use App\Services\Inventory\InventoryPayload;
use App\Services\Inventory\RecipeService;
use App\Services\Inventory\StockLedger;
use App\Services\Inventory\SuiteSubscriptionService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The inventory suite's own surface (desktop app first, the owner's phone for reading and approving): its switch and
 * features, the subscription, the stock items and their ledger, the links to the menu / store, the business's devices,
 * and the feed a device syncs from.
 */
final class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryAccess $access,
        private readonly StockLedger $ledger,
        private readonly RecipeService $recipes,
        private readonly SuiteSubscriptionService $subscription,
        private readonly CoverageService $coverage,
    ) {}

    // ── settings & subscription ──────────────────────────────────────────────────────────────────────────────────────

    /** GET /business/inventory/settings */
    public function settings(Request $request)
    {
        return $this->ok($this->settingsPayload($request));
    }

    /** PATCH /business/inventory/settings — owner only: switch on (pays the first month), features, approval limit */
    public function updateSettings(Request $request)
    {
        $this->ownerOnly($request);
        $business = BusinessContext::business($request);

        if (! $this->access->available($business)) {
            throw ValidationException::withMessages(['enabled' => __('المخزون غير متاح لتصنيف نشاطك.')]);
        }

        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'features' => ['sometimes', 'array'],
            'features.*' => ['string', Rule::in(array_keys(InventoryAccess::FEATURES))],
            'po_approval_threshold' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ]);

        $settings = $this->access->settings((int) $business->id);

        if (($data['enabled'] ?? null) === true && ! $settings->enabled) {
            $this->subscription->subscribe($business);
            $settings->update(['enabled' => true]);
            $this->access->defaultWarehouse((int) $business->id);
        } elseif (($data['enabled'] ?? null) === false) {
            $settings->update(['enabled' => false]);
        }

        if (array_key_exists('features', $data)) {
            $this->access->setFeatures($business, $data['features']);
        }
        if (array_key_exists('po_approval_threshold', $data)) {
            $settings->update(['po_approval_threshold' => $data['po_approval_threshold']]);
        }

        return $this->ok($this->settingsPayload($request));
    }

    /** POST /business/inventory/subscription/renew — owner pays the month now (lifts read-only at once) */
    public function renew(Request $request)
    {
        $this->ownerOnly($request);
        $this->subscription->subscribe(BusinessContext::business($request));

        return $this->ok($this->settingsPayload($request));
    }

    // ── dashboard & «يكفي اليوم؟» ─────────────────────────────────────────────────────────────────────────────────────

    /** GET /business/inventory/dashboard */
    public function dashboard(Request $request)
    {
        $businessId = BusinessContext::id($request);
        $coverage = $this->coverage->forBusiness($businessId);

        return $this->ok([
            'summary' => $this->coverage->summary($businessId),
            'low' => array_values(array_filter($coverage, fn ($r) => $r['below_reorder'] || in_array($r['verdict'], ['no', 'tight'], true))),
            'recent_moves' => Move::query()->where('business_id', $businessId)->latest('id')->limit(15)->get()
                ->map(fn ($m) => InventoryPayload::move($m) + ['item_name' => Item::query()->whereKey($m->item_id)->value('name_ar')])->all(),
        ]);
    }

    /** GET /business/inventory/coverage — «يكفي مبيعات اليوم؟» for every item, worst first */
    public function coverage(Request $request)
    {
        return $this->ok(['items' => $this->coverage->forBusiness(BusinessContext::id($request))]);
    }

    // ── warehouses ───────────────────────────────────────────────────────────────────────────────────────────────────

    /** GET /business/inventory/warehouses */
    public function warehouses(Request $request)
    {
        $this->access->defaultWarehouse(BusinessContext::id($request));

        return $this->ok(['warehouses' => Warehouse::query()->where('business_id', BusinessContext::id($request))->orderByDesc('is_default')->orderBy('id')->get()]);
    }

    /** POST /business/inventory/warehouses */
    public function storeWarehouse(Request $request)
    {
        $business = BusinessContext::business($request);
        $data = $request->validate(['uuid' => ['nullable', 'uuid'], 'name' => ['required', 'string', 'max:120']]);

        $this->access->defaultWarehouse((int) $business->id);
        $this->access->assertFeature($business, InventoryAccess::MULTI_WAREHOUSE);

        $warehouse = Warehouse::query()->where('uuid', $data['uuid'] ?? '')->where('business_id', $business->id)->first()
            ?? Warehouse::create(['uuid' => $data['uuid'] ?? (string) Str::uuid(), 'business_id' => $business->id, 'name' => $data['name']]);

        return $this->ok(['warehouse' => $warehouse], 201);
    }

    /** PATCH /business/inventory/warehouses/{warehouse} */
    public function updateWarehouse(Request $request, int $warehouse)
    {
        $model = Warehouse::query()->where('business_id', BusinessContext::id($request))->findOrFail($warehouse);
        $data = $request->validate(['name' => ['sometimes', 'string', 'max:120'], 'is_active' => ['sometimes', 'boolean'], 'is_default' => ['sometimes', 'boolean']]);

        DB::transaction(function () use ($model, $data) {
            if (($data['is_default'] ?? false) === true) {
                Warehouse::query()->where('business_id', $model->business_id)->update(['is_default' => false]);
            }
            $model->update($data);
        });

        return $this->ok(['warehouse' => $model->fresh()]);
    }

    // ── items ────────────────────────────────────────────────────────────────────────────────────────────────────────

    /** GET /business/inventory/items?search=&group=&below=1&barcode= */
    public function items(Request $request)
    {
        $businessId = BusinessContext::id($request);
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:120'], 'group' => ['nullable', 'string', 'max:120'],
            'below' => ['nullable', 'boolean'], 'barcode' => ['nullable', 'string', 'max:64'], 'include_inactive' => ['nullable', 'boolean'],
        ]);

        $items = Item::query()->where('business_id', $businessId)->with('units')
            ->when(! ($data['include_inactive'] ?? false), fn ($q) => $q->where('is_active', 1))
            ->when($data['search'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('name_ar', 'like', "%{$s}%")->orWhere('name_en', 'like', "%{$s}%")))
            ->when($data['group'] ?? null, fn ($q, $g) => $q->where('group_name', $g))
            ->when($data['barcode'] ?? null, fn ($q, $b) => $q->where(fn ($w) => $w->where('barcode', $b)->orWhereHas('units', fn ($u) => $u->where('barcode', $b))))
            ->orderBy('group_name')->orderBy('name_ar')->get()
            ->map(fn (Item $i) => InventoryPayload::item($i));

        if ($data['below'] ?? false) {
            $items = $items->filter(fn ($i) => $i['below_reorder'])->values();
        }

        $groups = Item::query()->where('business_id', $businessId)->whereNotNull('group_name')->distinct()->orderBy('group_name')->pluck('group_name');

        return $this->ok(['items' => $items->values(), 'groups' => $groups]);
    }

    /** POST /business/inventory/items — with its purchase units and, optionally, an opening balance */
    public function storeItem(Request $request)
    {
        $business = BusinessContext::business($request);
        $data = $request->validate($this->itemRules(true));

        if ($existing = Item::query()->where('business_id', $business->id)->where('uuid', $data['uuid'] ?? '')->first()) {
            return $this->ok(['item' => InventoryPayload::item($existing)]);
        }

        if (! empty($data['track_lots'])) {
            $this->access->assertFeature($business, InventoryAccess::LOTS);
        }

        $item = DB::transaction(function () use ($business, $data, $request) {
            $item = Item::create([
                'uuid' => $data['uuid'] ?? (string) Str::uuid(),
                'business_id' => $business->id,
            ] + collect($data)->only(['name_ar', 'name_en', 'group_name', 'base_unit', 'unit_kind', 'barcode', 'reorder_level', 'track_lots'])->all());

            $this->writeUnits($item, $data['units'] ?? []);

            if (! empty($data['opening_qty'])) {
                $this->ledger->post((int) $business->id, [
                    'uuid' => $data['opening_uuid'] ?? null,
                    'item_id' => $item->id,
                    'warehouse_id' => $data['warehouse_id'] ?? null,
                    'qty' => (float) $data['opening_qty'],
                    'unit_cost' => (float) ($data['opening_cost'] ?? 0),
                    'type' => Move::OPENING,
                    'user_id' => (int) $request->user()->id,
                ]);
            }

            return $item;
        });

        return $this->ok(['item' => InventoryPayload::item($item->fresh('units'))], 201);
    }

    /** GET /business/inventory/items/{item} — with its last moves and what sells it */
    public function showItem(Request $request, int $item)
    {
        $model = $this->ownItem($request, $item);
        $owners = DB::table('inv_recipe_lines')->where('item_id', $model->id)->get(['owner_type', 'owner_id', 'qty']);

        return $this->ok([
            'item' => InventoryPayload::item($model),
            'moves' => Move::query()->where('item_id', $model->id)->latest('id')->limit(50)->get()->map(fn ($m) => InventoryPayload::move($m))->all(),
            'used_by' => $owners->map(fn ($o) => ['owner_type' => $o->owner_type, 'owner_id' => $o->owner_id, 'qty' => (float) $o->qty, 'name' => $this->ownerName($o->owner_type, (int) $o->owner_id)])->all(),
            'coverage' => $this->coverage->forBusiness((int) $model->business_id, [$model->id])[0] ?? null,
        ]);
    }

    /** PATCH /business/inventory/items/{item} — the base unit never changes once stock was booked in it */
    public function updateItem(Request $request, int $item)
    {
        $model = $this->ownItem($request, $item);
        $data = $request->validate($this->itemRules(false));

        if (isset($data['base_unit']) && $data['base_unit'] !== $model->base_unit && Move::query()->where('item_id', $model->id)->exists()) {
            throw ValidationException::withMessages(['base_unit' => __('لا تتغير الوحدة الأساسية بعد تسجيل حركات على الصنف.')]);
        }

        DB::transaction(function () use ($model, $data) {
            $model->update(collect($data)->only(['name_ar', 'name_en', 'group_name', 'base_unit', 'unit_kind', 'barcode', 'reorder_level', 'track_lots', 'is_active'])->all());
            if (array_key_exists('units', $data)) {
                $model->units()->delete();
                $this->writeUnits($model, $data['units'] ?? []);
            }
        });

        return $this->ok(['item' => InventoryPayload::item($model->fresh('units'))]);
    }

    /** GET /business/inventory/items/{item}/moves — the item's ledger, newest first, with the balance after each */
    public function itemMoves(Request $request, int $item)
    {
        $model = $this->ownItem($request, $item);
        $page = Move::query()->where('item_id', $model->id)->orderByDesc('id')->paginate(min(200, (int) $request->query('per_page', 50)));
        $balance = (float) Move::query()->where('item_id', $model->id)->where('id', '<=', optional($page->first())->id ?? 0)->sum('qty');

        $rows = [];
        foreach ($page->items() as $move) {
            $rows[] = InventoryPayload::move($move) + ['balance_after' => round($balance, 4)];
            $balance -= (float) $move->qty;
        }

        return response()->json(['success' => true, 'data' => ['moves' => $rows], 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]]);
    }

    // ── moves: opening, waste, transfer, reversal ────────────────────────────────────────────────────────────────────

    /** POST /business/inventory/moves — {kind: opening|waste|transfer, …} */
    public function storeMove(Request $request)
    {
        $business = BusinessContext::business($request);
        $data = $request->validate([
            'kind' => ['required', Rule::in(['opening', 'waste', 'transfer'])],
            'uuid' => ['nullable', 'uuid'],
            'item_id' => ['required', 'integer'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'warehouse_id' => ['nullable', 'integer'],
            'to_warehouse_id' => ['required_if:kind,transfer', 'nullable', 'integer'],
            'lot_id' => ['nullable', 'integer'],
            'reason' => ['nullable', 'string', 'max:180'],
            'device_uuid' => ['nullable', 'uuid'],
            'occurred_at' => ['nullable', 'date'],
        ]);
        $userId = (int) $request->user()->id;
        $base = collect($data)->only(['item_id', 'warehouse_id', 'lot_id', 'reason', 'device_uuid', 'occurred_at'])->all() + ['user_id' => $userId];

        $moves = match ($data['kind']) {
            'opening' => [$this->ledger->post((int) $business->id, $base + ['uuid' => $data['uuid'] ?? null, 'qty' => $data['qty'], 'unit_cost' => $data['unit_cost'] ?? 0, 'type' => Move::OPENING])],
            'waste' => (function () use ($business, $base, $data) {
                $this->access->assertFeature($business, InventoryAccess::WASTE);

                return [$this->ledger->post((int) $business->id, $base + ['uuid' => $data['uuid'] ?? null, 'qty' => -1 * $data['qty'], 'type' => Move::WASTE])];
            })(),
            'transfer' => (function () use ($business, $base, $data) {
                $this->access->assertFeature($business, InventoryAccess::MULTI_WAREHOUSE);
                $from = $this->access->warehouse((int) $business->id, $data['warehouse_id'] ?? null);
                $to = $this->access->warehouse((int) $business->id, (int) $data['to_warehouse_id']);
                if ($from->id === $to->id) {
                    throw ValidationException::withMessages(['to_warehouse_id' => __('اختر مخزنًا آخر للتحويل.')]);
                }
                $uuid = $data['uuid'] ?? (string) Str::uuid();
                $ref = ['reference_type' => 'transfer', 'reference_id' => null];

                return $this->ledger->postMany((int) $business->id, [
                    $base + $ref + ['uuid' => $uuid, 'warehouse_id' => $from->id, 'qty' => -1 * $data['qty'], 'type' => Move::TRANSFER_OUT],
                    $base + $ref + ['uuid' => $this->twin($uuid), 'warehouse_id' => $to->id, 'qty' => $data['qty'], 'type' => Move::TRANSFER_IN],
                ]);
            })(),
        };

        return $this->ok(['moves' => array_map(fn ($m) => InventoryPayload::move($m), $moves)], 201);
    }

    /** POST /business/inventory/moves/{move}/reverse — the only undo there is */
    public function reverseMove(Request $request, int $move)
    {
        $model = Move::query()->where('business_id', BusinessContext::id($request))->findOrFail($move);

        if ($model->reverses_move_id || in_array($model->type, [Move::SALE, Move::RECEIPT, Move::ADJUSTMENT], true)) {
            throw ValidationException::withMessages(['move' => __('هذه الحركة تُصحَّح من مستندها (الطلب، الاستلام أو الجرد).')]);
        }

        $reversal = $this->ledger->reverse($model, Move::REVERSAL, (int) $request->user()->id, $request->input('reason'), $request->input('uuid'));

        if (! $reversal) {
            throw ValidationException::withMessages(['move' => __('عُكست هذه الحركة من قبل.')]);
        }

        return $this->ok(['move' => InventoryPayload::move($reversal)], 201);
    }

    // ── links to the menu / store and recipes ────────────────────────────────────────────────────────────────────────

    /** GET /business/inventory/links — every menu item and retail listing, with its sizes / extras and their recipes */
    public function links(Request $request)
    {
        $businessId = BusinessContext::id($request);
        $lines = DB::table('inv_recipe_lines')->where('business_id', $businessId)->get()->groupBy(fn ($l) => $l->owner_type . ':' . $l->owner_id);
        $shape = fn (string $type, int $id) => $lines->get("$type:$id", collect())->map(fn ($l) => ['item_id' => $l->item_id, 'qty' => (float) $l->qty, 'waste_pct' => (float) $l->waste_pct])->values()->all();

        $menu = MenuItem::query()->where('business_id', $businessId)->with(['variants', 'extras'])->orderBy('menu_section_id')->orderBy('sort_order')->get()
            ->map(function (MenuItem $m) use ($shape) {
                $lines = $shape(RecipeService::MENU_ITEM, $m->id);

                return [
                    'owner_type' => RecipeService::MENU_ITEM,
                    'owner_id' => $m->id,
                    'name' => $m->name_ar ?: $m->name_en,
                    'price' => (float) $m->base_price,
                    'section_id' => $m->menu_section_id,
                    'link' => count($lines) === 0 ? 'none' : (count($lines) === 1 && $lines[0]['qty'] == 1.0 ? 'direct' : 'recipe'),
                    'lines' => $lines,
                    'cost' => $this->recipes->cost(RecipeService::MENU_ITEM, $m->id),
                    'variants' => $m->variants->map(fn ($v) => ['owner_type' => RecipeService::MENU_VARIANT, 'owner_id' => $v->id, 'name' => $v->display_name, 'lines' => $shape(RecipeService::MENU_VARIANT, $v->id)])->values()->all(),
                    'extras' => $m->extras->map(fn ($e) => ['owner_type' => RecipeService::MENU_EXTRA, 'owner_id' => $e->id, 'name' => $e->name_ar ?? $e->name_en ?? ('#' . $e->id), 'lines' => $shape(RecipeService::MENU_EXTRA, $e->id)])->values()->all(),
                ];
            });

        $retail = BusinessCatalogListing::query()->where('business_id', $businessId)->get()
            ->map(fn (BusinessCatalogListing $l) => [
                'owner_type' => RecipeService::RETAIL_LISTING,
                'owner_id' => $l->id,
                'name' => $l->offeringLabel('#' . $l->id),
                'price' => (float) $l->price,
                'link' => count($shape(RecipeService::RETAIL_LISTING, $l->id)) ? 'direct' : 'none',
                'lines' => $shape(RecipeService::RETAIL_LISTING, $l->id),
                'cost' => $this->recipes->cost(RecipeService::RETAIL_LISTING, $l->id),
            ]);

        return $this->ok(['menu' => $menu->values(), 'retail' => $retail->values()]);
    }

    /** PUT /business/inventory/recipes — {owner_type, owner_id, lines:[{item_id, qty, waste_pct?}]}; an empty list unlinks */
    public function saveRecipe(Request $request)
    {
        $business = BusinessContext::business($request);
        $data = $request->validate([
            'owner_type' => ['required', Rule::in(RecipeService::OWNERS)],
            'owner_id' => ['required', 'integer'],
            'lines' => ['present', 'array', 'max:60'],
            'lines.*.item_id' => ['required', 'integer'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.waste_pct' => ['nullable', 'numeric', 'min:0', 'max:95'],
        ]);

        $this->access->assertFeature($business, InventoryAccess::SALES_LINK);
        $isRecipe = count($data['lines']) > 1 || in_array($data['owner_type'], [RecipeService::MENU_VARIANT, RecipeService::MENU_EXTRA], true)
            || (count($data['lines']) === 1 && (float) $data['lines'][0]['qty'] != 1.0 && $data['owner_type'] === RecipeService::MENU_ITEM);
        if ($isRecipe) {
            $this->access->assertFeature($business, InventoryAccess::RECIPES);
        }

        $lines = $this->recipes->setLines((int) $business->id, $data['owner_type'], (int) $data['owner_id'], $data['lines']);

        return $this->ok([
            'owner_type' => $data['owner_type'],
            'owner_id' => (int) $data['owner_id'],
            'lines' => $lines->map(fn ($l) => ['item_id' => $l->item_id, 'qty' => (float) $l->qty, 'waste_pct' => (float) $l->waste_pct])->values(),
            'cost' => $this->recipes->cost($data['owner_type'], (int) $data['owner_id']),
        ]);
    }

    // ── devices ──────────────────────────────────────────────────────────────────────────────────────────────────────

    /** GET /business/inventory/devices */
    public function devices(Request $request)
    {
        return $this->ok(['devices' => Device::query()->where('business_id', BusinessContext::id($request))->whereNull('revoked_at')->orderByDesc('is_hub')->orderBy('id')->get()]);
    }

    /** POST /business/inventory/devices — owner registers a computer (by its own uuid) with its role and screens */
    public function storeDevice(Request $request)
    {
        $this->ownerOnly($request);
        $businessId = BusinessContext::id($request);
        $data = $request->validate($this->deviceRules(true));

        $device = DB::transaction(function () use ($businessId, $data, $request) {
            $device = Device::query()->where('uuid', $data['uuid'])->first();
            if ($device && (int) $device->business_id !== $businessId) {
                throw ValidationException::withMessages(['uuid' => __('هذا الجهاز مسجّل لنشاط آخر.')]);
            }
            if (! empty($data['is_hub'])) {
                Device::query()->where('business_id', $businessId)->update(['is_hub' => false]);
            }

            $fields = collect($data)->only(['name', 'role', 'screens', 'is_hub'])->all() + ['revoked_at' => null, 'last_seen_at' => now()];

            return $device
                ? tap($device)->update($fields)
                : Device::create(['uuid' => $data['uuid'], 'business_id' => $businessId, 'registered_by' => (int) $request->user()->id] + $fields);
        });

        return $this->ok(['device' => $device->fresh()], 201);
    }

    /** PATCH /business/inventory/devices/{device} */
    public function updateDevice(Request $request, int $device)
    {
        $this->ownerOnly($request);
        $model = Device::query()->where('business_id', BusinessContext::id($request))->findOrFail($device);
        $data = $request->validate($this->deviceRules(false));

        DB::transaction(function () use ($model, $data) {
            if (! empty($data['is_hub'])) {
                Device::query()->where('business_id', $model->business_id)->update(['is_hub' => false]);
            }
            $model->update($data);
        });

        return $this->ok(['device' => $model->fresh()]);
    }

    /** DELETE /business/inventory/devices/{device} — revoked: it is refused from now on, its history stays */
    public function revokeDevice(Request $request, int $device)
    {
        $this->ownerOnly($request);
        $model = Device::query()->where('business_id', BusinessContext::id($request))->findOrFail($device);
        $model->update(['revoked_at' => now(), 'is_hub' => false]);

        return $this->ok(['device' => $model->fresh()]);
    }

    /** POST /business/inventory/devices/{uuid}/seen — a device says it is alive; a revoked one learns it here */
    public function deviceSeen(Request $request, string $uuid)
    {
        $model = Device::query()->where('business_id', BusinessContext::id($request))->where('uuid', $uuid)->firstOrFail();

        if ($model->revoked_at) {
            return response()->json(['success' => false, 'code' => 'device_revoked', 'message' => __('أُلغي تسجيل هذا الجهاز.')], 403);
        }

        $model->update(['last_seen_at' => now()]);

        return $this->ok(['device' => $model->fresh()]);
    }

    // ── the sync feed ────────────────────────────────────────────────────────────────────────────────────────────────

    /**
     * GET /business/inventory/changes?after_move=&since= — what the main computer pulls: ledger moves after the last one
     * it holds (500 at a time), and every document changed since its last pull (app orders' moves arrive here too).
     */
    public function changes(Request $request)
    {
        $businessId = BusinessContext::id($request);
        $data = $request->validate(['after_move' => ['nullable', 'integer', 'min:0'], 'since' => ['nullable', 'date']]);
        $since = isset($data['since']) ? Carbon::parse($data['since']) : null;
        $limit = 500;

        $moves = Move::query()->where('business_id', $businessId)->where('id', '>', (int) ($data['after_move'] ?? 0))->orderBy('id')->limit($limit + 1)->get();
        $hasMore = $moves->count() > $limit;
        $moves = $moves->take($limit);
        $changed = fn ($q) => $since ? $q->where('updated_at', '>', $since) : $q;

        return $this->ok([
            'server_time' => now()->toIso8601String(),
            'last_move_id' => (int) optional($moves->last())->id ?: (int) ($data['after_move'] ?? 0),
            'has_more' => $hasMore,
            'moves' => $moves->map(fn ($m) => InventoryPayload::move($m))->values(),
            'items' => $changed(Item::query()->where('business_id', $businessId)->with('units'))->get()->map(fn ($i) => InventoryPayload::item($i))->values(),
            'warehouses' => $changed(Warehouse::query()->where('business_id', $businessId))->get(),
            'suppliers' => $changed(Supplier::query()->where('business_id', $businessId))->get()->map(fn ($s) => InventoryPayload::supplier($s))->values(),
            'purchase_orders' => $changed(PurchaseOrder::query()->where('business_id', $businessId))->get()->map(fn ($p) => InventoryPayload::purchaseOrder($p))->values(),
            'devices' => $changed(Device::query()->where('business_id', $businessId))->get(),
        ]);
    }

    // ── helpers ──────────────────────────────────────────────────────────────────────────────────────────────────────

    private function settingsPayload(Request $request): array
    {
        $business = BusinessContext::business($request);
        $category = $this->access->categoryFeatures($business);
        $on = $this->access->features($business);
        $settings = $this->access->settings((int) $business->id);

        return [
            'available' => $category !== null,
            'enabled' => $category !== null && (bool) $settings->enabled,
            'features' => collect($category['allowed'] ?? [])->map(fn ($key) => [
                'key' => $key,
                'label' => app()->getLocale() === 'en' ? InventoryAccess::FEATURES[$key][1] : InventoryAccess::FEATURES[$key][0],
                'on' => in_array($key, $on, true),
                'optional' => in_array($key, $category['optional'] ?? [], true),
            ])->values(),
            'po_approval_threshold' => $settings->po_approval_threshold,
            'subscription' => $this->subscription->state($business) + ['writable' => $this->subscription->writable($business)],
            'is_owner' => BusinessContext::isOwner($request),
            'capabilities' => BusinessContext::isOwner($request) ? null : BusinessContext::capabilities($request),
        ];
    }

    private function itemRules(bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return [
            'uuid' => ['nullable', 'uuid'],
            'name_ar' => [$req, 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
            'group_name' => ['nullable', 'string', 'max:120'],
            'base_unit' => [$req, 'string', 'max:40'],
            'unit_kind' => ['sometimes', Rule::in(Item::KINDS)],
            'barcode' => ['nullable', 'string', 'max:64'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'track_lots' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'units' => ['sometimes', 'array', 'max:10'],
            'units.*.name' => ['required', 'string', 'max:40'],
            'units.*.factor' => ['required', 'numeric', 'gt:0'],
            'units.*.barcode' => ['nullable', 'string', 'max:64'],
            'opening_qty' => ['nullable', 'numeric', 'gt:0'],
            'opening_cost' => ['nullable', 'numeric', 'min:0'],
            'opening_uuid' => ['nullable', 'uuid'],
            'warehouse_id' => ['nullable', 'integer'],
        ];
    }

    private function deviceRules(bool $creating): array
    {
        return [
            'uuid' => [$creating ? 'required' : 'prohibited', 'uuid'],
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:80'],
            'role' => [$creating ? 'required' : 'sometimes', Rule::in(['main', 'warehouse', 'purchases', 'cashier', 'manager'])],
            'screens' => ['sometimes', 'nullable', 'array'],
            'screens.*' => ['string', Rule::in(['dashboard', 'items', 'recipes', 'purchases', 'receive', 'stocktake', 'pos', 'devices', 'reports'])],
            'is_hub' => ['sometimes', 'boolean'],
        ];
    }

    private function writeUnits(Item $item, array $units): void
    {
        foreach ($units as $unit) {
            ItemUnit::create(['item_id' => $item->id, 'name' => $unit['name'], 'factor' => (float) $unit['factor'], 'barcode' => $unit['barcode'] ?? null]);
        }
    }

    private function ownItem(Request $request, int $id): Item
    {
        return Item::query()->where('business_id', BusinessContext::id($request))->with('units')->findOrFail($id);
    }

    private function ownerName(string $type, int $id): ?string
    {
        return match ($type) {
            RecipeService::MENU_ITEM => MenuItem::query()->whereKey($id)->value('name_ar'),
            RecipeService::RETAIL_LISTING => optional(BusinessCatalogListing::query()->find($id))->offeringLabel(),
            RecipeService::MENU_VARIANT => optional(\App\Models\MenuItemVariant::query()->find($id))->display_name,
            RecipeService::MENU_EXTRA => \App\Models\MenuItemExtra::query()->whereKey($id)->value('name_ar'),
            default => null,
        };
    }

    private function ownerOnly(Request $request): void
    {
        if (! BusinessContext::isOwner($request)) {
            abort(response()->json(['success' => false, 'message' => __('هذا الإجراء لصاحب النشاط فقط.')], 403));
        }
    }

    private function twin(string $uuid): string
    {
        $h = md5($uuid . '|in');

        return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-4' . substr($h, 13, 3) . '-d' . substr($h, 17, 3) . '-' . substr($h, 20, 12);
    }

    private function ok(array $data, int $status = 200)
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }
}
