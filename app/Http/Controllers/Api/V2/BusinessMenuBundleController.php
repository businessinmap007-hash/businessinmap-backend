<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\MenuBundle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * v2 business menu-bundle management — "وجبة العيلة": a fixed set of the
 * business's own menu items sold under one name and one price. Every row is
 * scoped to business_id = the authenticated user (or their delegate's
 * employer, via {@see \App\Support\BusinessContext}).
 */
final class BusinessMenuBundleController extends Controller
{
    /** GET /api/v2/business/menu/bundles */
    public function index(Request $request)
    {
        $businessId = $this->businessId($request);

        $bundles = MenuBundle::query()
            ->where('business_id', $businessId)
            ->with('items.menuItem')
            ->orderByRaw('COALESCE(sort_order, 999999) ASC')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $bundles->map(fn (MenuBundle $b) => $this->payload($b))->values(),
        ]);
    }

    /** GET /api/v2/business/menu/bundles/{bundle} */
    public function show(Request $request, int $bundle)
    {
        $model = $this->ownBundle($request, $bundle);

        return response()->json(['success' => true, 'data' => $this->payload($model)]);
    }

    /** POST /api/v2/business/menu/bundles */
    public function store(Request $request)
    {
        $businessId = $this->businessId($request);
        $data = $this->validatedBundle($request, $businessId);

        $bundle = DB::transaction(function () use ($businessId, $data) {
            $bundle = MenuBundle::create($data['bundle'] + ['business_id' => $businessId]);
            $bundle->items()->createMany($data['items']);

            return $bundle;
        });

        return response()->json(['success' => true, 'data' => $this->payload($bundle->fresh('items.menuItem'))], 201);
    }

    /** PUT/PATCH /api/v2/business/menu/bundles/{bundle} */
    public function update(Request $request, int $bundle)
    {
        $model = $this->ownBundle($request, $bundle);
        $data = $this->validatedBundle($request, $this->businessId($request));

        DB::transaction(function () use ($model, $data) {
            $model->update($data['bundle']);
            // The composition is fully fixed, edited as a whole list — a
            // diff would only add complexity a "family meal" combo never
            // needs (no per-row history to preserve, unlike extras).
            $model->items()->delete();
            $model->items()->createMany($data['items']);
        });

        return response()->json(['success' => true, 'data' => $this->payload($model->fresh('items.menuItem'))]);
    }

    /** DELETE /api/v2/business/menu/bundles/{bundle} */
    public function destroy(Request $request, int $bundle)
    {
        $this->ownBundle($request, $bundle)->delete();

        return response()->json(['success' => true]);
    }

    // ─────────────────────────── Helpers ───────────────────────────

    /** The acting business's id (owner, or a delegate's employer via business.member). */
    private function businessId(Request $request): int
    {
        return \App\Support\BusinessContext::id($request);
    }

    private function ownBundle(Request $request, int $bundleId): MenuBundle
    {
        return MenuBundle::query()
            ->where('business_id', $this->businessId($request))
            ->with('items.menuItem')
            ->findOrFail($bundleId);
    }

    /** @return array{bundle: array<string,mixed>, items: array<int,array<string,mixed>>} */
    private function validatedBundle(Request $request, int $businessId): array
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
            'pricing_mode' => ['required', Rule::in(MenuBundle::PRICING_MODES)],
            'fixed_price' => ['required_if:pricing_mode,' . MenuBundle::PRICING_FIXED, 'nullable', 'numeric', 'min:0'],
            'discount_value' => [
                'required_if:pricing_mode,' . MenuBundle::PRICING_DISCOUNT_PERCENT . ',' . MenuBundle::PRICING_DISCOUNT_FIXED,
                'nullable',
                'numeric',
                'min:0',
                Rule::when(
                    (string) $request->input('pricing_mode') === MenuBundle::PRICING_DISCOUNT_PERCENT,
                    ['max:100']
                ),
            ],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            // At least 2 items — one item under its own name is not a
            // "bundle", it is just that item.
            'items' => ['required', 'array', 'min:2'],
            // Must belong to THIS business — an id from someone else's menu
            // (or a bundle) must not slip in.
            'items.*.menu_item_id' => ['required', 'integer', Rule::exists('menu_items', 'id')->where('business_id', $businessId)],
            'items.*.qty' => ['nullable', 'integer', 'min:1', 'max:99'],
        ], [], [
            'name_ar' => __('اسم الباقة'),
            'pricing_mode' => __('طريقة التسعير'),
            'fixed_price' => __('السعر الثابت'),
            'discount_value' => __('قيمة الخصم'),
            'items' => __('الأصناف'),
        ]);

        // Two identical component rows collapse to one with the summed qty —
        // sending the same item twice should not silently double-count it.
        $items = collect($data['items'])
            ->groupBy(fn ($row) => (int) $row['menu_item_id'])
            ->map(fn ($rows, $menuItemId) => [
                'menu_item_id' => (int) $menuItemId,
                'qty' => max(1, (int) $rows->sum(fn ($r) => (int) ($r['qty'] ?? 1))),
            ])
            ->values()
            ->all();

        return [
            'bundle' => [
                'name_ar' => trim((string) $data['name_ar']),
                'name_en' => trim((string) ($data['name_en'] ?? '')) ?: null,
                'pricing_mode' => $data['pricing_mode'],
                'fixed_price' => $data['pricing_mode'] === MenuBundle::PRICING_FIXED
                    ? round((float) $data['fixed_price'], 2)
                    : null,
                'discount_value' => $data['pricing_mode'] !== MenuBundle::PRICING_FIXED
                    ? round((float) $data['discount_value'], 2)
                    : null,
                'is_active' => $request->boolean('is_active', true),
                'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
            ],
            'items' => $items,
        ];
    }

    private function payload(MenuBundle $bundle): array
    {
        return [
            'id' => (int) $bundle->id,
            'name_ar' => (string) $bundle->name_ar,
            'name_en' => $bundle->name_en,
            'pricing_mode' => (string) $bundle->pricing_mode,
            'fixed_price' => $bundle->fixed_price !== null ? (float) $bundle->fixed_price : null,
            'discount_value' => $bundle->discount_value !== null ? (float) $bundle->discount_value : null,
            'components_subtotal' => $bundle->componentsSubtotal(),
            'price' => $bundle->price(),
            'is_active' => (bool) $bundle->is_active,
            'sort_order' => (int) $bundle->sort_order,
            'items' => $bundle->items->map(fn ($i) => [
                'menu_item_id' => (int) $i->menu_item_id,
                'name' => (string) ($i->menuItem?->name_ar ?: ('#' . $i->menu_item_id)),
                'unit_price' => (float) ($i->menuItem?->base_price ?? 0),
                'qty' => (int) $i->qty,
            ])->values(),
        ];
    }
}
