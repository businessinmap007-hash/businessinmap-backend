<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\DB;

/**
 * The spec table of a catalog master, ready for display: brand first, then
 * every attribute value the product carries, in attribute order. One query
 * pair for any number of products, so a list can carry specs without N+1.
 */
final class ProductSpecs
{
    /**
     * @param  array<int,int>  $productIds
     * @return array<int, list<array{code: string, name: string, value: string}>>  keyed by product id
     */
    public function forProducts(array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if ($productIds === []) {
            return [];
        }

        $english = app()->getLocale() === 'en';
        $pick = fn ($ar, $en) => trim((string) ($english ? ($en ?: $ar) : ($ar ?: $en)));

        $out = array_fill_keys($productIds, []);

        $brands = DB::table('catalog_products as p')
            ->join('catalog_brands as b', 'b.id', '=', 'p.brand_id')
            ->whereIn('p.id', $productIds)
            ->get(['p.id', 'b.name_ar', 'b.name_en']);

        foreach ($brands as $b) {
            $value = $pick($b->name_ar, $b->name_en);
            if ($value !== '') {
                $out[(int) $b->id][] = ['code' => 'brand', 'name' => $english ? 'Brand' : 'العلامة التجارية', 'value' => $value];
            }
        }

        $rows = DB::table('catalog_product_attribute_values as v')
            ->join('catalog_attributes as a', 'a.id', '=', 'v.attribute_id')
            ->leftJoin('catalog_units as u', 'u.id', '=', 'v.unit_id')
            ->leftJoin('catalog_attribute_options as o', 'o.id', '=', 'v.option_id')
            ->whereIn('v.product_id', $productIds)
            ->orderBy('a.sort_order')
            ->orderBy('a.id')
            ->get([
                'v.product_id', 'a.code', 'a.name_ar', 'a.name_en', 'a.data_type',
                'v.value_number', 'v.value_text_ar', 'v.value_text_en', 'v.value_bool', 'v.value_boolean',
                'u.name_ar as unit_ar', 'u.name_en as unit_en',
                'o.value_ar as option_ar', 'o.value_en as option_en',
            ]);

        foreach ($rows as $r) {
            $value = $this->value($r, $pick);
            if ($value === '') {
                continue;
            }

            $out[(int) $r->product_id][] = ['code' => (string) $r->code, 'name' => $pick($r->name_ar, $r->name_en), 'value' => $value];
        }

        return $out;
    }

    private function value(object $r, callable $pick): string
    {
        if ($r->option_ar || $r->option_en) {
            return $pick($r->option_ar, $r->option_en);
        }

        if ($r->value_number !== null) {
            $number = rtrim(rtrim(number_format((float) $r->value_number, 4, '.', ''), '0'), '.');
            $unit = $pick($r->unit_ar, $r->unit_en);

            return trim($number . ' ' . $unit);
        }

        $text = $pick($r->value_text_ar, $r->value_text_en);
        if ($text !== '') {
            return $text;
        }

        $bool = $r->value_boolean ?? $r->value_bool;

        return $bool === null ? '' : (app()->getLocale() === 'en' ? ($bool ? 'Yes' : 'No') : ($bool ? 'نعم' : 'لا'));
    }
}
