<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\MenuDetailProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * «قيم موديلات الكتالوج» — المالك، 2026-10-02. For one detail kind (mobiles,
 * computers…), every catalog model on its shelf as a row and the kind's own
 * fields as columns, filled in one screen: a choice picked, a figure typed.
 *
 * Only the fields the MODEL answers are here. A field flagged «لكل وحدة» in
 * «أشكال المنيو» (a car's year, mileage, colour) is stated by the merchant for
 * each unit, so a model has nothing to say about it.
 *
 * Values land in catalog_product_attribute_values — the table the product page,
 * the storefront card and the cross-shop search already read.
 */
class CatalogModelValuesController extends Controller
{
    private const PER_PAGE = 40;

    public function index(Request $request)
    {
        $profiles = MenuDetailProfile::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $allFields = MenuDetailProfile::fieldsFor($profiles->pluck('id')->all());

        // A kind with every field per-unit (cars) has nothing for a model to say.
        $answered = fn ($p) => collect($allFields[$p->id] ?? [])->where('per_item', false)->values()->all();

        $profile = $profiles->firstWhere('code', (string) $request->get('profile'))
            ?: ($profiles->first(fn ($p) => $answered($p) !== []) ?: $profiles->first());

        $fields = $profile ? $answered($profile) : [];
        $optionIds = $profile ? $this->optionIds($profile) : [];
        $shelfIds = $profile ? $this->shelfIds($optionIds) : [];
        $shelves = $shelfIds === [] ? collect() : DB::table('product_category_children')->whereIn('id', $shelfIds)->orderBy('name_ar')->get(['id', 'name_ar']);

        $search = trim((string) $request->get('q', ''));
        $shelf = (int) $request->get('shelf', 0);
        $missing = $request->boolean('missing');

        $products = collect();
        $values = [];

        if ($profile && ($optionIds !== [] || $shelfIds !== [])) {
            $fieldIds = array_column($fields, 'id');
            $filed = $optionIds !== [] && DB::table('catalog_products')->whereIn('line_option_id', $optionIds)->whereNull('deleted_at')->exists();

            $query = DB::table('catalog_products as p')
                ->leftJoin('catalog_brands as b', 'b.id', '=', 'p.brand_id')
                ->whereNull('p.deleted_at')
                ->where('p.is_active', 1)
                // Models filed under the kind's branches (a phone under «موبايل»)
                // when any are; else the whole shelf — a shelf that holds the
                // chargers beside the phones would ask a charger for its RAM.
                ->where(function ($w) use ($optionIds, $shelfIds, $filed) {
                    $w->whereIn('p.line_option_id', $optionIds ?: [0]);
                    if (! $filed) {
                        $w->orWhereIn('p.product_category_child_id', $shelfIds ?: [0]);
                    }
                })
                ->when($shelf > 0, fn ($q) => $q->where('p.product_category_child_id', $shelf))
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('p.name_ar', 'like', "%{$search}%")->orWhere('p.name_en', 'like', "%{$search}%")))
                ->when($missing && $fieldIds !== [], fn ($q) => $q->whereRaw(
                    '(SELECT COUNT(DISTINCT v.attribute_id) FROM catalog_product_attribute_values v WHERE v.product_id = p.id AND v.attribute_id IN (' . implode(',', array_map('intval', $fieldIds)) . ')) < ?',
                    [count($fieldIds)]
                ))
                ->orderBy('b.name_en')->orderBy('p.series')->orderBy('p.name_en');

            $products = $query->paginate(self::PER_PAGE, ['p.id', 'p.name_ar', 'p.name_en', 'p.series', 'b.name_ar as brand'])->withQueryString();

            if ($fieldIds !== []) {
                foreach (DB::table('catalog_product_attribute_values')
                    ->whereIn('product_id', $products->pluck('id')->all())->whereIn('attribute_id', $fieldIds)
                    ->get(['product_id', 'attribute_id', 'option_id', 'value_number', 'value_text_ar', 'value_text_en']) as $v) {
                    $values[(int) $v->product_id][(int) $v->attribute_id] = $v;
                }
            }
        }

        return view('admin-v2.catalog-model-values.index', compact('profiles', 'profile', 'fields', 'products', 'values', 'shelves', 'search', 'shelf', 'missing', 'allFields'));
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'profile' => ['required', 'string', 'exists:menu_detail_profiles,code'],
            'values' => ['nullable', 'array'],
        ]);

        $profile = MenuDetailProfile::query()->where('code', $data['profile'])->firstOrFail();
        $fields = collect(MenuDetailProfile::fieldsFor([$profile->id])[$profile->id] ?? [])->where('per_item', false)->keyBy('id');
        $units = DB::table('catalog_attributes')->whereIn('id', $fields->keys()->all())->pluck('unit_id', 'id');

        // Only models of this kind may be written.
        $allowed = DB::table('catalog_products')->whereIn('id', array_map('intval', array_keys($data['values'] ?? [])))->whereNull('deleted_at')->pluck('id')->flip();

        $errors = [];
        $writes = [];

        foreach ($data['values'] ?? [] as $productId => $row) {
            if (! $allowed->has((int) $productId)) {
                continue;
            }

            foreach ((array) $row as $attributeId => $raw) {
                $field = $fields->get((int) $attributeId);
                if (! $field) {
                    continue;
                }

                $raw = is_string($raw) ? trim($raw) : $raw;
                if ($raw === null || $raw === '') {
                    $writes[] = [(int) $productId, (int) $attributeId, null];
                    continue;
                }

                if ($field['data_type'] === 'select') {
                    $ok = DB::table('catalog_attribute_options')->where('id', (int) $raw)->where('attribute_id', (int) $attributeId)->where('is_active', 1)->exists();
                    $ok ? $writes[] = [(int) $productId, (int) $attributeId, ['option_id' => (int) $raw]] : $errors[] = "{$field['name']} #{$productId}";
                } elseif ($field['data_type'] === 'number') {
                    $number = str_replace(['٫', ','], ['.', ''], (string) $raw);
                    is_numeric($number) && (float) $number >= 0
                        ? $writes[] = [(int) $productId, (int) $attributeId, ['value_number' => (float) $number]]
                        : $errors[] = "{$field['name']} #{$productId}";
                } else {
                    $text = mb_substr((string) $raw, 0, 190);
                    $writes[] = [(int) $productId, (int) $attributeId, ['value_text_ar' => $text, 'value_text_en' => $text]];
                }
            }
        }

        if ($errors !== []) {
            return back()->withInput()->withErrors(['values' => __('قيم غير صالحة: :list', ['list' => implode('، ', array_slice($errors, 0, 8))])]);
        }

        $now = now();
        DB::transaction(function () use ($writes, $units, $now) {
            foreach ($writes as [$productId, $attributeId, $row]) {
                DB::table('catalog_product_attribute_values')->where('product_id', $productId)->where('attribute_id', $attributeId)->delete();

                if ($row !== null) {
                    DB::table('catalog_product_attribute_values')->insert($row + [
                        'product_id' => $productId, 'attribute_id' => $attributeId,
                        'unit_id' => $units[$attributeId] ?? null, 'sort_order' => 0,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        });

        return back()->with('success', __('حُفظت قيم :n خانة.', ['n' => count($writes)]));
    }

    /** @return list<int> the branches of every group this kind is assigned to */
    private function optionIds(MenuDetailProfile $profile): array
    {
        return DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.menu_detail_profile_id', $profile->id)->pluck('o.id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * The catalog shelves the kind's trades sell from: through the businesses
     * that carry its branches and what their retail scope allows — so «سيارات»
     * finds the car shelf although no model is filed under a branch yet.
     *
     * @param  list<int>  $optionIds
     * @return list<int>
     */
    private function shelfIds(array $optionIds): array
    {
        if ($optionIds === []) {
            return [];
        }

        $childIds = DB::table('category_child_option')->whereIn('option_id', $optionIds)->distinct()->pluck('child_id')->all();
        $retail = (int) DB::table('platform_services')->where('key', 'retail')->value('id');

        $types = DB::table('category_service_configs')
            ->where('platform_service_id', $retail)->whereIn('child_id', $childIds)
            ->pluck('config')
            ->flatMap(fn ($json) => (array) (json_decode((string) $json, true)['allowed_item_types'] ?? []))
            ->unique()->values()->all();

        return $types === [] ? [] : DB::table('product_category_children')->whereIn('slug', $types)->whereNull('deleted_at')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
