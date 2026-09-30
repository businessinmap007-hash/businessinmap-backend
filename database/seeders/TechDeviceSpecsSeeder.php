<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * «كتالوج تفصيلي» — the second detailed-catalog vertical after appliances:
 * laptops/computers (product_category_children.slug=computers_laptops) and
 * phones (mobiles_accessories). Unlike appliance names, these carry no
 * parseable spec in the name itself («HP ProBook Laptop 15.6 inch» says
 * nothing about its CPU or RAM) — so this seeder is a curated lookup by the
 * product's own name_en, not a text extractor. Idempotent: `updateOrInsert`
 * per (product, attribute).
 *
 * Plain accessories inside these children (chargers, cases, printers,
 * monitors) are left without a spec row on purpose — not every product under
 * a "detailed" child has details; the product page just shows what exists.
 */
class TechDeviceSpecsSeeder extends Seeder
{
    /** name_en => [processor, ram_gb, storage, screen_inches, os, gpu?] */
    private const LAPTOPS = [
        'HP ProBook Laptop 15.6 inch' => ['Intel Core i5-1235U', 8, '512GB SSD', 15.6, 'Windows 11'],
        'HP Pavilion Laptop 14 inch' => ['Intel Core i5-1335U', 8, '512GB SSD', 14, 'Windows 11'],
        'Dell Inspiron Laptop 15 inch' => ['Intel Core i5-1235U', 8, '512GB SSD', 15.6, 'Windows 11'],
        'Dell Latitude Laptop 14 inch' => ['Intel Core i7-1355U', 16, '512GB SSD', 14, 'Windows 11'],
        'Lenovo IdeaPad Laptop 15.6 inch' => ['Intel Core i3-1215U', 8, '256GB SSD', 15.6, 'Windows 11'],
        'Lenovo ThinkPad Laptop 14 inch' => ['Intel Core i7-1355U', 16, '512GB SSD', 14, 'Windows 11'],
        'Asus VivoBook Laptop 15.6 inch' => ['Intel Core i5-1335U', 8, '512GB SSD', 15.6, 'Windows 11'],
        'Asus TUF Gaming Laptop 15.6 inch' => ['Intel Core i7-13620H', 16, '512GB SSD', 15.6, 'Windows 11', 'NVIDIA RTX 4050'],
        'Acer Aspire Laptop 15.6 inch' => ['Intel Core i3-1215U', 8, '256GB SSD', 15.6, 'Windows 11'],
        'Apple MacBook Air 13 inch' => ['Apple M2', 8, '256GB SSD', 13.3, 'macOS'],
        'Apple iPad 10.9 inch' => ['Apple A14 Bionic', 4, '64GB', 10.9, 'iPadOS'],

        // Added by TechDeviceLaptopsExpansionSeeder — one gaming pick, one
        // business pick, verified against each model's real published spec
        // sheet. Starts «منيو مواصفات 2» (computer/laptop).
        'Lenovo Legion 5 15 inch' => ['AMD Ryzen 7 5800H', 16, '512GB SSD', 15.6, 'Windows 11', 'NVIDIA RTX 3060'],
        'HP EliteBook 840 G9 14 inch' => ['Intel Core i5-1240P', 16, '512GB SSD', 14, 'Windows 11'],
    ];

    /** name_en => [processor, ram_gb, storage, screen_inches, os, gpu?] */
    private const PHONES = [
        'Samsung Galaxy A15 128GB' => ['MediaTek Helio G99', 4, '128GB', 6.5, 'Android 14'],
        'Samsung Galaxy A54 256GB' => ['Exynos 1380', 8, '256GB', 6.4, 'Android 14'],
        'Samsung Galaxy S23 256GB' => ['Snapdragon 8 Gen 2', 8, '256GB', 6.1, 'Android 14'],
        'Xiaomi Redmi Note 13 128GB' => ['Snapdragon 685', 6, '128GB', 6.67, 'Android 13'],
        'Xiaomi Redmi 13C 128GB' => ['MediaTek Helio G85', 4, '128GB', 6.74, 'Android 13'],
        'Oppo Reno 11 256GB' => ['MediaTek Dimensity 7050', 8, '256GB', 6.7, 'Android 14'],
        'Oppo A78 128GB' => ['Snapdragon 680', 8, '128GB', 6.56, 'Android 13'],
        'Realme C55 128GB' => ['MediaTek Helio G88', 6, '128GB', 6.72, 'Android 13'],
        'Infinix Hot 40 128GB' => ['MediaTek Helio G88', 8, '128GB', 6.78, 'Android 13'],
        'Infinix Note 30 256GB' => ['MediaTek Helio G99', 8, '256GB', 6.78, 'Android 13'],
        'Tecno Spark 20 128GB' => ['MediaTek Helio G85', 8, '128GB', 6.6, 'Android 13'],
        'Honor X9b 256GB' => ['Snapdragon 6 Gen 1', 8, '256GB', 6.78, 'Android 13'],
        'Apple iPhone 15 128GB' => ['Apple A16 Bionic', 6, '128GB', 6.1, 'iOS 17'],
        'Apple iPhone 13 128GB' => ['Apple A15 Bionic', 4, '128GB', 6.1, 'iOS 17'],

        // Added by TechDevicePhonesExpansionSeeder's 7 new products — one
        // current/popular tier-up pick per existing brand, verified against
        // each model's real published spec sheet (gsmarena/devicespecifications).
        'Samsung Galaxy S24 Ultra 256GB' => ['Snapdragon 8 Gen 3', 12, '256GB', 6.8, 'Android 14', 'Adreno 750'],
        'Apple iPhone 15 Pro Max 256GB' => ['Apple A17 Pro', 8, '256GB', 6.7, 'iOS 17'],
        'Xiaomi Redmi Note 13 Pro 256GB' => ['Snapdragon 7s Gen 2', 8, '256GB', 6.67, 'Android 13'],
        'Xiaomi Poco X6 Pro 256GB' => ['MediaTek Dimensity 8300', 8, '256GB', 6.67, 'Android 14', 'Mali-G615 MC6'],
        'Realme 12 128GB' => ['MediaTek Dimensity 6100+', 8, '128GB', 6.67, 'Android 14'],
        'Infinix Note 40 Pro 256GB' => ['MediaTek Helio G99', 8, '256GB', 6.78, 'Android 14'],
        'Tecno Camon 20 256GB' => ['MediaTek Helio G85', 8, '256GB', 6.67, 'Android 13', 'Mali-G52 MC2'],
    ];

    public function run(): void
    {
        $now = now();

        DB::table('catalog_units')->updateOrInsert(
            ['code' => 'gb'],
            ['name_ar' => 'جيجا', 'name_en' => 'GB', 'unit_type' => 'other', 'is_active' => 1, 'sort_order' => 100, 'created_at' => $now, 'updated_at' => $now]
        );
        DB::table('catalog_units')->updateOrInsert(
            ['code' => 'inch'],
            ['name_ar' => 'بوصة', 'name_en' => 'inch', 'unit_type' => 'other', 'is_active' => 1, 'sort_order' => 100, 'created_at' => $now, 'updated_at' => $now]
        );
        $unitIds = DB::table('catalog_units')->whereIn('code', ['gb', 'inch'])->pluck('id', 'code')->map(fn ($id) => (int) $id)->all();

        $attributes = [
            'processor' => ['المعالج', 'Processor', 'text', null, 200],
            'ram_gb' => ['الرام (RAM)', 'RAM', 'number', 'gb', 201],
            'storage' => ['سعة التخزين', 'Storage', 'text', null, 202],
            'screen_inches' => ['مقاس الشاشة', 'Screen size', 'number', 'inch', 203],
            'os' => ['نظام التشغيل', 'Operating system', 'text', null, 204],
            'gpu' => ['كارت الشاشة', 'Graphics', 'text', null, 205],
        ];

        $attributeIds = [];
        foreach ($attributes as $code => [$ar, $en, $type, $unit, $sort]) {
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

        foreach ([72 => self::LAPTOPS, 73 => self::PHONES] as $childId => $rows) {
            $products = DB::table('catalog_products')
                ->where('product_category_child_id', $childId)
                ->whereNull('deleted_at')
                ->whereIn('name_en', array_keys($rows))
                ->pluck('id', 'name_en');

            foreach ($rows as $nameEn => $spec) {
                $productId = $products[$nameEn] ?? null;
                if (! $productId) {
                    continue;
                }

                [$processor, $ramGb, $storage, $screenInches, $os] = $spec;
                $gpu = $spec[5] ?? null;

                $write = function (string $code, ?float $number, ?string $text) use ($productId, $attributeIds, $unitIds, $now) {
                    if ($number === null && $text === null) {
                        return;
                    }
                    $unit = $code === 'ram_gb' ? $unitIds['gb'] : ($code === 'screen_inches' ? $unitIds['inch'] : null);

                    DB::table('catalog_product_attribute_values')->updateOrInsert(
                        ['product_id' => $productId, 'attribute_id' => $attributeIds[$code], 'option_id' => null],
                        [
                            'value_number' => $number,
                            'value_text_ar' => null,
                            'value_text_en' => $text,
                            'unit_id' => $unit,
                            'sort_order' => 0,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                };

                $write('processor', null, $processor);
                $write('ram_gb', (float) $ramGb, null);
                $write('storage', null, $storage);
                $write('screen_inches', (float) $screenInches, null);
                $write('os', null, $os);
                if ($gpu) {
                    $write('gpu', null, $gpu);
                }
            }
        }
    }
}
