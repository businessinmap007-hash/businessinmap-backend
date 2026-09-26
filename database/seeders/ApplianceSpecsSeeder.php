<?php

namespace Database\Seeders;

use App\Services\Catalog\ApplianceSpecExtractor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Gives every electrical-appliance catalog master (product category child
 * «أجهزة كهربائية») a structured spec table — type, operation, capacity,
 * power… — read off its own name. Idempotent: attributes/units are created
 * once, and a product's value is rewritten only for the codes it yields, so a
 * spec a curator added by hand under another attribute is never touched.
 */
class ApplianceSpecsSeeder extends Seeder
{
    private const CATEGORY_CHILD_SLUG = 'home_appliances';

    public function run(): void
    {
        $now = now();
        $extractor = new ApplianceSpecExtractor();

        foreach (ApplianceSpecExtractor::UNITS as $code => [$ar, $en]) {
            DB::table('catalog_units')->updateOrInsert(
                ['code' => $code],
                ['name_ar' => $ar, 'name_en' => $en, 'unit_type' => 'other', 'is_active' => 1, 'sort_order' => 100, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $unitIds = DB::table('catalog_units')
            ->whereIn('code', array_merge(array_keys(ApplianceSpecExtractor::UNITS), ['kg', 'liter']))
            ->pluck('id', 'code')
            ->map(fn ($id) => (int) $id)
            ->all();

        $attributeIds = [];
        foreach (ApplianceSpecExtractor::ATTRIBUTES as $code => [$ar, $en, $type, $unit, $sort]) {
            DB::table('catalog_attributes')->updateOrInsert(
                ['code' => $code],
                [
                    'name_ar' => $ar, 'name_en' => $en, 'data_type' => $type,
                    'unit_id' => $unit ? $unitIds[$unit] : null,
                    'is_filterable' => 1, 'is_variant_axis' => 0, 'is_required' => 0,
                    'sort_order' => $sort, 'created_at' => $now, 'updated_at' => $now,
                ]
            );
            $attributeIds[$code] = (int) DB::table('catalog_attributes')->where('code', $code)->value('id');
        }

        $childId = DB::table('product_category_children')->where('slug', self::CATEGORY_CHILD_SLUG)->value('id');
        if (! $childId) {
            return;
        }

        $products = DB::table('catalog_products')
            ->where('product_category_child_id', $childId)
            ->whereNull('deleted_at')
            ->get(['id', 'name_en']);

        foreach ($products as $product) {
            foreach ($extractor->extract((string) $product->name_en) as $code => $value) {
                $unit = ApplianceSpecExtractor::ATTRIBUTES[$code][3];

                DB::table('catalog_product_attribute_values')->updateOrInsert(
                    ['product_id' => $product->id, 'attribute_id' => $attributeIds[$code], 'option_id' => null],
                    [
                        'value_number' => $value['number'] ?? null,
                        'value_text_ar' => $value['ar'] ?? null,
                        'value_text_en' => $value['en'] ?? null,
                        'unit_id' => $unit ? $unitIds[$unit] : null,
                        'sort_order' => ApplianceSpecExtractor::ATTRIBUTES[$code][4],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }
}
