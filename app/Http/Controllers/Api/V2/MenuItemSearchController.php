<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\MenuDetailProfile;
use App\Models\MenuItem;
use App\Services\Catalog\ProductSpecs;
use App\Services\Menu\MenuItemAttributes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * «الاستخدام الأساسي: ألاقي لاب توب بمواصفات محددة وأعرف المحلات اللي عندها
 * المنتج ده وأقارن الأسعار» — المالك، 2026-10-02.
 *
 * Search across shops by a detail kind's fields: «سيارات ٢٠٢٠–٢٠٢٣، أوتوماتيك،
 * أقل من ٦٠ ألف كم»، «لاب توب Core i7، رام ١٦». A field is matched on the
 * unit's own value (a car's year) or, where the unit states none, on its
 * catalog master's (a laptop's processor). Pass `catalog_product_id` to see
 * every shop that sells one exact product, cheapest first — the comparison.
 *
 * Public (no auth), like the rest of discovery.
 */
final class MenuItemSearchController extends Controller
{
    /** GET /api/v2/discovery/menu-items/search */
    public function index(Request $request)
    {
        $data = $request->validate([
            'profile' => ['nullable', 'string', 'max:50'],
            'q' => ['nullable', 'string', 'max:100'],
            'line_option_id' => ['nullable', 'integer', 'min:1'],
            'brand_id' => ['nullable', 'integer', 'min:1'],
            'catalog_product_id' => ['nullable', 'integer', 'min:1'],
            'governorate_id' => ['nullable', 'integer', 'min:1'],
            'city_id' => ['nullable', 'integer', 'min:1'],
            'attr' => ['nullable', 'array'],
            // «غرف نوم — قسط»: only what can be bought on instalments.
            'installments' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:price_asc,price_desc,newest'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $english = app()->getLocale() === 'en';

        $profile = ! empty($data['profile'])
            ? MenuDetailProfile::query()->where('code', $data['profile'])->where('is_active', true)->first()
            : null;

        if (! empty($data['profile']) && ! $profile) {
            return response()->json(['success' => false, 'message' => __('نوع التفاصيل غير موجود.')], 404);
        }

        $fields = $profile ? (MenuDetailProfile::fieldsFor([$profile->id], $english)[$profile->id] ?? []) : [];
        $morph = (new MenuItem)->getMorphClass();

        $base = DB::table('menu_items')
            ->join('users as biz', 'biz.id', '=', 'menu_items.business_id')
            ->where('biz.type', 'business')
            ->where('menu_items.is_active', true)
            ->when(! empty($data['catalog_product_id']), fn ($q) => $q->where('menu_items.catalog_product_id', (int) $data['catalog_product_id']))
            ->when(! empty($data['brand_id']), fn ($q) => $q->whereIn('menu_items.catalog_product_id',
                DB::table('catalog_products')->where('brand_id', (int) $data['brand_id'])->select('id')))
            ->when(! empty($data['governorate_id']), fn ($q) => $q->where('biz.governorate_id', (int) $data['governorate_id']))
            ->when(! empty($data['city_id']), fn ($q) => $q->where('biz.city_id', (int) $data['city_id']))
            ->when(trim((string) ($data['q'] ?? '')) !== '', function ($q) use ($data) {
                $like = '%' . trim($data['q']) . '%';
                $q->where(fn ($w) => $w->where('menu_items.name_ar', 'like', $like)->orWhere('menu_items.name_en', 'like', $like));
            });

        if ($request->boolean('installments')) {
            $base->whereExists(fn ($sub) => $sub->select(DB::raw(1))->from('menu_item_payment_plans as pp')
                ->whereColumn('pp.menu_item_id', 'menu_items.id')->where('pp.is_active', 1)
                ->whereExists(fn ($kind) => $kind->select(DB::raw(1))->from('offering_options as oo')
                    ->join('options as o', 'o.id', '=', 'oo.option_id')->join('option_groups as g', 'g.id', '=', 'o.group_id')
                    ->join('menu_detail_types as t', 't.code', '=', 'g.detail_type')
                    ->whereColumn('oo.offering_id', 'menu_items.id')->where('oo.offering_type', $morph)->where('oo.role', 'line')
                    ->where('t.allows_payment_plans', 1)));
        }

        // The kind: items filed under a branch of a group that has it.
        if ($profile || ! empty($data['line_option_id'])) {
            $base->whereExists(function ($sub) use ($profile, $data, $morph) {
                $sub->select(DB::raw(1))->from('offering_options as oo')
                    ->whereColumn('oo.offering_id', 'menu_items.id')
                    ->where('oo.offering_type', $morph)
                    ->where('oo.role', 'line')
                    ->when(! empty($data['line_option_id']), fn ($q) => $q->where('oo.option_id', (int) $data['line_option_id']))
                    ->when($profile, fn ($q) => $q->whereIn('oo.option_id',
                        DB::table('options as op')->join('option_groups as og', 'og.id', '=', 'op.group_id')
                            ->where('og.menu_detail_profile_id', $profile->id)->select('op.id')));
            });
        }

        // The facets count what is on offer BEFORE the field filters narrow it,
        // so a customer sees what choosing another colour would give.
        $facetIds = (clone $base)->limit(5000)->pluck('menu_items.id')->all();

        $filterable = collect($fields)->where('is_filterable', true)->keyBy('code');
        foreach ((array) ($data['attr'] ?? []) as $key => $value) {
            [$code, $bound] = str_ends_with((string) $key, '_min') ? [substr($key, 0, -4), 'min']
                : (str_ends_with((string) $key, '_max') ? [substr($key, 0, -4), 'max'] : [(string) $key, 'eq']);

            $field = $filterable->get($code);
            if (! $field || $value === null || $value === '' || is_array($value) && $value === []) {
                continue;
            }

            $this->applyFieldFilter($base, (int) $field['id'], $field['data_type'], $bound, $value);
        }

        $sort = $data['sort'] ?? 'price_asc';
        $base->when($sort === 'price_asc', fn ($q) => $q->orderBy('menu_items.base_price'))
            ->when($sort === 'price_desc', fn ($q) => $q->orderByDesc('menu_items.base_price'))
            ->when($sort === 'newest', fn ($q) => $q->orderByDesc('menu_items.id'))
            ->orderBy('menu_items.id');

        $page = $base->paginate(
            (int) ($data['per_page'] ?? 20),
            ['menu_items.id', 'menu_items.business_id', 'menu_items.catalog_product_id', 'menu_items.name_ar', 'menu_items.name_en',
                'menu_items.base_price', 'menu_items.image', 'menu_items.available_quantity', 'menu_items.description_ar', 'menu_items.description_en',
                'biz.name as business_name', 'biz.logo as business_logo', 'biz.governorate_id', 'biz.city_id']
        );

        $ids = collect($page->items())->pluck('id')->all();
        $products = collect($page->items())->pluck('catalog_product_id')->filter()->unique()->values()->all();

        $attrs = app(MenuItemAttributes::class);
        $attrs->preload($ids);
        $masterSpecs = app(ProductSpecs::class)->forProducts($products);
        $images = DB::table('catalog_products')->whereIn('id', $products)->pluck('main_image', 'id');

        $cardCodes = collect($fields)->where('show_on_card', true)->pluck('code')->all();
        $installments = app(\App\Services\Menu\PaymentPlans::class)->cardLines(collect($page->items())->mapWithKeys(fn ($r) => [(int) $r->id => (float) $r->base_price])->all());

        $items = collect($page->items())->map(function ($r) use ($attrs, $masterSpecs, $images, $english, $cardCodes, $installments) {
            $specs = $attrs->mergeIntoSpecs($masterSpecs[(int) $r->catalog_product_id] ?? [], (int) $r->id);
            $byCode = collect($specs)->keyBy('code');
            // «الوصف» when the kind ticked it for the card.
            $description = (string) ($english ? ($r->description_en ?: $r->description_ar) : ($r->description_ar ?: $r->description_en));
            if ($description !== '') {
                $byCode['description'] = ['code' => 'description', 'value' => \Illuminate\Support\Str::limit($description, 90)];
            }
            $summary = collect($cardCodes)->map(fn ($c) => $byCode[$c]['value'] ?? null)->filter()->implode(' · ');

            return [
                'id' => (int) $r->id,
                'name' => (string) ($english ? ($r->name_en ?: $r->name_ar) : ($r->name_ar ?: $r->name_en)),
                'price' => (float) $r->base_price,
                // «تقسيط من … شهريًا» when it can be bought on instalments.
                'installment' => $installments[(int) $r->id] ?? null,
                'image' => $r->image ?: ($images[$r->catalog_product_id] ?? null),
                'catalog_product_id' => $r->catalog_product_id ? (int) $r->catalog_product_id : null,
                'available_quantity' => $r->available_quantity !== null ? (int) $r->available_quantity : null,
                'specs' => $specs,
                'summary' => $summary !== '' ? $summary : null,
                'business' => [
                    'id' => (int) $r->business_id,
                    'name' => (string) $r->business_name,
                    'logo' => $r->business_logo,
                    'governorate_id' => $r->governorate_id ? (int) $r->governorate_id : null,
                    'city_id' => $r->city_id ? (int) $r->city_id : null,
                ],
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'profile' => $profile ? ['code' => $profile->code, 'name' => $profile->label($english), 'fields' => $fields] : null,
                'facets' => $this->facets($filterable->all(), $facetIds),
                // Inside `data`, so the app's client — which unwraps it — sees paging.
                'meta' => [
                    'page' => $page->currentPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                    'last_page' => $page->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * GET /api/v2/discovery/menu-items/kinds — the detail kinds a customer can
     * search («سيارات»، «كمبيوتر ولاب توب»…) with only the fields that are
     * filters for that kind, so the app draws the filter sheet from data.
     * Only kinds some shop actually sells are listed.
     */
    public function kinds()
    {
        $english = app()->getLocale() === 'en';
        $morph = (new MenuItem)->getMorphClass();

        $profiles = MenuDetailProfile::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $fields = MenuDetailProfile::fieldsFor($profiles->pluck('id')->all(), $english);

        $counts = DB::table('menu_items')
            ->join('offering_options as oo', function ($j) use ($morph) {
                $j->on('oo.offering_id', '=', 'menu_items.id')->where('oo.offering_type', $morph)->where('oo.role', 'line');
            })
            ->join('options as op', 'op.id', '=', 'oo.option_id')
            ->join('option_groups as og', 'og.id', '=', 'op.group_id')
            ->where('menu_items.is_active', true)
            ->whereNotNull('og.menu_detail_profile_id')
            ->groupBy('og.menu_detail_profile_id')
            ->selectRaw('og.menu_detail_profile_id as pid, COUNT(DISTINCT menu_items.id) as n')
            ->pluck('n', 'pid');

        return response()->json(['success' => true, 'data' => ['kinds' => $profiles
            ->filter(fn ($p) => ($counts[$p->id] ?? 0) > 0)
            ->map(fn ($p) => [
                'code' => $p->code,
                'name' => $p->label($english),
                'icon' => $p->icon,
                'count' => (int) $counts[$p->id],
                'fields' => collect($fields[$p->id] ?? [])->where('is_filterable', true)->values()->all(),
            ])->values()]]);
    }

    /** One field, matched on the unit's own value, else on its catalog master's. */
    private function applyFieldFilter(QueryBuilder $query, int $attributeId, string $type, string $bound, mixed $value): void
    {
        $unitMatches = fn (QueryBuilder $q) => $this->condition($q, 'v', $type, $bound, $value, unit: true);
        $masterMatches = fn (QueryBuilder $q) => $this->condition($q, 'm', $type, $bound, $value, unit: false);

        $query->where(function ($w) use ($attributeId, $unitMatches, $masterMatches) {
            $w->whereExists(function ($q) use ($attributeId, $unitMatches) {
                $q->select(DB::raw(1))->from('menu_item_attribute_values as v')
                    ->whereColumn('v.menu_item_id', 'menu_items.id')->where('v.attribute_id', $attributeId);
                $unitMatches($q);
            })->orWhere(function ($o) use ($attributeId, $masterMatches) {
                $o->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('menu_item_attribute_values as v0')
                    ->whereColumn('v0.menu_item_id', 'menu_items.id')->where('v0.attribute_id', $attributeId))
                    ->whereExists(function ($q) use ($attributeId, $masterMatches) {
                        $q->select(DB::raw(1))->from('catalog_product_attribute_values as m')
                            ->whereColumn('m.product_id', 'menu_items.catalog_product_id')->where('m.attribute_id', $attributeId);
                        $masterMatches($q);
                    });
            });
        });
    }

    private function condition(QueryBuilder $q, string $alias, string $type, string $bound, mixed $value, bool $unit): void
    {
        if ($type === 'number') {
            $number = str_replace(['٫', ','], ['.', ''], (string) $value);
            if (is_numeric($number)) {
                $q->where("{$alias}.value_number", ['min' => '>=', 'max' => '<=', 'eq' => '='][$bound], (float) $number);
            } else {
                $q->whereRaw('1 = 0');
            }

            return;
        }

        if ($type === 'select') {
            $ids = array_values(array_filter(array_map('intval', is_array($value) ? $value : explode(',', (string) $value))));
            $ids === [] ? $q->whereRaw('1 = 0') : $q->whereIn("{$alias}.option_id", $ids);

            return;
        }

        $like = '%' . trim((string) $value) . '%';
        $unit
            ? $q->where("{$alias}.value_text", 'like', $like)
            : $q->where(fn ($w) => $w->where("{$alias}.value_text_ar", 'like', $like)->orWhere("{$alias}.value_text_en", 'like', $like));
    }

    /**
     * What the matching units offer on each filterable field: a number's range,
     * a select's options with how many units carry each.
     *
     * @param  array<string,array<string,mixed>>  $fields
     * @param  list<int>  $ids
     * @return array<string,array<string,mixed>>
     */
    private function facets(array $fields, array $ids): array
    {
        if ($ids === [] || $fields === []) {
            return [];
        }

        $out = [];
        $english = app()->getLocale() === 'en';

        foreach ($fields as $code => $field) {
            if (! in_array($field['data_type'], ['number', 'select'], true)) {
                continue;
            }

            $attributeId = (int) $field['id'];

            // The unit's value, or its master's where it states none.
            $rows = DB::table('menu_items as mi')
                ->leftJoin('menu_item_attribute_values as v', fn ($j) => $j->on('v.menu_item_id', '=', 'mi.id')->where('v.attribute_id', $attributeId))
                ->leftJoin('catalog_product_attribute_values as m', fn ($j) => $j->on('m.product_id', '=', 'mi.catalog_product_id')->where('m.attribute_id', $attributeId))
                ->whereIn('mi.id', $ids)
                ->get(['mi.id', 'v.id as v_id', 'v.value_number as v_num', 'v.option_id as v_opt', 'm.value_number as m_num', 'm.option_id as m_opt']);

            if ($field['data_type'] === 'number') {
                $values = $rows->map(fn ($r) => $r->v_id ? $r->v_num : $r->m_num)->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v);
                if ($values->isNotEmpty()) {
                    $out[$code] = ['type' => 'number', 'min' => $values->min(), 'max' => $values->max()];
                }
                continue;
            }

            $counts = $rows->map(fn ($r) => $r->v_id ? $r->v_opt : $r->m_opt)->filter()->countBy();
            if ($counts->isEmpty()) {
                continue;
            }

            $labels = DB::table('catalog_attribute_options')->whereIn('id', $counts->keys()->all())->get(['id', 'value_ar', 'value_en'])->keyBy('id');
            $label = fn ($o) => $o ? (string) (($english ? ($o->value_en ?: $o->value_ar) : ($o->value_ar ?: $o->value_en))) : '';
            $out[$code] = [
                'type' => 'select',
                'options' => $counts->map(fn ($n, $id) => [
                    'id' => (int) $id,
                    'name' => $label($labels[$id] ?? null),
                    'count' => (int) $n,
                ])->sortByDesc('count')->values()->all(),
            ];
        }

        return $out;
    }
}
